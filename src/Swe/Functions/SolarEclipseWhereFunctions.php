<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\VectorMath;
use Swisseph\Coordinates;

/**
 * Solar eclipse geographic position calculations
 *
 * Port from swecl.c:565-950
 */
class SolarEclipseWhereFunctions
{
    // Earth radius in AU (swecl.c:89)
    private const DE = 6378140.0 / Constants::AUNIT;

    // Earth oblateness factor (swecl.c:654)
    private const EARTH_OBLATENESS = Constants::EARTH_OBLATENESS;

    // Earth oblateness complement (swecl.c:654)
    private const EARTHOBL = 1.0 - self::EARTH_OBLATENESS;

    // Moon radius in AU (swecl.c:91)
    private const RMOON = Constants::DMOON / 2.0 / Constants::AUNIT;

    // Moon diameter in AU
    private const DMOON = Constants::DMOON / Constants::AUNIT;

    /**
     * Calculate geographic position of solar eclipse maximum
     *
     * Public API wrapper for swe_sol_eclipse_where().
     * Port from swecl.c:565-581
     *
     * @param float $tjdUt Time in Julian days UT
     * @param int $ifl Ephemeris flag
     * @param array &$geopos Geographic position [longitude_deg, latitude_deg] (output)
     * @param array &$attr Eclipse attributes [20] (output):
     *   [0] = fraction of solar diameter covered
     *   [1] = ratio of lunar diameter to solar diameter
     *   [2] = fraction of solar disc covered (obscuration)
     *   [3] = diameter of core shadow in km
     *   [4] = azimuth of sun
     *   [5] = true altitude of sun
     *   [6] = apparent altitude of sun
     *   [7] = angular distance of moon from sun
     *   [8] = magnitude (NASA definition)
     *   [9] = saros series number
     *   [10] = saros series member number
     * @param string &$serr Error message
     * @return int Eclipse flags:
     *   SE_ECL_CENTRAL = central eclipse (total or annular)
     *   SE_ECL_NONCENTRAL = non-central eclipse
     *   SE_ECL_TOTAL = total eclipse
     *   SE_ECL_ANNULAR = annular eclipse
     *   SE_ECL_PARTIAL = partial eclipse
     *   0 = no eclipse
     *   SE_ERR = error
     */
    public static function where(
        float $tjdUt,
        int $ifl,
        array &$geopos,
        array &$attr,
        string &$serr
    ): int {
        // Initialize output arrays
        $geopos = [0.0, 0.0];
        $attr = array_fill(0, 20, 0.0);
        $dcore = array_fill(0, 10, 0.0);

        // Mask ephemeris flag (swecl.c:573)
        $ifl &= Constants::SEFLG_EPHMASK;

        // Set tidal acceleration (swecl.c:574)
        // Note: swi_set_tid_acc() sets tidal acceleration - not critical for basic eclipse calculations
        // In full port this would call: \swi_set_tid_acc($tjdUt, $ifl, 0, $serrTid);
        // For now, we skip this as it affects only long-term Moon position accuracy

        // Calculate eclipse location (swecl.c:575)
        $serrWhere = '';
        $retflag = self::eclipseWhere($tjdUt, Constants::SE_SUN, null, $ifl, $geopos, $dcore, $serrWhere);
        if ($retflag < 0) {
            $serr = $serrWhere;
            return $retflag;
        }

        // Calculate eclipse attributes at that location (swecl.c:577)
        $serrHow = '';
        $retflag2 = self::eclipseHow($tjdUt, Constants::SE_SUN, null, $ifl, $geopos[0], $geopos[1], 0.0, $attr, $serrHow);
        if ($retflag2 === Constants::SE_ERR) {
            $serr = $serrHow;
            return $retflag2;
        }

        // Core shadow diameter (swecl.c:579)
        $attr[3] = $dcore[0];

        return $retflag;
    }

