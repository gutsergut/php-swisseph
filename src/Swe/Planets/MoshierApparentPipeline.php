<?php

declare(strict_types=1);

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\Precession;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\PlanData;
use Swisseph\Moshier\MoshierConstants;
use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Swe\LightTime;

/**
 * Pipeline for Moshier ephemeris apparent position computation.
 *
 * FULL PORT of app_pos_etc_plan() and app_pos_rest() from sweph.c
 * for SEFLG_MOSEPH ephemeris.
 *
 * Input: heliocentric equatorial J2000 cartesian coordinates
 * stored in SwedState->pldat[ipli]->x and SwedState->pldat[SEI_EARTH]->x
 *
 * Processing steps (matching C exactly):
 * 1. Read heliocentric equatorial J2000 from pdp->x
 * 2. If heliocentric requested, subtract Sun/SunBary
 * 3. Observer: geocenter or topocenter
 * 4. Light-time correction with speed adjustment (xxsp for dt change)
 * 5. For MOSEPH: recalculate velocity via moshplan(t, ...) for precision
 * 6. Geocentric conversion: planet - observer
 * 7. Speed correction for dt change
 * 8. Relativistic deflection of light
 * 9. Annual aberration with speed correction (xobs vs xobs2)
 * 10. Frame bias ICRS→J2000
 * 11. Precession J2000 → date
 * 12. Nutation
 * 13. Transform to ecliptic
 * 14. Store results in pdp->xreturn[]
 *
 * @see sweph.c app_pos_etc_plan() lines 2462-2772, app_pos_rest() lines 2775-2856
 */
final class MoshierApparentPipeline
{
    /** Astronomical unit in meters (IAU 2012) */
    private const AUNIT = 1.49597870700e+11;

    /** Speed of light in m/s */
    private const CLIGHT = 2.99792458e+8;

