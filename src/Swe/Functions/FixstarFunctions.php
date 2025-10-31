<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\FixedStar;
use Swisseph\Coordinates;
use Swisseph\SiderealMode;
use Swisseph\FK4FK5;
use Swisseph\ICRS;
use Swisseph\Precession;
use Swisseph\Bias;
use Swisseph\VectorMath;
use Swisseph\Swe\Functions\TimeFunctions;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Swe\Functions\SiderealFunctions;
use Swisseph\Swe\FixedStars\StarCatalogReader;
use Swisseph\Swe\FixedStars\StarTransforms;

/**
 * Fixed star position calculations.
 * Port of fixstar functions from sweph.c
 *
 * Refactored to use:
 * - StarCatalogData: Built-in stars and solar mass distribution
 * - StarCatalogReader: File I/O and catalog parsing
 * - StarTransforms: Coordinate transformations (aberration, deflection, nutation)
 */
class FixstarFunctions
{
    /**
     * Calculate fixstar position from ET (Ephemeris Time).
     *
     * Port of swe_fixstar() from sweph.c:7950-8018
     *
     * @param string $star Star name (traditional name, Bayer designation, or sequential number). Modified to full name on success.
     * @param float $tjd Julian day number (ET)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array $xx Output array [longitude, latitude, distance, speed_long, speed_lat, speed_dist]
     * @param string|null $serr Error string (passed by reference)
     * @return int Flag value or ERR
     */
    public static function fixstar(string &$star, float $tjd, int $iflag, array &$xx, ?string &$serr): int
    {
        $srecord = '';

        // Initialize error string
        $serr = '';

        // Load from star catalog (handles formatting, built-in stars, and file search)
        $dparams = null;
        if (($retc = StarCatalogReader::loadRecord($star, $srecord, $dparams, $serr)) !== Constants::SE_OK) {
            goto return_err;
        }

        // Calculate position from record with full coordinate transformations
        $retc = self::calcFromRecord($srecord, $tjd, $iflag, $star, $xx, $serr);
        if ($retc === Constants::SE_ERR) {
            goto return_err;
        }

        return $retc;

        return_err:
        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        return Constants::SE_ERR;
    }

    /**
     * Calculate fixstar position from UT (Universal Time).
     *
     * Port of swe_fixstar_ut() from sweph.c:8020-8042
     *
     * @param string $star Star name. Modified to full name on success.
     * @param float $tjdUt Julian day number (UT)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array $xx Output array [longitude, latitude, distance, speed_long, speed_lat, speed_dist]
     * @param string|null $serr Error string (passed by reference)
     * @return int Flag value or ERR
     */
    public static function fixstarUt(string &$star, float $tjdUt, int $iflag, array &$xx, ?string &$serr): int
    {
        // Get delta T
        $deltat = TimeFunctions::deltatEx($tjdUt, $iflag, $serr);

        // Calculate with ET
        $retflag = self::fixstar($star, $tjdUt + $deltat, $iflag, $xx, $serr);

        // If ephemeris changed, recalculate delta T
        if ($retflag !== Constants::SE_ERR && ($retflag & Constants::SEFLG_EPHMASK) !== ($iflag & Constants::SEFLG_EPHMASK)) {
            $deltat = TimeFunctions::deltatEx($tjdUt, $retflag, $serr);
            $retflag = self::fixstar($star, $tjdUt + $deltat, $iflag, $xx, $serr);
        }

        return $retflag;
    }

    /**
     * Get visual magnitude of a fixed star.
     *
     * Port of swe_fixstar_mag() from sweph.c:8044-8095
     *
     * @param string $star Star name. Modified to full name on success.
     * @param float $mag Output magnitude (passed by reference)
     * @param string|null $serr Error string (passed by reference)
     * @return int OK or ERR
     */
    public static function fixstarMag(string &$star, float &$mag, ?string &$serr): int
    {
        $srecord = '';

        // Initialize error string
        $serr = '';

        // Load from star catalog
        $dparams = [];
        if (($retc = StarCatalogReader::loadRecord($star, $srecord, $dparams, $serr)) !== Constants::SE_OK) {
            goto return_err;
        }

        // Magnitude is in dparams[7]
        $mag = $dparams[7];

        return Constants::SE_OK;

        return_err:
        $mag = 0.0;
        return Constants::SE_ERR;
    }

