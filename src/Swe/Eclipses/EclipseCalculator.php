<?php

declare(strict_types=1);

namespace Swisseph\Swe\Eclipses;

use Swisseph\Constants;

/**
 * Eclipse calculation functions
 * Ported from swecl.c (Swiss Ephemeris C library)
 *
 * Internal helper functions for eclipse computations.
 * NOT for direct public use - wrapped by SolarEclipseFunctions/LunarEclipseFunctions.
 */
class EclipseCalculator
{
    /**
     * Calculate position of planet or fixed star
     * Ported from swecl.c:888-897 (calc_planet_star)
     *
     * Simple wrapper that calls either swe_calc() or swe_fixstar()
     * depending on whether a star name is provided.
     *
     * @param float $tjdEt Julian day in ET/TT
     * @param int $ipl Planet number (SE_SUN, SE_MOON, etc.)
     * @param string|null $starname Star name for swe_fixstar() (null for planets)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$x Output: position array [longitude, latitude, distance, ...]
     * @param string|null &$serr Output: error message
     * @return int OK or ERR
     */
    public static function calcPlanetStar(
        float $tjdEt,
        int $ipl,
        ?string $starname,
        int $iflag,
        array &$x,
        ?string &$serr = null
    ): int {
        $retc = Constants::OK;

        // If starname is provided and not empty, calculate fixed star
        if ($starname !== null && $starname !== '') {
            $retc = \swe_fixstar($starname, $tjdEt, $iflag, $x, $serr);
        } else {
            // Otherwise calculate planet
            $retc = \swe_calc($tjdEt, $ipl, $iflag, $x, $serr);
        }

        return $retc;
    }