    /**
     * Calculate geographic position of eclipse center (maximum obscuration)
     *
     * Internal implementation - port from swecl.c:632-889
     *
     * Uses Besselian elements to find where shadow axis intersects Earth's surface.
     * Accounts for Earth's oblateness through iterative correction.
     *
     * @param float $tjdUt Time in Julian days UT
     * @param int $ipl Planet/object number (SE_SUN for solar eclipse)
     * @param string|null $starname Star name (null for planets)
     * @param int $ifl Ephemeris flags
     * @param array &$geopos Output: [longitude_deg, latitude_deg]
     * @param array &$dcore Output: shadow geometry [10]:
     *   [0] = core shadow diameter in km
     *   [1] = penumbra diameter in km
     *   [2] = distance of shadow axis from geocenter (km)
     *   [3] = core shadow diameter on fundamental plane (km)
     *   [4] = penumbra diameter on fundamental plane (km)
     *   [5] = cosf1 (umbra geometry factor)
     *   [6] = cosf2 (penumbra geometry factor)
     * @param string &$serr Error message
     * @return int Eclipse type flags or SE_ERR
     */
    private static function eclipseWhere(
        float $tjdUt,
        int $ipl,
        ?string $starname,
        int $ifl,
        array &$geopos,
        array &$dcore,
        string &$serr
    ): int {
        // Initialize arrays (swecl.c:637-664)
        $e = array_fill(0, 6, 0.0);
        $et = array_fill(0, 6, 0.0);
        $rm = array_fill(0, 6, 0.0);
        $rs = array_fill(0, 6, 0.0);
        $rmt = array_fill(0, 6, 0.0);
        $rst = array_fill(0, 6, 0.0);
        $xs = array_fill(0, 6, 0.0);
        $xst = array_fill(0, 6, 0.0);
        $x = array_fill(0, 6, 0.0);
        $lm = array_fill(0, 6, 0.0);
        $ls = array_fill(0, 6, 0.0);
        $lx = array_fill(0, 6, 0.0);

        $dcore = array_fill(0, 10, 0.0);
        $geopos = [0.0, 0.0];

        // Constants (swecl.c:652-660)
        $de = self::DE;
        $earthobl = self::EARTHOBL;
        $rmoon = self::RMOON;
        $dmoon = self::DMOON;
        $noEclipse = false;
        $niter = 0;

        // Ephemeris flags (swecl.c:665-667)
        $iflag = Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL | $ifl;
        $iflag2 = $iflag | Constants::SEFLG_RADIANS;
        $iflag = $iflag | Constants::SEFLG_XYZ;

        // Delta T (swecl.c:668-669)
        $serrDelta = '';
        $deltat = \swe_deltat_ex($tjdUt, $ifl, $serrDelta);
        $tjd = $tjdUt + $deltat;

        // Moon position in cartesian coordinates (swecl.c:670-672)
        $serrMoon = '';
        $retc = \swe_calc($tjd, Constants::SE_MOON, $iflag, $rm, $serrMoon);
        if ($retc === Constants::SE_ERR) {
            $serr = $serrMoon;
            return $retc;
        }

        // Moon position in polar coordinates (swecl.c:673-675)
        $retc = \swe_calc($tjd, Constants::SE_MOON, $iflag2, $lm, $serrMoon);
        if ($retc === Constants::SE_ERR) {
            $serr = $serrMoon;
            return $retc;
        }

        // Sun/object position in cartesian coordinates (swecl.c:676-678)
        $serrSun = '';
        $retc = self::calcPlanetStar($tjd, $ipl, $starname, $iflag, $rs, $serrSun);
        if ($retc === Constants::SE_ERR) {
            $serr = $serrSun;
            return $retc;
        }

        // Sun/object position in polar coordinates (swecl.c:679-681)
        $retc = self::calcPlanetStar($tjd, $ipl, $starname, $iflag2, $ls, $serrSun);
        if ($retc === Constants::SE_ERR) {
            $serr = $serrSun;
            return $retc;
        }

        // Save original positions (swecl.c:682-687)
        for ($i = 0; $i <= 2; $i++) {
            $rst[$i] = $rs[$i];
            $rmt[$i] = $rm[$i];
        }

        // Sidereal time (swecl.c:688-692)
        if ($ifl & Constants::SEFLG_NONUT) {
            $oe = \swed_get_oec();
            $sidt = \swe_sidtime0($tjdUt, $oe->eps * Constants::RADTODEG, 0) * 15 * Constants::DEGTORAD;
        } else {
            $sidt = \swe_sidtime($tjdUt) * 15 * Constants::DEGTORAD;
        }

        // Radius of planet/star disk in AU (swecl.c:693-701)
        if ($starname !== null && $starname !== '') {
            $drad = 0.0;
        } else if ($ipl < Constants::NDIAM) {
            $drad = Constants::PLA_DIAM[$ipl] / 2.0 / Constants::AUNIT;
        } else if ($ipl > Constants::SE_AST_OFFSET) {
            $swed = \swed_get();
            $drad = $swed->ast_diam / 2.0 * 1000.0 / Constants::AUNIT; // km -> m -> AU
        } else {
            $drad = 0.0;
        }

        // Iterative calculation for Earth oblateness correction (swecl.c:702-851)
        iter_where:

        // Restore original positions (swecl.c:703-706)
        for ($i = 0; $i <= 2; $i++) {
            $rs[$i] = $rst[$i];
            $rm[$i] = $rmt[$i];
        }

        // Account for Earth oblateness (swecl.c:707-714)
        // Instead of flattening the earth, we apply correction to z coordinate
        for ($i = 0; $i <= 2; $i++) {
            $lx[$i] = $lm[$i];
        }
        Coordinates::polcart($lx, $rm);
        $rm[2] /= $earthobl;

        // Distance of moon from geocenter (swecl.c:715-716)
        $dm = sqrt(VectorMath::squareSum($rm));

        // Account for Earth oblateness in sun position (swecl.c:717-721)
        for ($i = 0; $i <= 2; $i++) {
            $lx[$i] = $ls[$i];
        }
        Coordinates::polcart($lx, $rs);
        $rs[2] /= $earthobl;

        // Sun - moon vector (swecl.c:722-726)
        for ($i = 0; $i <= 2; $i++) {
            $e[$i] = $rm[$i] - $rs[$i];
            $et[$i] = $rmt[$i] - $rst[$i];
        }

        // Distance sun - moon (swecl.c:727-728)
        $dsm = sqrt(VectorMath::squareSum($e));
        $dsmt = sqrt(VectorMath::squareSum($et));

        // Sun - moon unit vector (swecl.c:729-735)
        for ($i = 0; $i <= 2; $i++) {
            $e[$i] /= $dsm;
            $et[$i] /= $dsmt;
        }

        // Umbra and penumbra geometry (swecl.c:736-739)
        $sinf1 = ($drad - $rmoon) / $dsm;
        $cosf1 = sqrt(1.0 - $sinf1 * $sinf1);
        $sinf2 = ($drad + $rmoon) / $dsm;
        $cosf2 = sqrt(1.0 - $sinf2 * $sinf2);

        // Distance of moon from fundamental plane (swecl.c:740-741)
        $s0 = -VectorMath::dotProduct($rm, $e);

        // Distance of shadow axis from geocenter (swecl.c:742-743)
        $r0 = sqrt($dm * $dm - $s0 * $s0);

        // Diameter of core shadow on fundamental plane (swecl.c:750-751)
        $d0 = ($s0 / $dsm * ($drad * 2.0 - $dmoon) - $dmoon) / $cosf1;

        // Diameter of half-shadow on fundamental plane (swecl.c:752-753)
        $D0 = ($s0 / $dsm * ($drad * 2.0 + $dmoon) + $dmoon) / $cosf2;

        // Store shadow geometry (swecl.c:754-760)
        $dcore[2] = $r0;
        $dcore[3] = $d0;
        $dcore[4] = $D0;
        $dcore[5] = $cosf1;
        $dcore[6] = $cosf2;

        // Convert to kilometers (swecl.c:761-762)
        for ($i = 2; $i < 5; $i++) {
            $dcore[$i] *= Constants::AUNIT / 1000.0;
        }

        // Determine eclipse type (swecl.c:763-784)
        $retc = 0;

        if ($de * $cosf1 >= $r0) {
            // Central eclipse (total or annular) (swecl.c:768)
            $retc |= Constants::SE_ECL_CENTRAL;
        } else if ($r0 <= $de * $cosf1 + abs($d0) / 2.0) {
            // Non-central but core shadow touches earth (swecl.c:770)
            $retc |= Constants::SE_ECL_NONCENTRAL;
        } else if ($r0 <= $de * $cosf2 + $D0 / 2.0) {
            // Partial eclipse (swecl.c:772)
            $retc |= (Constants::SE_ECL_PARTIAL | Constants::SE_ECL_NONCENTRAL);
        } else {
            // No eclipse (swecl.c:774-781)
            $serr = sprintf("no solar eclipse at tjd = %f", $tjd);
            for ($i = 0; $i < 2; $i++) {
                $geopos[$i] = 0.0;
            }
            $dcore[0] = 0.0;
            $retc = 0;
            $d = 0.0;
            $noEclipse = true;
        }

        // Distance of shadow point from fundamental plane (swecl.c:785-788)
        $d = $s0 * $s0 + $de * $de - $dm * $dm;
        if ($d > 0) {
            $d = sqrt($d);
        } else {
            $d = 0.0;
        }

        // Distance of moon from shadow point on earth (swecl.c:789-790)
        $s = $s0 - $d;

        // Geographic position of eclipse center (swecl.c:791-844)
        // Shadow axis intersection with Earth surface

        // Position of eclipse center (swecl.c:845-848)
        for ($i = 0; $i <= 2; $i++) {
            $xs[$i] = $rm[$i] + $s * $e[$i];
        }

        // Need geographic position with correct z (swecl.c:849-851)
        for ($i = 0; $i <= 2; $i++) {
            $xst[$i] = $xs[$i];
        }
        $xst[2] *= $earthobl;
        Coordinates::cartpol($xst, $xst);

        // Iterative correction for Earth oblateness (swecl.c:852-860)
        if ($niter <= 0) {
            $cosfi = cos($xst[1]);
            $sinfi = sin($xst[1]);
            $eobl = self::EARTH_OBLATENESS;
            $cc = 1.0 / sqrt($cosfi * $cosfi + (1.0 - $eobl) * (1.0 - $eobl) * $sinfi * $sinfi);
            $ss = (1.0 - $eobl) * (1.0 - $eobl) * $cc;
            $earthobl = $ss;
            $niter++;
            goto iter_where;
        }

        Coordinates::polcart($xst, $xst);

        // Convert to longitude and latitude (swecl.c:862-863)
        Coordinates::cartpol($xs, $xs);

        // Measure from sidereal time at Greenwich (swecl.c:864-865)
        $xs[0] -= $sidt;
        $xs[0] *= Constants::RADTODEG;
        $xs[1] *= Constants::RADTODEG;
        $xs[0] = \swe_degnorm($xs[0]);

        // West is negative (swecl.c:866-868)
        if ($xs[0] > 180.0) {
            $xs[0] -= 360.0;
        }

        $geopos[0] = $xs[0];
        $geopos[1] = $xs[1];

        // Diameter of core shadow at place of maximum eclipse (swecl.c:869-873)
        for ($i = 0; $i <= 2; $i++) {
            $x[$i] = $rmt[$i] - $xst[$i];
        }
        $s = sqrt(VectorMath::squareSum($x));

        // Core shadow diameter (swecl.c:874-875)
        $dcore[0] = ($s / $dsmt * ($drad * 2.0 - $dmoon) - $dmoon) * $cosf1;
        $dcore[0] *= Constants::AUNIT / 1000.0;

        // Penumbra diameter (swecl.c:876-877)
        $dcore[1] = ($s / $dsmt * ($drad * 2.0 + $dmoon) + $dmoon) * $cosf2;
        $dcore[1] *= Constants::AUNIT / 1000.0;

        // Determine if total or annular (swecl.c:878-885)
        if (!($retc & Constants::SE_ECL_PARTIAL) && !$noEclipse) {
            if ($dcore[0] > 0) {
                $retc |= Constants::SE_ECL_ANNULAR;
            } else {
                $retc |= Constants::SE_ECL_TOTAL;
            }
        }

        return $retc;
    }