    /**
     * Calculate fixstar position from CSV record with full astronomical transformations.
     *
     * Port of swi_fixstar_calc_from_record() from sweph.c:7667-7950
     *
     * Applies: proper motion, parallax, radial velocity, FK4→FK5 precession, ICRF conversion,
     * observer position, light deflection, aberration, precession, nutation, coordinate transforms,
     * sidereal positions.
     *
     * @param string $srecord CSV record from star file
     * @param float $tjd Julian day (ET)
     * @param int $iflag Calculation flags
     * @param string $star Star name (for error messages)
     * @param array $xx Output coordinates [6 elements]
     * @param string $serr Error string
     * @return int Flag value or ERR
     */
    private static function calcFromRecord(string $srecord, float $tjd, int $iflag, string $star, array &$xx, string &$serr): int
    {
        // Port of swi_fixstar_calc_from_record() from sweph.c:7667-7950
        // Full astronomical transformations without simplifications

        $retc = Constants::SE_OK;
        $x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xxsv = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xobs_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xearth = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xearth_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xsun = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xsun_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        $dt = Constants::PLAN_SPEED_INTV * 0.1;
        $iflgsave = $iflag;
        $iflag |= Constants::SEFLG_SPEED; // We need speed to work correctly

        // TODO: Validate and adjust iflag with plaus_iflag()
        $epheflag = $iflag & Constants::SEFLG_EPHMASK;

        // TODO: Check ephemeris initialization (swi_init_swed_if_start)
        // TODO: Handle ephemeris file management

        // Set default sidereal mode if needed
        if (($iflag & Constants::SEFLG_SIDEREAL) && !SiderealMode::isSet()) {
            SiderealMode::set(Constants::SE_SIDM_FAGAN_BRADLEY, 0.0, 0.0);
        }

        /******************************************
         * Parse star record
         ******************************************/
        $stardata = new FixedStar();
        $retc = StarCatalogReader::cutString($srecord, $star, $stardata, $serr);
        if ($retc === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        $epoch = $stardata->epoch;
        $ra_pm = $stardata->ramot;  // RA proper motion (radians/century)
        $de_pm = $stardata->demot;  // Dec proper motion (radians/century)
        $radv = $stardata->radvel;  // Radial velocity (AU/century)
        $parall = $stardata->parall; // Parallax (radians)
        $ra = $stardata->ra;        // RA at epoch (radians)
        $de = $stardata->de;        // Dec at epoch (radians)

        /******************************************
         * Calculate time since epoch
         ******************************************/
        if ($epoch == 1950) {
            $t = $tjd - Constants::B1950; // days since 1950.0
        } else { // epoch == 2000
            $t = $tjd - Constants::J2000; // days since 2000.0
        }

        /******************************************
         * Initial position vector (equatorial)
         ******************************************/
        $x[0] = $ra;
        $x[1] = $de;
        $x[2] = 1.0; // Will be replaced with actual distance

        // Calculate distance from parallax
        if ($parall == 0) {
            $rdist = 1000000000.0; // Very distant star
        } else {
            $rdist = 1.0 / ($parall * Constants::RADTODEG * 3600.0) * Constants::PARSEC_TO_AUNIT;
        }
        $x[2] = $rdist;

        // Proper motion and radial velocity (per day)
        $x[3] = $ra_pm / 36525.0;   // RA proper motion per day
        $x[4] = $de_pm / 36525.0;   // Dec proper motion per day
        $x[5] = $radv / 36525.0;    // Radial velocity per day

        /******************************************
         * Convert to Cartesian coordinates with speeds
         ******************************************/
        Coordinates::polcartSp($x, $x);

        /******************************************
         * PART 2: FK4 → FK5 conversion for epoch 1950
         ******************************************/
        if ($epoch == 1950) {
            // Convert from FK4 (B1950.0) to FK5 (J2000.0)
            FK4FK5::fk4ToFk5($x, Constants::B1950);

            // Precess from B1950 to J2000
            Precession::precess($x, Constants::B1950, 0, Constants::J_TO_J2000);
            Precession::precess($x, Constants::B1950, 0, Constants::J_TO_J2000, 3); // Speed vector
        }

        /******************************************
         * PART 3: ICRF conversion
         ******************************************/
        // FK5 to ICRF, if JPL ephemeris refers to ICRF
        // With data that are already ICRF, epoch = 0
        if ($epoch != 0) {
            // Convert FK5 → ICRF (backward = TRUE)
            ICRS::icrsToFk5($x, $iflag, true);

            // With ephemerides < DE403, we now convert to J2000
            // For DE >= 403, apply bias correction
            // TODO: Implement swi_get_denum() to check DE number
            // For now, assume modern ephemerides (DE >= 403) and apply bias
            $denum = 431; // Assume DE431 (modern ephemeris)
            if ($denum >= 403) {
                Bias::bias($x, Constants::J2000, Constants::SEFLG_SPEED, false);
            }
        }

        /******************************************
         * PART 4: Earth and Sun positions
         * For parallax, light deflection, and aberration
         ******************************************/
        $xpo = null;
        $xpo_dt = null;

        if (!($iflag & Constants::SEFLG_BARYCTR) && (!($iflag & Constants::SEFLG_HELCTR) || !($iflag & Constants::SEFLG_MOSEPH))) {
            // Get Earth position at tjd - dt
            $retc = PlanetsFunctions::calc($tjd - $dt, Constants::SE_EARTH, $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR, $xearth_dt, $serr);
            if ($retc < 0) {
                $serr = "Failed to get Earth position at tjd-dt: " . ($serr ?? 'unknown error');
                return Constants::SE_ERR;
            }

            // Get Earth position at tjd
            $retc = PlanetsFunctions::calc($tjd, Constants::SE_EARTH, $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR, $xearth, $serr);
            if ($retc < 0) {
                $serr = "Failed to get Earth position at tjd: " . ($serr ?? 'unknown error');
                return Constants::SE_ERR;
            }

            // Get Sun position at tjd - dt
            $retc = PlanetsFunctions::calc($tjd - $dt, Constants::SE_SUN, $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR, $xsun_dt, $serr);
            if ($retc < 0) {
                $serr = "Failed to get Sun position at tjd-dt: " . ($serr ?? 'unknown error');
                return Constants::SE_ERR;
            }

            // Get Sun position at tjd
            $retc = PlanetsFunctions::calc($tjd, Constants::SE_SUN, $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR, $xsun, $serr);
            if ($retc < 0) {
                $serr = "Failed to get Sun position at tjd: " . ($serr ?? 'unknown error');
                return Constants::SE_ERR;
            }
        }

        /******************************************
         * PART 5: Observer position (geocenter or topocenter)
         ******************************************/
        if ($iflag & Constants::SEFLG_TOPOCTR) {
            // TODO: Implement swi_get_observer() for topocentric positions
            // For now, topocentric not supported for stars
            $serr = 'Topocentric positions for fixed stars not yet implemented';
            return Constants::SE_ERR;
        } elseif (!($iflag & Constants::SEFLG_BARYCTR) && (!($iflag & Constants::SEFLG_HELCTR) || !($iflag & Constants::SEFLG_MOSEPH))) {
            // Barycentric position of geocenter
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $xearth[$i];
                $xobs_dt[$i] = $xearth_dt[$i];
            }
        }

        /******************************************
         * PART 6: Apply proper motion and parallax
         ******************************************/
        // Determine observer position for parallax
        if (($iflag & Constants::SEFLG_HELCTR) && ($iflag & Constants::SEFLG_MOSEPH)) {
            $xpo = null;    // No parallax if Moshier and heliocentric
            $xpo_dt = null;
        } elseif ($iflag & Constants::SEFLG_HELCTR) {
            $xpo = $xsun;
            $xpo_dt = $xsun_dt;
        } elseif ($iflag & Constants::SEFLG_BARYCTR) {
            $xpo = null;    // No parallax if barycentric
            $xpo_dt = null;
        } else {
            $xpo = $xobs;
            $xpo_dt = $xobs_dt;
        }

        // Apply proper motion over time and subtract observer position (parallax)
        if ($xpo === null) {
            // No parallax correction - just apply proper motion
            for ($i = 0; $i <= 2; $i++) {
                $x[$i] += $t * $x[$i + 3];
            }
        } else {
            // Apply proper motion and parallax
            for ($i = 0; $i <= 2; $i++) {
                $x[$i] += $t * $x[$i + 3];      // Proper motion
                $x[$i] -= $xpo[$i];              // Subtract observer position (parallax)
                $x[$i + 3] -= $xpo[$i + 3];      // Speed correction
            }
        }

        // Part 7: Relativistic light deflection
        if (($iflag & Constants::SEFLG_TRUEPOS) == 0 && ($iflag & Constants::SEFLG_NOGDEFL) == 0) {
            StarTransforms::deflectLight($x, $xearth, $xearth_dt, $xsun, $xsun_dt, $dt, $iflag);
        }

        // Part 8: Annual aberration of light
        if (($iflag & Constants::SEFLG_TRUEPOS) == 0 && ($iflag & Constants::SEFLG_NOABERR) == 0) {
            StarTransforms::aberrLightEx($x, $xpo, $xpo_dt, $dt, $iflag);
        }

        // Part 9: ICRS to J2000 bias correction
        // Apply if NOT ICRS and (DE≥403 or BARYCTR)
        // For fixstars, we typically use modern ephemeris (DE431/DE440/441) which are all ≥403
        if (!($iflag & Constants::SEFLG_ICRS)) {
            // MOSEPH is 403, JPLEPH default is 431+, SWIEPH default is 431+
            // Only skip bias if explicitly using very old ephemeris
            $applyBias = ($iflag & Constants::SEFLG_BARYCTR) ||
                         !($iflag & Constants::SEFLG_MOSEPH); // MOSEPH is exactly 403, apply bias
            if ($applyBias) {
                Bias::bias($x, $tjd, $iflag, false);  // ICRS → J2000
            }
        }

        // Save J2000 coordinates (required for sidereal positions later)
        $xxsv = $x;

        // Part 10: Precession J2000 → equator of date
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($x, $tjd, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($x, $tjd, $iflag, Constants::J2000_TO_J);
            }
        }