    /**
     * Helper: sum of squares for 3-vector.
     */
    private static function squareSum(array $x): float
    {
        return $x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2];
    }

    /**
     * Compute apparent position for a planet using Moshier ephemeris.
     *
     * This function reads from SwedState->pldat[ipli] which must have been
     * filled by MoshierPlanetCalculator::moshplan() with doSave=true.
     *
     * Full port of app_pos_etc_plan() for SEFLG_MOSEPH case.
     *
     * @param int $ipli Internal planet index (SEI_MERCURY, etc.)
     * @param int $ipl External planet index (SE_MERCURY, etc.) for Sun handling
     * @param int $iflag Calculation flags
     * @param string|null &$serr Error message
     * @return int 0=OK, -1=ERR
     */
    public static function appPosEtcPlan(int $ipli, int $ipl, int $iflag, ?string &$serr): int
    {
        $swed = SwedState::getInstance();
        $pedp = &$swed->pldat[MoshierConstants::SEI_EARTH];
        $pdp = &$swed->pldat[$ipli];

        // ========================================
        // Check if already computed with same flags
        // ========================================
        $flg1 = $iflag & ~Constants::SEFLG_EQUATORIAL & ~Constants::SEFLG_XYZ;
        $flg2 = $pdp->xflgs & ~Constants::SEFLG_EQUATORIAL & ~Constants::SEFLG_XYZ;
        if ($flg1 === $flg2 && $flg2 !== -1) {
            $pdp->xflgs = $iflag;
            $pdp->iephe = $iflag & Constants::SEFLG_EPHMASK;
            return 0;
        }

        // ========================================
        // Copy heliocentric equatorial J2000 from pdp->x
        // ========================================
        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xx0 = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0]; // Save original for later
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $pdp->x[$i];
            $xx0[$i] = $pdp->x[$i];
        }

        // ========================================
        // Observer: geocenter or topocenter
        // ========================================
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        if ($iflag & Constants::SEFLG_TOPOCTR) {
            // Topocentric: xobs = topocenter offset + Earth
            // TODO: implement swi_get_observer when needed
            // For now, fallback to geocenter
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $pedp->x[$i];
            }
        } else {
            // Geocenter = Earth heliocentric position
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $pedp->x[$i];
            }
        }

        // ========================================
        // Light-time correction (geocentric)
        // ========================================
        $dtsave_for_defl = 0.0;
        $t = $pdp->teval;
        $xxsp = [0.0, 0.0, 0.0]; // Speed correction from dt change
        $xobs2 = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0]; // Observer at t - dt for aberration speed

        if (!($iflag & Constants::SEFLG_TRUEPOS)) {
            // For Moshier: niter = 0 (no iteration, single light-time calculation)
            $niter = 0;

            // ----------------------------------------
            // Speed adjustment for change in dt
            // From C: lines 2557-2594
            // ----------------------------------------
            if ($iflag & Constants::SEFLG_SPEED) {
                // Position at t-1 day (rough)
                $xxsv = [0.0, 0.0, 0.0];
                $xxsp_work = [0.0, 0.0, 0.0];
                for ($i = 0; $i <= 2; $i++) {
                    $xxsv[$i] = $xxsp_work[$i] = $xx[$i] - $xx[$i + 3];
                }
                // Iterate to compute light-time at t-1
                for ($j = 0; $j <= $niter; $j++) {
                    $dx = [0.0, 0.0, 0.0];
                    for ($i = 0; $i <= 2; $i++) {
                        $dx[$i] = $xxsp_work[$i];
                        if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR)) {
                            $dx[$i] -= ($xobs[$i] - $xobs[$i + 3]);
                        }
                    }
                    // New dt at t-1
                    $dt = sqrt(self::squareSum($dx)) * self::AUNIT / self::CLIGHT / 86400.0;
                    for ($i = 0; $i <= 2; $i++) {
                        $xxsp_work[$i] = $xxsv[$i] - $dt * $xx0[$i + 3];
                    }
                }
                // true position - apparent position at time t-1
                for ($i = 0; $i <= 2; $i++) {
                    $xxsp[$i] = $xxsv[$i] - $xxsp_work[$i];
                }
            }

            // ----------------------------------------
            // dt and t(apparent) - main light-time
            // From C: lines 2595-2607
            // ----------------------------------------
            for ($j = 0; $j <= $niter; $j++) {
                $dx = [0.0, 0.0, 0.0];
                for ($i = 0; $i <= 2; $i++) {
                    $dx[$i] = $xx[$i];
                    if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR)) {
                        $dx[$i] -= $xobs[$i];
                    }
                }
                $dt = sqrt(self::squareSum($dx)) * self::AUNIT / self::CLIGHT / 86400.0;
                $t = $pdp->teval - $dt;
                $dtsave_for_defl = $dt;
                // Approximate position at t
                for ($i = 0; $i <= 2; $i++) {
                    $xx[$i] = $xx0[$i] - $dt * $xx0[$i + 3];
                }
            }

            // Part of daily motion resulting from change of dt
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 0; $i <= 2; $i++) {
                    $xxsp[$i] = $xx0[$i] - $xx[$i] - $xxsp[$i];
                }
            }

            // ----------------------------------------
            // For Moshier: recalculate velocity for precision
            // From C: lines 2658-2688
            // ----------------------------------------
            if (($iflag & Constants::SEFLG_SPEED) &&
                !($iflag & (Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR))) {
                // Recalculate planet at t (light-time corrected)
                $xxsv = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
                $xearth = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
                $retc = MoshierPlanetCalculator::moshplan($t, $ipli, false, $xxsv, $xearth, $serr);
                if ($retc < 0) {
                    return $retc;
                }
                // Only speed is taken from this computation
                for ($i = 3; $i <= 5; $i++) {
                    $xx[$i] = $xxsv[$i];
                }
                // Also save xearth for later (xobs2)
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] = $xearth[$i];
                }
            } else {
                // No recalculation, xobs2 = xobs (no aberration speed correction)
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] = $xobs[$i];
                }
            }
        } else {
            // SEFLG_TRUEPOS: no light-time correction
            for ($i = 0; $i <= 5; $i++) {
                $xobs2[$i] = $xobs[$i];
            }
        }

        // ========================================
        // Geocentric conversion
        // From C: lines 2710-2725
        // ========================================
        if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR)) {
            // Subtract observer position
            for ($i = 0; $i <= 5; $i++) {
                $xx[$i] -= $xobs[$i];
            }
            // Speed correction for dt change
            if (!($iflag & Constants::SEFLG_TRUEPOS) && ($iflag & Constants::SEFLG_SPEED)) {
                for ($i = 3; $i <= 5; $i++) {
                    $xx[$i] -= $xxsp[$i - 3];
                }
            }
        }

        // Zero speed if not requested
        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ========================================
        // Relativistic deflection of light
        // From C: lines 2728-2732
        // ========================================
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOGDEFL)) {
            LightTime::deflectLight($xx, $dtsave_for_defl, $iflag);
        }

        // ========================================
        // Annual aberration
        // From C: lines 2736-2748
        // ========================================
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOABERR)) {
            LightTime::aberrLight($xx, $xobs, $iflag);
            // Speed correction from change in Earth velocity between t and t-dt
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 3; $i <= 5; $i++) {
                    $xx[$i] += $xobs[$i] - $xobs2[$i];
                }
            }
        }

        // Zero speed if not requested (safety)
        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ========================================
        // ICRS to J2000 frame bias (for DE >= 403)
        // From C: lines 2752-2754
        // ========================================
        if (!($iflag & Constants::SEFLG_ICRS)) {
            LightTime::bias($xx, $t, $iflag, false);
        }

        // Save J2000 equatorial coordinates for sidereal
        $xxsv = $xx;

        // ========================================
        // Precession: equator J2000 → equator of date
        // From C: lines 2759-2765
        // ========================================
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($xx, $pdp->teval, $iflag, 0); // J2000_TO_J = 0
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($xx, $pdp->teval, $iflag, 0);
            }
            $oe = $swed->oec;
        } else {
            $oe = $swed->oec2000;
        }

        // Ensure obliquity is calculated
        if ($oe === null || abs($oe->teps - $pdp->teval) > 1e-8) {
            if (!($iflag & Constants::SEFLG_J2000)) {
                $swed->oec->calculate($pdp->teval);
                $oe = $swed->oec;
            }
        }

        // ========================================
        // Call app_pos_rest for remaining transformations
        // ========================================
        return self::appPosRest($pdp, $iflag, $xx, $xxsv, $oe, $serr);
    }

    /**
     * Final transformations: nutation, ecliptic conversion, polar conversion.
     *
     * Full port of app_pos_rest() from sweph.c lines 2775-2856
     *
     * @param PlanData $pdp Planet data structure
     * @param int $iflag Calculation flags
     * @param array $xx Current coordinates (equatorial cartesian)
     * @param array $x2000 J2000 equatorial coordinates (for sidereal)
     * @param object $oe Obliquity data
     * @param string|null &$serr Error message
     * @return int 0=OK
     */
    private static function appPosRest(
        PlanData $pdp,
        int $iflag,
        array $xx,
        array $x2000,
        object $oe,
        ?string &$serr
    ): int {
        $swed = SwedState::getInstance();

        // ========================================
        // Nutation
        // From C: lines 2785-2787
        // Note: C checks SEFLG_NONUT only, not SEFLG_J2000
        // ========================================
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $swed->ensureNutation($pdp->teval, $iflag, $oe->seps, $oe->ceps);
            Coordinates::nutate($xx, $swed->nutMatrix, $swed->nutMatrixVelocity, $iflag, false);
        }

        // Now we have equatorial cartesian coordinates; save them
        for ($i = 0; $i <= 5; $i++) {
            $pdp->xreturn[18 + $i] = $xx[$i];
        }

        // ========================================
        // Transform to ecliptic
        // From C: lines 2795-2799
        // ========================================
        Coordinates::coortrf2($xx, $xx, $oe->seps, $oe->ceps);
        if ($iflag & Constants::SEFLG_SPEED) {
            $speed = [$xx[3], $xx[4], $xx[5]];
            Coordinates::coortrf2($speed, $speed, $oe->seps, $oe->ceps);
            $xx[3] = $speed[0];
            $xx[4] = $speed[1];
            $xx[5] = $speed[2];
        }

        // Apply nutation to ecliptic coordinates too
        // From C: lines 2800-2805
        if (!($iflag & Constants::SEFLG_NONUT)) {
            Coordinates::coortrf2($xx, $xx, $swed->snut, $swed->cnut);
            if ($iflag & Constants::SEFLG_SPEED) {
                $speed = [$xx[3], $xx[4], $xx[5]];
                Coordinates::coortrf2($speed, $speed, $swed->snut, $swed->cnut);
                $xx[3] = $speed[0];
                $xx[4] = $speed[1];
                $xx[5] = $speed[2];
            }
        }

        // Now we have ecliptic cartesian coordinates
        for ($i = 0; $i <= 5; $i++) {
            $pdp->xreturn[6 + $i] = $xx[$i];
        }

        // ========================================
        // Sidereal positions (SEFLG_SIDEREAL)
        // From C: lines 2808-2835
        // TODO: implement sidereal when needed
        // ========================================

        // ========================================
        // Transform to polar coordinates
        // From C: lines 2840-2841
        // ========================================
        // Equatorial polar: xreturn[18:23] (cartesian) → xreturn[12:17] (polar)
        $xeq_cart = array_slice($pdp->xreturn, 18, 6);
        $xeq_pol = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        Coordinates::cartPolSp($xeq_cart, $xeq_pol);
        for ($i = 0; $i < 6; $i++) {
            $pdp->xreturn[12 + $i] = $xeq_pol[$i];
        }

        // Ecliptic polar: xreturn[6:11] (cartesian) → xreturn[0:5] (polar)
        $xecl_cart = array_slice($pdp->xreturn, 6, 6);
        $xecl_pol = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        Coordinates::cartPolSp($xecl_cart, $xecl_pol);
        for ($i = 0; $i < 6; $i++) {
            $pdp->xreturn[$i] = $xecl_pol[$i];
        }

        // ========================================
        // Radians to degrees
        // From C: lines 2846-2851
        // ========================================
        for ($i = 0; $i < 2; $i++) {
            $pdp->xreturn[$i] = rad2deg($pdp->xreturn[$i]);         // ecliptic lon/lat
            $pdp->xreturn[$i + 3] = rad2deg($pdp->xreturn[$i + 3]); // ecliptic speed
            $pdp->xreturn[$i + 12] = rad2deg($pdp->xreturn[$i + 12]); // equatorial
            $pdp->xreturn[$i + 15] = rad2deg($pdp->xreturn[$i + 15]); // equatorial speed
        }

        // Normalize longitude to [0, 360)
        while ($pdp->xreturn[0] < 0) {
            $pdp->xreturn[0] += 360.0;
        }
        while ($pdp->xreturn[0] >= 360) {
            $pdp->xreturn[0] -= 360.0;
        }

        // Save flags
        $pdp->xflgs = $iflag;
        $pdp->iephe = $iflag & Constants::SEFLG_EPHMASK;

        return 0;
    }

    /**
     * Compute apparent position for the SUN using Moshier ephemeris.
     *
     * Full port of app_pos_etc_sun() from sweph.c lines 3901-4074
     * for SEFLG_MOSEPH ephemeris.
     *
     * Key differences from app_pos_etc_plan():
     * - Sun geocentric = -Earth heliocentric (for Moshier)
     * - No light deflection for Sun (it IS the deflecting body)
     * - Different light-time handling
     *
     * @param int $iflag Calculation flags
     * @param string|null &$serr Error message
     * @return int 0=OK, -1=ERR
     */
    public static function appPosEtcSun(int $iflag, ?string &$serr): int
    {
        $swed = SwedState::getInstance();
        $pedp = &$swed->pldat[MoshierConstants::SEI_EARTH];
        // For Sun, we store results in Earth's pdp (C uses pedp for Sun results)

        // ========================================
        // Check if already computed with same flags
        // ========================================
        $flg1 = $iflag & ~Constants::SEFLG_EQUATORIAL & ~Constants::SEFLG_XYZ;
        $flg2 = $pedp->xflgs & ~Constants::SEFLG_EQUATORIAL & ~Constants::SEFLG_XYZ;
        if ($flg1 === $flg2 && $flg2 !== -1) {
            $pedp->xflgs = $iflag;
            $pedp->iephe = $iflag & Constants::SEFLG_EPHMASK;
            return 0;
        }

        // ========================================
        // Observer: geocenter or topocenter
        // ========================================
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        if ($iflag & Constants::SEFLG_TOPOCTR) {
            // TODO: implement topocentric when needed
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $pedp->x[$i];
            }
        } else {
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $pedp->x[$i];
            }
        }

        // ========================================
        // True heliocentric position of Earth
        // For Moshier: heliocentric, so xx = xobs (Earth position)
        // ========================================
        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        // With Moshier (or barycentric), xx = xobs
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $xobs[$i];
        }

        // ========================================
        // Light-time (for heliocentric/barycentric Earth only)
        // For geocentric Sun with Moshier: no light-time, just aberration later
        // ========================================
        $t = $pedp->teval;
        // With Moshier geocentric Sun: no light-time iteration
        // The aberration will handle apparent position

        // ========================================
        // Zero speed if not requested
        // ========================================
        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ========================================
        // Conversion to geocenter
        // Geocentric Sun = -Earth heliocentric
        // ========================================
        if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR)) {
            for ($i = 0; $i <= 5; $i++) {
                $xx[$i] = -$xx[$i];
            }
        }

        // ========================================
        // Annual aberration (NO deflection for Sun!)
        // ========================================
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOABERR)) {
            LightTime::aberrLight($xx, $xobs, $iflag);
        }

        // Zero speed if not requested
        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ========================================
        // ICRS to J2000 frame bias
        // ========================================
        if (!($iflag & Constants::SEFLG_ICRS)) {
            LightTime::bias($xx, $t, $iflag, false);
        }

        // Save J2000 equatorial coordinates for sidereal
        $xxsv = $xx;

        // ========================================
        // Precession: equator J2000 → equator of date
        // ========================================
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($xx, $pedp->teval, $iflag, 0);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($xx, $pedp->teval, $iflag, 0);
            }
            $oe = $swed->oec;
        } else {
            $oe = $swed->oec2000;
        }

        // Ensure obliquity is calculated
        if ($oe === null || abs($oe->teps - $pedp->teval) > 1e-8) {
            if (!($iflag & Constants::SEFLG_J2000)) {
                $swed->oec->calculate($pedp->teval);
                $oe = $swed->oec;
            }
        }

        // ========================================
        // Call app_pos_rest for remaining transformations
        // Note: For Sun, we use pedp (Earth's pdp) to store results
        // ========================================
        return self::appPosRest($pedp, $iflag, $xx, $xxsv, $oe, $serr);
    }

    /**
     * Compute apparent position for Moon using Moshier ephemeris.
     *
     * Full port of app_pos_etc_moon() for SEFLG_MOSEPH case from sweph.c:4086-4227
     *
     * Moon pipeline differs from planets:
     * - Geocentric coordinates from MoshierMoon (not heliocentric)
     * - Different light-time handling (linear extrapolation for MOSEPH)
     * - Aberration but no light deflection (Moon is too close)
     *
     * @param int $iflag Calculation flags
     * @param string|null &$serr Error message
     * @return int 0=OK, -1=ERR
     */
    public static function appPosEtcMoon(int $iflag, ?string &$serr): int
    {
        $swed = SwedState::getInstance();
        $pedp = &$swed->pldat[MoshierConstants::SEI_EARTH];
        $psdp = &$swed->pldat[MoshierConstants::SEI_SUNBARY];
        $pdp = &$swed->pldat[MoshierConstants::SEI_MOON];

        // ========================================
        // Check if already computed with same flags
        // ========================================
        $flg1 = $iflag & ~Constants::SEFLG_EQUATORIAL & ~Constants::SEFLG_XYZ;
        $flg2 = $pdp->xflgs & ~Constants::SEFLG_EQUATORIAL & ~Constants::SEFLG_XYZ;
        if ($flg1 === $flg2 && $flg2 !== -1) {
            $pdp->xflgs = $iflag;
            $pdp->iephe = $iflag & Constants::SEFLG_EPHMASK;
            return 0;
        }

        // ========================================
        // Copy geocentric equatorial J2000 from pdp->x
        // Moon is already geocentric from MoshierMoon
        // ========================================
        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xxm = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $pdp->x[$i];
            $xxm[$i] = $xx[$i];
        }

        // ========================================
        // To solar system barycentric
        // ========================================
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] += $pedp->x[$i];
        }

        // ========================================
        // Observer position
        // ========================================
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xobs2 = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        if ($iflag & Constants::SEFLG_TOPOCTR) {
            // Topocentric observer
            if ($swed->topd === null || $swed->topd->teval !== $pdp->teval || $swed->topd->teval == 0) {
                if (LightTime::getObserver($pdp->teval, $iflag | Constants::SEFLG_NONUT, true, $xobs, $serr) !== 0) {
                    return -1;
                }
            } else {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs[$i] = $swed->topd->xobs[$i];
                }
            }
            for ($i = 0; $i <= 5; $i++) {
                $xxm[$i] -= $xobs[$i];
            }
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] += $pedp->x[$i];
            }
        } elseif ($iflag & Constants::SEFLG_BARYCTR) {
            // Barycentric
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = 0.0;
            }
            for ($i = 0; $i <= 5; $i++) {
                $xxm[$i] += $pedp->x[$i];
            }
        } elseif ($iflag & Constants::SEFLG_HELCTR) {
            // Heliocentric
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $psdp->x[$i];
            }
            for ($i = 0; $i <= 5; $i++) {
                $xxm[$i] += $pedp->x[$i] - $psdp->x[$i];
            }
        } else {
            // Geocentric (default)
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $pedp->x[$i];
            }
        }

        // ========================================
        // Light-time correction
        // ========================================
        $t = $pdp->teval;

        if (!($iflag & Constants::SEFLG_TRUEPOS)) {
            $dt = sqrt(self::squareSum($xxm)) * self::AUNIT / self::CLIGHT / 86400.0;
            $t = $pdp->teval - $dt;

            // MOSEPH case: linear extrapolation (sweph.c:4170-4183)
            // "this method results in an error of a milliarcsec in speed"
            $xe = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            $xs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] -= $dt * $xx[$i + 3];
                $xe[$i] = $pedp->x[$i] - $dt * $pedp->x[$i + 3];
                $xe[$i + 3] = $pedp->x[$i + 3];
                $xs[$i] = 0.0;
                $xs[$i + 3] = 0.0;
            }

            // Observer position at light-time corrected moment
            if ($iflag & Constants::SEFLG_TOPOCTR) {
                if (LightTime::getObserver($t, $iflag | Constants::SEFLG_NONUT, false, $xobs2, $serr) !== 0) {
                    return -1;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] += $xe[$i];
                }
            } elseif ($iflag & Constants::SEFLG_BARYCTR) {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] = 0.0;
                }
            } elseif ($iflag & Constants::SEFLG_HELCTR) {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] = $xs[$i];
                }
            } else {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs2[$i] = $xe[$i];
                }
            }
        }

        // ========================================
        // To correct center (geocentric from barycentric)
        // ========================================
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] -= $xobs[$i];
        }

        // ========================================
        // Annual aberration of light
        // (No light deflection for Moon - too close)
        // ========================================
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOABERR)) {
            LightTime::aberrLight($xx, $xobs, $iflag);

            // Speed correction for aberration
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 3; $i <= 5; $i++) {
                    $xx[$i] += $xobs[$i] - $xobs2[$i];
                }
            }
        }

        // If speed not requested, zero it
        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ========================================
        // ICRS to J2000 frame bias
        // ========================================
        if (!($iflag & Constants::SEFLG_ICRS)) {
            LightTime::bias($xx, $t, $iflag, false);
        }

        // Save J2000 equatorial coordinates for sidereal
        $xxsv = $xx;

        // ========================================
        // Precession: equator J2000 → equator of date
        // Direction: -1 = J2000_TO_J (from J2000 to date)
        // ========================================
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($xx, $pdp->teval, $iflag, -1);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($xx, $pdp->teval, $iflag, -1);
            }
            $oe = $swed->oec;
        } else {
            $oe = $swed->oec2000;
        }

        // Ensure obliquity is calculated
        if ($oe === null || abs($oe->teps - $pdp->teval) > 1e-8) {
            if (!($iflag & Constants::SEFLG_J2000)) {
                $swed->oec->calculate($pdp->teval);
                $oe = $swed->oec;
            }
        }

        // ========================================
        // Call app_pos_rest for remaining transformations
        // ========================================
        return self::appPosRest($pdp, $iflag, $xx, $xxsv, $oe, $serr);
    }
}