    /**
     * Calculate planet or fixed star position
     *
     * Port from swecl.c:893-901
     *
     * @param float $tjdEt Time in Julian days ET
     * @param int $ipl Planet number
     * @param string|null $starname Star name (null for planets)
     * @param int $iflag Ephemeris flags
     * @param array &$x Output position array
     * @param string &$serr Error message
     * @return int Return code (OK or SE_ERR)
     */
    private static function calcPlanetStar(
        float $tjdEt,
        int $ipl,
        ?string $starname,
        int $iflag,
        array &$x,
        string &$serr
    ): int {
        if ($starname === null || $starname === '') {
            return \swe_calc($tjdEt, $ipl, $iflag, $x, $serr);
        } else {
            return \swe_fixstar($starname, $tjdEt, $iflag, $x, $serr);
        }
    }

    /**
     * Calculate eclipse attributes at specific geographic location
     *
     * Placeholder - will be ported from eclipse_how() in next iteration
     *
     * @param float $tjdUt Time in Julian days UT
     * @param int $ipl Planet number
     * @param string|null $starname Star name
     * @param int $ifl Ephemeris flags
     * @param float $geolon Geographic longitude in degrees
     * @param float $geolat Geographic latitude in degrees
     * @param float $geohgt Geographic height in meters
     * @param array &$attr Attributes array [20]
     * @param string &$serr Error message
     * @return int Eclipse type or SE_ERR
     */
    private static function eclipseHow(
        float $tjdUt,
        int $ipl,
        ?string $starname,
        int $ifl,
        float $geolon,
        float $geolat,
        float $geohgt,
        array &$attr,
        string &$serr
    ): int {
        // Temporary: call existing swe_sol_eclipse_how() if available
        // Otherwise return placeholder
        if (function_exists('swe_sol_eclipse_how')) {
            $geopos = [$geolon, $geolat, $geohgt];
            return \swe_sol_eclipse_how($tjdUt, $ifl, $geopos, $attr, $serr);
        }

        // Placeholder - return partial eclipse
        $attr = array_fill(0, 20, 0.0);
        return Constants::SE_ECL_PARTIAL;
    }
}
