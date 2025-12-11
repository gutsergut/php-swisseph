<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Swe\Eclipses\EclipseCalculator;
use Swisseph\Swe\Eclipses\SarosData;

/**
 * Lunar Eclipse Functions
 * Ported from swecl.c (Swiss Ephemeris C library)
 *
 * Public API functions for lunar eclipse calculations.
 * These are the functions exposed as global swe_lun_eclipse_*().
 *
 * WITHOUT SIMPLIFICATIONS - complete C port with:
 * - Full umbral and penumbral shadow calculations
 * - Saros series detection
 * - Azimuth/altitude of Moon during eclipse
 * - All eclipse phases (penumbral, partial, total)
 */
class LunarEclipseFunctions
{
    /**
     * Compute attributes of a lunar eclipse at a given time
     * Ported from swecl.c:3190-3228 (swe_lun_eclipse_how)
     *
     * Calculates eclipse parameters for a specific time and location:
     * - Eclipse type (total, partial, penumbral)
     * - Umbral and penumbral magnitudes
     * - Azimuth and altitude of Moon (if geopos provided)
     * - Saros series information
     *
     * @param float $tjdUt Julian day in UT
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param array|null $geopos Geographic position [longitude, latitude, height] or null
     *   - geopos[0]: longitude in degrees (east positive)
     *   - geopos[1]: latitude in degrees (north positive)
     *   - geopos[2]: height in meters above sea level
     *   If null: only eclipse parameters, no azimuth/altitude
     * @param array &$attr Output: eclipse attributes [0-10]
     *   - attr[0]: umbral magnitude
     *   - attr[1]: penumbral magnitude
     *   - attr[2]: (not used for lunar eclipses)
     *   - attr[3]: (not used for lunar eclipses)
     *   - attr[4]: azimuth of moon at tjd (if geopos provided)
     *   - attr[5]: true altitude of moon above horizon (if geopos provided)
     *   - attr[6]: apparent altitude of moon above horizon (if geopos provided)
     *   - attr[7]: angular distance from opposition (180° - distance)
     *   - attr[8]: umbral magnitude (same as attr[0])
     *   - attr[9]: saros series number
     *   - attr[10]: saros series member number
     * @param string|null &$serr Output: error message
     * @return int Eclipse type flags:
     *   - SE_ECL_TOTAL: total lunar eclipse
     *   - SE_ECL_PARTIAL: partial lunar eclipse
     *   - SE_ECL_PENUMBRAL: penumbral lunar eclipse only
     *   - 0: no lunar eclipse at this time
     *   - ERR: error occurred
     */
    public static function how(
        float $tjdUt,
        int $ifl,
        ?array $geopos,
        array &$attr,
        ?string &$serr = null
    ): int {
        $attr = array_fill(0, 20, 0.0);
        $serr = null;

        // Validate geopos altitude if provided (swecl.c:3201-3205)
        if ($geopos !== null) {
            if (!is_array($geopos) || count($geopos) < 3) {
                $serr = 'geopos must be [lon, lat, alt] or null';
                return Constants::SE_ERR;
            }

            $alt = (float)($geopos[2] ?? 0.0);
            if ($alt < -500.0 || $alt > 25000.0) {
                $serr = sprintf(
                    'location for eclipses must be between %.0f and %.0f m above sea',
                    -500.0,
                    25000.0
                );
                return Constants::SE_ERR;
            }
        }

        // Remove SEFLG_TOPOCTR flag (swecl.c:3206)
        $ifl = $ifl & ~Constants::SEFLG_TOPOCTR;
        $ifl &= ~(Constants::SEFLG_JPLHOR | Constants::SEFLG_JPLHOR_APPROX);

        // Calculate internal eclipse parameters
        $dcore = [];
        $retc = self::lunEclipseHow($tjdUt, $ifl, $attr, $dcore, $serr);

        // If no geopos, return eclipse type only
        if ($geopos === null) {
            return $retc;
        }

        // Calculate azimuth and altitude of Moon (swecl.c:3213-3223)
        \swe_set_topo($geopos[0], $geopos[1], $geopos[2]);

        $lm = [];
        if (\swe_calc_ut($tjdUt, Constants::SE_MOON, $ifl | Constants::SEFLG_TOPOCTR | Constants::SEFLG_EQUATORIAL, $lm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        $xaz = [];
        \swe_azalt($tjdUt, Constants::SE_EQU2HOR, $geopos, 0, 10, $lm, $xaz);

        $attr[4] = $xaz[0]; // azimuth
        $attr[5] = $xaz[1]; // true altitude
        $attr[6] = $xaz[2]; // apparent altitude

        // If Moon below horizon, return 0 (swecl.c:3224-3225)
        if ($xaz[2] <= 0) {
            $retc = 0;
        }

        return $retc;
    }

    /**
     * Internal lunar eclipse calculation
     * Ported from swecl.c:3237-3377 (lun_eclipse_how)
     *
     * WITHOUT SIMPLIFICATIONS - full algorithm:
     * - Selenocentric coordinates
     * - Core and penumbral shadow diameters
     * - Umbral and penumbral magnitudes
     * - Saros series detection
     *
     * @param float $tjdUt Julian day in UT
     * @param int $ifl Ephemeris flags
     * @param array &$attr Output: eclipse attributes
     * @param array &$dcore Output: shadow parameters
     *   - dcore[0]: distance of shadow axis from geocenter r0
     *   - dcore[1]: diameter of core shadow on fundamental plane d0
     *   - dcore[2]: diameter of half-shadow on fundamental plane D0
     *   - dcore[3]: cosf1 (core shadow cone angle cosine)
     *   - dcore[4]: cosf2 (penumbral shadow cone angle cosine)
     * @param string|null &$serr Error message
     * @return int Eclipse type or ERR
     */
    private static function lunEclipseHow(
        float $tjdUt,
        int $ifl,
        array &$attr,
        array &$dcore,
        ?string &$serr
    ): int {
        // Initialize arrays (swecl.c:3253-3255)
        $dcore = array_fill(0, 10, 0.0);
        $attr = array_fill(0, 20, 0.0);

        // Constants from sweph.h and swecl.c:79-86
        // All distances in AU (astronomical units)
        $AUNIT = 1.49597870700e+11; // AU in meters (sweph.h:273)
        $RMOON = 1737400.0 / $AUNIT;      // Moon radius in AU
        $DMOON = 2 * $RMOON;              // Moon diameter in AU
        $RSUN = 695990000.0 / $AUNIT;     // Sun radius in AU
        $DSUN = 2 * $RSUN;                // Sun diameter in AU
        $REARTH = 6378136.6 / $AUNIT;     // Earth radius in AU
        $DEARTH = 2 * $REARTH;            // Earth diameter in AU
        $RADTODEG = 57.29577951308232088; // 180/PI

        // Ephemeris flags: equatorial + cartesian + speed (swecl.c:3258-3259)
        $iflag = Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL | $ifl;
        $iflag |= Constants::SEFLG_XYZ;

        // Delta T (swecl.c:3260-3261)
        $deltat = \swe_deltat_ex($tjdUt, $ifl, $serr);
        $tjd = $tjdUt + $deltat;

        // Moon in cartesian coordinates (swecl.c:3262-3264)
        $rm = [];
        if (\swe_calc($tjd, Constants::SE_MOON, $iflag, $rm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Distance of moon from geocenter (swecl.c:3265-3266)
        $dm = sqrt($rm[0] * $rm[0] + $rm[1] * $rm[1] + $rm[2] * $rm[2]);

        // Sun in cartesian coordinates (swecl.c:3267-3269)
        $rs = [];
        if (\swe_calc($tjd, Constants::SE_SUN, $iflag, $rs, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Distance of sun from geocenter (swecl.c:3270-3271)
        $ds = sqrt($rs[0] * $rs[0] + $rs[1] * $rs[1] + $rs[2] * $rs[2]);

        // Unit vectors (swecl.c:3272-3275)
        $x1 = [$rs[0] / $ds, $rs[1] / $ds, $rs[2] / $ds];
        $x2 = [$rm[0] / $dm, $rm[1] / $dm, $rm[2] / $dm];

        // Angular distance (swecl.c:3276)
        $dotProd = $x1[0] * $x2[0] + $x1[1] * $x2[1] + $x1[2] * $x2[2];
        $dctr = acos($dotProd) * $RADTODEG;

        // Selenocentric sun (swecl.c:3277-3279)
        for ($i = 0; $i <= 2; $i++) {
            $rs[$i] -= $rm[$i];
        }

        // Selenocentric earth (swecl.c:3280-3282)
        for ($i = 0; $i <= 2; $i++) {
            $rm[$i] = -$rm[$i];
        }

        // Sun - earth vector (swecl.c:3283-3285)
        $e = [];
        for ($i = 0; $i <= 2; $i++) {
            $e[$i] = $rm[$i] - $rs[$i];
        }

        // Distance sun - earth (swecl.c:3286-3287)
        $dsm = sqrt($e[0] * $e[0] + $e[1] * $e[1] + $e[2] * $e[2]);

        // Sun - earth unit vector (swecl.c:3288-3290)
        for ($i = 0; $i <= 2; $i++) {
            $e[$i] /= $dsm;
        }

        // Shadow cone angles (swecl.c:3291-3295)
        $f1 = ($RSUN - $REARTH) / $dsm;
        $cosf1 = sqrt(1 - $f1 * $f1);
        $f2 = ($RSUN + $REARTH) / $dsm;
        $cosf2 = sqrt(1 - $f2 * $f2);

        // Distance of earth from fundamental plane (swecl.c:3296-3297)
        $s0 = -($rm[0] * $e[0] + $rm[1] * $e[1] + $rm[2] * $e[2]);

        // Distance of shadow axis from selenocenter (swecl.c:3298-3299)
        $r0 = sqrt($dm * $dm - $s0 * $s0);

        // Diameter of core shadow on fundamental plane (swecl.c:3300-3302)
        // One 50th is added for effect of atmosphere, AA98, L4
        $d0 = abs($s0 / $dsm * ($DSUN - $DEARTH) - $DEARTH) * (1 + 1.0 / 50.0);

        // Diameter of half-shadow on fundamental plane (swecl.c:3304)
        $D0 = ($s0 / $dsm * ($DSUN + $DEARTH) + $DEARTH) * (1 + 1.0 / 50.0);

        // Additional division by cosf1/cosf2 (swecl.c:3305-3306)
        $d0 /= $cosf1;
        $D0 /= $cosf2;

        // For better agreement with NASA (swecl.c:3308-3309)
        $d0 *= 0.99405;
        $D0 *= 0.98813;

        // Store shadow parameters (swecl.c:3309-3313)
        $dcore[0] = $r0;
        $dcore[1] = $d0;
        $dcore[2] = $D0;
        $dcore[3] = $cosf1;
        $dcore[4] = $cosf2;

        // Determine eclipse type and umbral magnitude (swecl.c:3317-3329)
        $retc = 0;
        $rmoon = $RMOON;

        if ($d0 / 2 >= $r0 + $rmoon / $cosf1) {
            // Total eclipse
            $retc = Constants::SE_ECL_TOTAL;
            $attr[0] = ($d0 / 2 - $r0 + $rmoon) / $DMOON;
        } elseif ($d0 / 2 >= $r0 - $rmoon / $cosf1) {
            // Partial eclipse
            $retc = Constants::SE_ECL_PARTIAL;
            $attr[0] = ($d0 / 2 - $r0 + $rmoon) / $DMOON;
        } elseif ($D0 / 2 >= $r0 - $rmoon / $cosf2) {
            // Penumbral eclipse
            $retc = Constants::SE_ECL_PENUMBRAL;
            $attr[0] = 0;
        } else {
            // No eclipse
            $serr = sprintf('no lunar eclipse at tjd = %f', $tjd);
        }

        $attr[8] = $attr[0]; // swecl.c:3330

        // Penumbral magnitude (swecl.c:3334)
        $attr[1] = ($D0 / 2 - $r0 + $rmoon) / $DMOON;

        // Angular distance from opposition (swecl.c:3335-3336)
        if ($retc !== 0) {
            $attr[7] = 180 - abs($dctr);
        }

        // Saros series detection (swecl.c:3337-3360)
        $sarosDataLunar = SarosData::getLunarSarosData();
        $SAROS_CYCLE = 6585.3213;
        $found = false;

        foreach ($sarosDataLunar as $saros) {
            $d = ($tjdUt - $saros['tstart']) / $SAROS_CYCLE;
            if ($d < 0 && $d * $SAROS_CYCLE > -2) {
                $d = 0.0000001;
            }
            if ($d < 0) {
                continue;
            }

            $j = (int)$d;
            if (($d - $j) * $SAROS_CYCLE < 2) {
                $attr[9] = (float)$saros['series_no'];
                $attr[10] = (float)($j + 1);
                $found = true;
                break;
            }

            $k = $j + 1;
            if (($k - $d) * $SAROS_CYCLE < 2) {
                $attr[9] = (float)$saros['series_no'];
                $attr[10] = (float)($k + 1);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $attr[9] = -99999999.0;
            $attr[10] = -99999999.0;
        }

        return $retc;
    }
}