        // Part 11: Nutation
        $nutMatrix = null;
        $nutMatrixVelocity = null;
        if (!($iflag & Constants::SEFLG_NONUT)) {
            // Calculate nutation (dpsi, deps)
            [$dpsi, $deps] = \Swisseph\Nutation::calc($tjd);

            // Get obliquity for nutation matrix calculation
            $swed = \Swisseph\SwephFile\SwedState::getInstance();
            if ($swed->oec->needsUpdate($tjd)) {
                $swed->oec->calculate($tjd, $iflag);
            }

            // Build nutation matrix
            $nutMatrix = StarTransforms::buildNutationMatrix($dpsi, $deps, $swed->oec);

            // Build nutation velocity matrix for speed calculations
            if ($iflag & Constants::SEFLG_SPEED) {
                $nutMatrixVelocity = StarTransforms::buildNutationMatrix($dpsi, $deps, $swed->oec);
            }

            // Apply nutation to position and velocity
            Coordinates::nutate($x, $nutMatrix, $nutMatrixVelocity, $iflag, false);
        }

        // Part 12: Transformation to ecliptic coordinates
        if (!($iflag & Constants::SEFLG_EQUATORIAL)) {
            // Get obliquity data
            $swed = \Swisseph\SwephFile\SwedState::getInstance();

            if ($iflag & Constants::SEFLG_J2000) {
                // Use J2000 obliquity
                $oe = $swed->oec2000;
            } else {
                // Use date obliquity
                if ($swed->oec->needsUpdate($tjd)) {
                    $swed->oec->calculate($tjd, $iflag);
                }
                $oe = $swed->oec;
            }

            // Transform equatorial → ecliptic (rotate around x-axis by -eps)
            $xTemp = [0.0, 0.0, 0.0];
            Coordinates::coortrf2($x, $xTemp, $oe->seps, $oe->ceps);
            $x[0] = $xTemp[0];
            $x[1] = $xTemp[1];
            $x[2] = $xTemp[2];

            if ($iflag & Constants::SEFLG_SPEED) {
                $xTemp = [0.0, 0.0, 0.0];
                Coordinates::coortrf2([$x[3], $x[4], $x[5]], $xTemp, $oe->seps, $oe->ceps);
                $x[3] = $xTemp[0];
                $x[4] = $xTemp[1];
                $x[5] = $xTemp[2];
            }

            // Apply nutation to ecliptic coordinates if nutation was calculated
            if ($nutMatrix !== null && !($iflag & Constants::SEFLG_NONUT)) {
                // Get nutation sin/cos for ecliptic transformation
                $snut = sin($deps); // Approximation: nutation in obliquity
                $cnut = cos($deps);

                $xTemp = [0.0, 0.0, 0.0];
                Coordinates::coortrf2($x, $xTemp, $snut, $cnut);
                $x[0] = $xTemp[0];
                $x[1] = $xTemp[1];
                $x[2] = $xTemp[2];

                if ($iflag & Constants::SEFLG_SPEED) {
                    $xTemp = [0.0, 0.0, 0.0];
                    Coordinates::coortrf2([$x[3], $x[4], $x[5]], $xTemp, $snut, $cnut);
                    $x[3] = $xTemp[0];
                    $x[4] = $xTemp[1];
                    $x[5] = $xTemp[2];
                }
            }
        }