    /**
     * Compute attributes of a solar/lunar eclipse for given tjd and location
     * Ported from swecl.c:967-1150 (eclipse_how)
     *
     * Calculates eclipse parameters at a specific geographic location:
     * - Eclipse type (total, annular, partial)
     * - Magnitude and obscuration
     * - Azimuth and altitude of Sun/planet
     * - Saros series number
     *
     * PART 1: Basic calculations (lines 967-1050)
     * - Initialize arrays and flags
     * - Calculate positions (equatorial and cartesian)
     * - Compute radii (rmoon, rsun)
     * - Calculate angular distance (dctr)
     * - Determine eclipse type
     *
     * @param float $tjdUt Julian day in UT
     * @param int $ipl Planet number (SE_SUN for solar eclipse)
     * @param string|null $starname Star name (null for planets)
     * @param int $ifl Ephemeris flags
     * @param float $geolon Geographic longitude (degrees)
     * @param float $geolat Geographic latitude (degrees)
     * @param float $geohgt Geographic height (meters above sea level)
     * @param array &$attr Output: eclipse attributes [0-10+]
     *   attr[0]: fraction of solar diameter covered by moon (magnitude)
     *   attr[1]: ratio of lunar diameter to solar one
     *   attr[2]: fraction of solar disc covered by moon (obscuration)
     *   attr[3]: diameter of core shadow in km
     *   attr[4]: azimuth of sun at tjd
     *   attr[5]: true altitude of sun above horizon at tjd
     *   attr[6]: apparent altitude of sun above horizon at tjd
     *   attr[7]: elongation of moon in degrees
     *   attr[8]: magnitude acc. to NASA
     *   attr[9]: saros series number
     *   attr[10]: saros series member number
     * @param string|null &$serr Output: error message
     * @return int Eclipse type flags (SE_ECL_TOTAL|ANNULAR|PARTIAL|VISIBLE) or 0
     */
    public static function eclipseHow(
        float $tjdUt,
        int $ipl,
        ?string $starname,
        int $ifl,
        float $geolon,
        float $geolat,
        float $geohgt,
        array &$attr,
        ?string &$serr = null
    ): int {
        $retc = 0;

        // Initialize variables
        $xs = array_fill(0, 6, 0.0);  // Planet cartesian
        $xm = array_fill(0, 6, 0.0);  // Moon cartesian
        $ls = array_fill(0, 6, 0.0);  // Planet equatorial
        $lm = array_fill(0, 6, 0.0);  // Moon equatorial
        $x1 = array_fill(0, 6, 0.0);  // Normalized planet vector
        $x2 = array_fill(0, 6, 0.0);  // Normalized moon vector
        $xh = array_fill(0, 6, 0.0);  // Azimuth/altitude
        $geopos = [$geolon, $geolat, $geohgt];

        // Initialize attr array to zeros
        for ($i = 0; $i < 10; $i++) {
            $attr[$i] = 0.0;
        }

        // Convert UT to ET
        $te = $tjdUt + \swe_deltat_ex($tjdUt, $ifl, $serr);

        // Set topocentric observer position
        \swe_set_topo($geolon, $geolat, $geohgt);

        // Calculate flags
        $iflag = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR | $ifl;
        $iflagcart = $iflag | Constants::SEFLG_XYZ;

        // Calculate planet/star position (equatorial)
        if (self::calcPlanetStar($te, $ipl, $starname, $iflag, $ls, $serr) === Constants::ERR) {
            return Constants::ERR;
        }

        // Calculate moon position (equatorial)
        if (\swe_calc($te, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::ERR) {
            return Constants::ERR;
        }

        // Calculate planet/star position (cartesian)
        if (self::calcPlanetStar($te, $ipl, $starname, $iflagcart, $xs, $serr) === Constants::ERR) {
            return Constants::ERR;
        }

        // Calculate moon position (cartesian)
        if (\swe_calc($te, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::ERR) {
            return Constants::ERR;
        }

        // Calculate radius of planet disk in AU
        if ($starname !== null && $starname !== '') {
            // Fixed star has no disk
            $drad = 0.0;
        } elseif ($ipl < count(EclipseUtils::PLA_DIAM)) {
            // Planet from diameter table
            $drad = EclipseUtils::PLA_DIAM[$ipl] / 2.0 / Constants::AUNIT;
        } elseif ($ipl > Constants::SE_AST_OFFSET) {
            // Asteroid - would need swed.ast_diam
            // For now use 0, proper implementation needs State access
            $drad = 0.0;
        } else {
            $drad = 0.0;
        }

        // Calculate azimuth and altitude of sun/planet
        // Using modern method (not USE_AZ_NAV)
        \swe_azalt($tjdUt, Constants::SE_EQU2HOR, $geopos, 0, 10, $ls, $xh);
        // xh now contains: [azimuth, true_altitude, apparent_altitude, ...]

        // Eclipse geometry calculations
        // Angular radius of moon as seen from observer
        $rmoon = asin(EclipseUtils::RMOON / $lm[2]) * Constants::RADTODEG;

        // Angular radius of sun/planet as seen from observer
        $rsun = asin($drad / $ls[2]) * Constants::RADTODEG;

        // Sum and difference of radii
        $rsplusrm = $rsun + $rmoon;
        $rsminusrm = $rsun - $rmoon;

        // Normalize position vectors to unit vectors
        for ($i = 0; $i < 3; $i++) {
            $x1[$i] = $xs[$i] / $ls[2];  // Planet unit vector
            $x2[$i] = $xm[$i] / $lm[2];  // Moon unit vector
        }

        // Angular distance between centers
        $dctr = acos(\Swisseph\VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;

        // Determine eclipse type based on angular distances
        if ($dctr < $rsminusrm) {
            // Moon smaller than sun -> annular eclipse
            $retc = Constants::SE_ECL_ANNULAR;
        } elseif ($dctr < abs($rsminusrm)) {
            // Moon larger than sun -> total eclipse
            $retc = Constants::SE_ECL_TOTAL;
        } elseif ($dctr < $rsplusrm) {
            // Disks overlap -> partial eclipse
            $retc = Constants::SE_ECL_PARTIAL;
        } else {
            // No eclipse at this location
            $retc = 0;
            if ($serr !== null) {
                $serr = sprintf("no solar eclipse at tjd = %f", $tjdUt);
            }
        }

        // attr[1]: ratio of lunar diameter to solar one
        if ($rsun > 0) {
            $attr[1] = $rmoon / $rsun;
        } else {
            $attr[1] = 0.0;
        }

        // TO BE CONTINUED: PART 2 will add:
        // - attr[0]: magnitude
        // - attr[2]: obscuration
        // - attr[4-6]: azimuth/altitude
        // - attr[7]: elongation
        // - attr[8]: NASA magnitude
        // - attr[9-10]: Saros series

        return $retc;
    }
}