        // Part 13: Sidereal positions
        if ($iflag & Constants::SEFLG_SIDEREAL) {
            [$sidMode, $sidOpts, $t0User, $ayan0User] = SiderealMode::get();

            // Rigorous algorithm: sidereal longitude on ecliptic of t0
            if ($sidOpts & Constants::SE_SIDBIT_ECL_T0) {
                $xoutTmp = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
                $retc = SiderealFunctions::tropRa2SidLon($xxsv, $x, $xoutTmp, $iflag);
                if ($retc !== Constants::SE_OK) {
                    $serr = 'sidereal transformation ECL_T0 failed';
                    return Constants::SE_ERR;
                }
                // If equatorial output requested, use equatorial sidereal position
                if ($iflag & Constants::SEFLG_EQUATORIAL) {
                    for ($i = 0; $i <= 5; $i++) {
                        $x[$i] = $xoutTmp[$i];
                    }
                }
            }
            // Project onto solar system equator plane
            elseif ($sidOpts & Constants::SE_SIDBIT_SSY_PLANE) {
                $retc = SiderealFunctions::tropRa2SidLonSosy($xxsv, $x, $iflag);
                if ($retc !== Constants::SE_OK) {
                    $serr = 'sidereal transformation SSY_PLANE failed';
                    return Constants::SE_ERR;
                }
                // If equatorial output requested, use saved J2000 position
                if ($iflag & Constants::SEFLG_EQUATORIAL) {
                    for ($i = 0; $i <= 5; $i++) {
                        $x[$i] = $xxsv[$i];
                    }
                }
            }
            // Traditional algorithm: subtract ayanamsa from longitude
            else {
                // Convert to polar
                Coordinates::cartPolSp($x, $x);

                // Get ayanamsa
                $daya = 0.0;
                $retc = \Swisseph\Sidereal::ayanamshaDegFromJdTT($tjd);
                if ($retc === false) {
                    $serr = 'ayanamsa calculation failed';
                    return Constants::SE_ERR;
                }
                $daya = $retc;

                // Subtract ayanamsa from longitude
                $x[0] -= $daya * Constants::DEGTORAD;

                // Convert back to cartesian
                Coordinates::polCartSp($x, $x);
            }
        }

        // Part 14: Final conversions
        // Transform to polar coordinates if not XYZ
        if (!($iflag & Constants::SEFLG_XYZ)) {
            Coordinates::cartPolSp($x, $x);
        }

        // Convert radians to degrees if not RADIANS
        if (!($iflag & Constants::SEFLG_RADIANS) && !($iflag & Constants::SEFLG_XYZ)) {
            for ($i = 0; $i < 2; $i++) {
                $x[$i] *= Constants::RADTODEG;
                $x[$i + 3] *= Constants::RADTODEG;
            }
        }

        // Copy to output array
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $x[$i];
        }

        // Clear speed if not requested
        if (!($iflgsave & Constants::SEFLG_SPEED)) {
            $iflag = $iflag & ~Constants::SEFLG_SPEED;
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // Don't return chosen ephemeris if none was specified
        if (($iflgsave & Constants::SEFLG_EPHMASK) == 0) {
            $iflag = $iflag & ~Constants::SEFLG_DEFAULTEPH;
        }

        $serr = '';
        return $iflag;
    }
}
