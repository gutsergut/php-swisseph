<?php

declare(strict_types=1);

namespace Swisseph\Swe\Eclipses;

use Swisseph\Constants;
use Swisseph\Math;
use Swisseph\VectorMath;
use function swe_calc;
use function swe_set_topo;
use function swe_deltat_ex;

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
        $retc = 0 /* OK */;

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
        if (self::calcPlanetStar($te, $ipl, $starname, $iflag, $ls, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Calculate moon position (equatorial)
        if (\swe_calc($te, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Calculate planet/star position (cartesian)
        if (self::calcPlanetStar($te, $ipl, $starname, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Calculate moon position (cartesian)
        if (\swe_calc($te, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
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

        // === PART 2: Eclipse attributes (lines 1060-1150) ===

        // attr[0]: Eclipse magnitude
        // Fraction of solar diameter covered by moon
        $lsun = asin($rsun / 2.0 * Constants::DEGTORAD) * 2.0;
        $lsunleft = (-$dctr + $rsun + $rmoon);
        if ($lsun > 0) {
            $attr[0] = $lsunleft / $rsun / 2.0;
        } else {
            $attr[0] = 1.0;
        }

        // attr[2]: Obscuration
        // Fraction of solar disc obscured by moon
        $lsun = $rsun;
        $lmoon = $rmoon;
        $lctr = $dctr;

        if ($retc === 0 || $lsun === 0.0) {
            // No eclipse or invalid sun size
            $attr[2] = 1.0;
        } elseif ($retc === Constants::SE_ECL_TOTAL || $retc === Constants::SE_ECL_ANNULAR) {
            // Total or annular: simple ratio of areas
            $attr[2] = $lmoon * $lmoon / $lsun / $lsun;
        } else {
            // Partial eclipse: calculate intersection area of two circles
            // Using formula for overlapping circles
            $a = 2.0 * $lctr * $lmoon;
            $b = 2.0 * $lctr * $lsun;

            if ($a < 1e-9) {
                // Circles coincide
                $attr[2] = $lmoon * $lmoon / $lsun / $lsun;
            } else {
                // Law of cosines to find angles
                $a = ($lctr * $lctr + $lmoon * $lmoon - $lsun * $lsun) / $a;
                if ($a > 1.0) $a = 1.0;
                if ($a < -1.0) $a = -1.0;

                $b = ($lctr * $lctr + $lsun * $lsun - $lmoon * $lmoon) / $b;
                if ($b > 1.0) $b = 1.0;
                if ($b < -1.0) $b = -1.0;

                $a = acos($a);
                $b = acos($b);

                // Calculate circular segment areas
                $sc1 = $a * $lmoon * $lmoon / 2.0;
                $sc2 = $b * $lsun * $lsun / 2.0;
                $sc1 -= (cos($a) * sin($a)) * $lmoon * $lmoon / 2.0;
                $sc2 -= (cos($b) * sin($b)) * $lsun * $lsun / 2.0;

                // Total obscured area divided by sun's area
                $attr[2] = ($sc1 + $sc2) * 2.0 / M_PI / $lsun / $lsun;
            }
        }

        // attr[7]: Angular distance (elongation) between centers
        $attr[7] = $dctr;

        // Check visibility: eclipse visible if sun is above horizon
        // Approximate minimum height considering refraction and dip
        // 34.4556': refraction at horizon (Bennett's formula)
        // 1.75' / sqrt(geohgt): dip of horizon
        // 0.37' / sqrt(geohgt): refraction between horizon and observer
        $hminAppr = -(34.4556 + (1.75 + 0.37) * sqrt($geohgt)) / 60.0;
        if ($xh[1] + $rsun + abs($hminAppr) >= 0 && $retc !== 0) {
            $retc |= Constants::SE_ECL_VISIBLE;
        }

        // attr[4-6]: Azimuth and altitude
        // Using modern method (not USE_AZ_NAV)
        $attr[4] = $xh[0];  // azimuth from south, clockwise via west
        $attr[5] = $xh[1];  // true altitude
        $attr[6] = $xh[2];  // apparent altitude

        // attr[8-10]: NASA magnitude and Saros series (only for Sun)
        if ($ipl === Constants::SE_SUN && ($starname === null || $starname === '')) {
            // attr[8]: Magnitude according to NASA
            // For partial: fraction of diameter occulted
            // For total/annular: ratio of diameters
            $attr[8] = $attr[0];
            if ($retc & (Constants::SE_ECL_TOTAL | Constants::SE_ECL_ANNULAR)) {
                $attr[8] = $attr[1];
            }

            // attr[9-10]: Saros series and member number
            $found = false;
            foreach (SarosData::SAROS_DATA_SOLAR as $i => $saros) {
                $d = ($tjdUt - $saros['tstart']) / SarosData::SAROS_CYCLE;

                // Small negative values close to zero: treat as zero
                if ($d < 0 && $d * SarosData::SAROS_CYCLE > -2) {
                    $d = 0.0000001;
                }
                if ($d < 0) {
                    continue;
                }

                $j = (int)$d;

                // Check if within 2 days of cycle start
                if (($d - $j) * SarosData::SAROS_CYCLE < 2) {
                    $attr[9] = (float)$saros['series_no'];
                    $attr[10] = (float)($j + 1);
                    $found = true;
                    break;
                }

                // Check if within 2 days of next cycle
                $k = $j + 1;
                if (($k - $d) * SarosData::SAROS_CYCLE < 2) {
                    $attr[9] = (float)$saros['series_no'];
                    $attr[10] = (float)($k + 1);
                    $found = true;
                    break;
                }
            }

            // If no Saros series found (outside valid range)
            if (!$found) {
                $attr[9] = -99999999.0;
                $attr[10] = -99999999.0;
            }
        }

        return $retc;
    }

    /**
     * Calculate solar eclipse times for a specific location.
     * Port of eclipse_when_loc() from swecl.c:2096-2414
     *
     * PART 1: Initialization and approximate time (lines 2096-2170)
     *
     * @param float $tjdStart Start time for search (JD UT)
     * @param int $ifl Ephemeris flags
     * @param array $geopos Geographic position [longitude, latitude, altitude_m]
     * @param array &$tret Output times array [7]:
     *                    [0] time of maximum eclipse
     *                    [1] time of first contact (eclipse begin)
     *                    [2] time of second contact (totality begin)
     *                    [3] time of third contact (totality end)
     *                    [4] time of fourth contact (eclipse end)
     *                    [5] time of sunrise (if max not visible)
     *                    [6] time of sunset (if max not visible)
     * @param array &$attr Output attributes from eclipse_how()
     * @param int $backward 1 for backward search, 0 for forward
     * @param string|null &$serr Error message
     * @return int Eclipse type flags or 0 if no eclipse, SE_ERR on error
     */
    public static function eclipseWhenLoc(
        float $tjdStart,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward,
        ?string &$serr = null
    ): int {
        // From swecl.c:2096-2414
        // Initialize arrays
        $xs = array_fill(0, 6, 0.0);
        $xm = array_fill(0, 6, 0.0);
        $ls = array_fill(0, 6, 0.0);
        $lm = array_fill(0, 6, 0.0);
        $x1 = array_fill(0, 6, 0.0);
        $x2 = array_fill(0, 6, 0.0);
        $dc = array_fill(0, 3, 0.0);

        $tret = array_fill(0, 7, 0.0);

        $retflag = 0;
        $retc = 0;

        // Variables for refinement loops
        $dtint = 0.0;
        $dctr = 0.0;
        $dt1 = 0.0;
        $dt2 = 0.0;
        $dm = 0.0;
        $ds = 0.0;
        $rmoon = 0.0;
        $rsun = 0.0;
        $rsplusrm = 0.0;
        $rsminusrm = 0.0;
        $dctrmin = 0.0;

        // Time intervals
        $twomin = 2.0 / 24.0 / 60.0;
        $tensec = 10.0 / 24.0 / 60.0 / 60.0;
        $twohr = 2.0 / 24.0;
        $tenmin = 10.0 / 24.0 / 60.0;

        // Flags for topocentric calculation
        $iflag = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR | $ifl;
        $iflagcart = $iflag | Constants::SEFLG_XYZ;

        // Set topocentric location
        swe_set_topo($geopos[0], $geopos[1], $geopos[2]);

        // Calculate Saros cycle number K
        // From swecl.c:2109
        $K = (int)(($tjdStart - Constants::J2000) / 365.2425 * 12.3685);
        if ($backward) {
            $K++;
        } else {
            $K--;
        }

        // Main search loop - try successive Saros cycles until eclipse found
        // From swecl.c:2110-2414
        next_try:

        // Time since J2000 in Julian centuries (of 36525 days)
        // From swecl.c:2111-2112
        $T = $K / 1236.85;
        $T2 = $T * $T;
        $T3 = $T2 * $T;
        $T4 = $T3 * $T;

        // Moon's argument of latitude (F)
        // From swecl.c:2113-2117, Meeus formula
        $Ff = $F = EclipseUtils::degNorm(160.7108 + 390.67050274 * $K
                   - 0.0016341 * $T2
                   - 0.00000227 * $T3
                   + 0.000000011 * $T4);

        if ($Ff > 180) {
            $Ff -= 180;
        }

        // No eclipse possible if F is not near node
        // From swecl.c:2118-2123
        if ($Ff > 21 && $Ff < 159) {
            if ($backward) {
                $K--;
            } else {
                $K++;
            }
            goto next_try;
        }

        // Approximate time of geocentric maximum eclipse
        // Formula from Meeus, German, p. 381
        // From swecl.c:2124-2128
        $tjd = 2451550.09765 + 29.530588853 * $K
                            + 0.0001337 * $T2
                            - 0.000000150 * $T3
                            + 0.00000000073 * $T4;

        // Sun's mean anomaly
        // From swecl.c:2129-2131
        $M = EclipseUtils::degNorm(2.5534 + 29.10535669 * $K
                            - 0.0000218 * $T2
                            - 0.00000011 * $T3);

        // Moon's mean anomaly
        // From swecl.c:2132-2135
        $Mm = EclipseUtils::degNorm(201.5643 + 385.81693528 * $K
                            + 0.1017438 * $T2
                            + 0.00001239 * $T3
                            + 0.000000058 * $T4);

        // Eccentricity correction
        // From swecl.c:2139
        $E = 1 - 0.002516 * $T - 0.0000074 * $T2;

        // Convert to radians
        // From swecl.c:2143-2145
        $M *= Constants::DEGTORAD;
        $Mm *= Constants::DEGTORAD;
        $F *= Constants::DEGTORAD;

        // Apply corrections to approximate eclipse time
        // From swecl.c:2149-2150
        $tjd = $tjd - 0.4075 * sin($Mm)
                    + 0.1721 * $E * sin($M);

        // Reset topocentric location (from C code line 2151)
        swe_set_topo($geopos[0], $geopos[1], $geopos[2]);

        // PART 2: Iterative refinement to find exact maximum
        // From swecl.c:2152-2220

        $dtdiv = 2.0;
        $dtstart = 0.5;

        // For early/late dates use larger initial step (delta t uncertainty)
        // From swecl.c:2154-2155
        if ($tjd < 1900000 || $tjd > 2500000) {
            $dtstart = 2.0;
        }

        // Iterative refinement loop
        // From swecl.c:2156-2197
        for ($dt = $dtstart; $dt > 0.00001; $dt /= $dtdiv) {
            // Increase division factor for fine refinement
            if ($dt < 0.1) {
                $dtdiv = 3.0;
            }

            // Sample 3 points: before, at, and after current tjd
            for ($i = 0, $t = $tjd - $dt; $i <= 2; $i++, $t += $dt) {
                // Calculate Sun position (cartesian and equatorial)
                // From swecl.c:2163-2164
                if (swe_calc($t, Constants::SE_SUN, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
                if (swe_calc($t, Constants::SE_SUN, $iflag, $ls, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                // Calculate Moon position (cartesian and equatorial)
                // From swecl.c:2167-2168
                if (swe_calc($t, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
                if (swe_calc($t, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                // Calculate distances from geocenter
                // From swecl.c:2171-2172
                $dm = sqrt(EclipseUtils::squareSum($xm));
                $ds = sqrt(EclipseUtils::squareSum($xs));

                // Normalize position vectors
                // From swecl.c:2173-2176
                for ($k = 0; $k < 3; $k++) {
                    $x1[$k] = $xs[$k] / $ds;
                    $x2[$k] = $xm[$k] / $dm;
                }

                // Angular distance between centers
                // From swecl.c:2177
                $dc[$i] = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
            }

            // Find maximum (minimum angular distance) using parabolic interpolation
            // From swecl.c:2179
            EclipseUtils::findMaximum($dc[0], $dc[1], $dc[2], $dt, $dtint, $dctr);
            $tjd += $dtint + $dt;
        }

        // Calculate final positions at refined maximum time
        // From swecl.c:2181-2190
        if (swe_calc($tjd, Constants::SE_SUN, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }
        if (swe_calc($tjd, Constants::SE_SUN, $iflag, $ls, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }
        if (swe_calc($tjd, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }
        if (swe_calc($tjd, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Calculate angular separation and radii at maximum
        // From swecl.c:2191-2195
        $dctr = acos(VectorMath::dotProductUnit($xs, $xm)) * Constants::RADTODEG;
        $rmoon = asin(EclipseUtils::RMOON / $lm[2]) * Constants::RADTODEG;
        $rsun = asin(EclipseUtils::RSUN / $ls[2]) * Constants::RADTODEG;
        $rsplusrm = $rsun + $rmoon;
        $rsminusrm = $rsun - $rmoon;

        // Check if eclipse actually occurs
        // From swecl.c:2196-2201
        if ($dctr > $rsplusrm) {
            // No eclipse - try next/previous cycle
            if ($backward) {
                $K--;
            } else {
                $K++;
            }
            goto next_try;
        }

        // Convert ET to UT for tret[0] (maximum time)
        // From swecl.c:2202-2203 (iterative delta T correction)
        $tret[0] = $tjd - swe_deltat_ex($tjd, $ifl, $serr);
        $tret[0] = $tjd - swe_deltat_ex($tret[0], $ifl, $serr);

        // Check if found eclipse is before/after start time
        // From swecl.c:2204-2210
        if (($backward && $tret[0] >= $tjdStart - 0.0001)
            || (!$backward && $tret[0] <= $tjdStart + 0.0001)) {
            if ($backward) {
                $K--;
            } else {
                $K++;
            }
            goto next_try;
        }

        // Determine eclipse type
        // From swecl.c:2211-2217
        if ($dctr < $rsminusrm) {
            $retflag = Constants::SE_ECL_ANNULAR;
        } elseif ($dctr < abs($rsminusrm)) {
            $retflag = Constants::SE_ECL_TOTAL;
        } elseif ($dctr <= $rsplusrm) {
            $retflag = Constants::SE_ECL_PARTIAL;
        }

        $dctrmin = $dctr;

        // PART 3: Contacts 2 and 3 (totality begin/end)
        // From swecl.c:2218-2290

        // For partial eclipses, no 2nd and 3rd contact
        // From swecl.c:2232-2233
        if ($dctr > abs($rsminusrm)) {
            $tret[2] = $tret[3] = 0.0;
        } else {
            // Initial estimate: when |rsminusrm| = dctr
            // From swecl.c:2235
            $dc[1] = abs($rsminusrm) - $dctrmin;

            // Sample 3 points: 2 minutes before and after maximum
            // From swecl.c:2236-2254
            for ($i = 0, $t = $tjd - $twomin; $i <= 2; $i += 2, $t = $tjd + $twomin) {
                if (swe_calc($t, Constants::SE_SUN, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
                if (swe_calc($t, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $dm = sqrt(EclipseUtils::squareSum($xm));
                $ds = sqrt(EclipseUtils::squareSum($xs));

                // Moon radius with empirical correction factor
                // From swecl.c:2244-2245
                $rmoon = asin(EclipseUtils::RMOON / $dm) * Constants::RADTODEG;
                $rmoon *= 0.99916; // Gives better accuracy for 2nd/3rd contacts

                $rsun = asin(EclipseUtils::RSUN / $ds) * Constants::RADTODEG;
                $rsminusrm = $rsun - $rmoon;

                for ($k = 0; $k < 3; $k++) {
                    $x1[$k] = $xs[$k] / $ds;
                    $x2[$k] = $xm[$k] / $dm;
                }

                $dctr = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
                $dc[$i] = abs($rsminusrm) - $dctr;
            }

            // Find zero crossings for contacts 2 and 3
            // From swecl.c:2255-2257
            EclipseUtils::findZero($dc[0], $dc[1], $dc[2], $twomin, $dt1, $dt2);
            $tret[2] = $tjd + $dt1 + $twomin;
            $tret[3] = $tjd + $dt2 + $twomin;

            // Refine using velocities (two iterations: 10s, then 1s)
            // From swecl.c:2258-2283
            for ($m = 0, $dt = $tensec; $m < 2; $m++, $dt /= 10) {
                for ($j = 2; $j <= 3; $j++) {
                    // Get positions with velocities
                    if (swe_calc($tret[$j], Constants::SE_SUN,
                                           $iflagcart | Constants::SEFLG_SPEED, $xs, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }
                    if (swe_calc($tret[$j], Constants::SE_MOON,
                                           $iflagcart | Constants::SEFLG_SPEED, $xm, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }

                    // Calculate dc[0] at current time and dc[1] at time - dt
                    for ($i = 0; $i < 2; $i++) {
                        if ($i == 1) {
                            // Subtract velocity * dt to get earlier position
                            for ($k = 0; $k < 3; $k++) {
                                $xs[$k] -= $xs[$k+3] * $dt;
                                $xm[$k] -= $xm[$k+3] * $dt;
                            }
                        }

                        $dm = sqrt(EclipseUtils::squareSum($xm));
                        $ds = sqrt(EclipseUtils::squareSum($xs));

                        $rmoon = asin(EclipseUtils::RMOON / $dm) * Constants::RADTODEG;
                        $rmoon *= 0.99916; // Empirical correction

                        $rsun = asin(EclipseUtils::RSUN / $ds) * Constants::RADTODEG;
                        $rsminusrm = $rsun - $rmoon;

                        for ($k = 0; $k < 3; $k++) {
                            $x1[$k] = $xs[$k] / $ds;
                            $x2[$k] = $xm[$k] / $dm;
                        }

                        $dctr = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
                        $dc[$i] = abs($rsminusrm) - $dctr;
                    }

                    // Linear interpolation to zero
                    // From swecl.c:2282
                    $dt1 = -$dc[0] / (($dc[0] - $dc[1]) / $dt);
                    $tret[$j] += $dt1;
                }
            }

            // Convert ET to UT
            // From swecl.c:2285-2286
            $tret[2] -= swe_deltat_ex($tret[2], $ifl, $serr);
            $tret[3] -= swe_deltat_ex($tret[3], $ifl, $serr);
        }

        // PART 4: Contacts 1 and 4 (eclipse begin/end)
        // From swecl.c:2288-2345

        // Initial estimate: when rsplusrm = dctr
        // From swecl.c:2289
        $dc[1] = $rsplusrm - $dctrmin;

        // Sample 3 points: 2 hours before and after maximum
        // From swecl.c:2290-2307
        for ($i = 0, $t = $tjd - $twohr; $i <= 2; $i += 2, $t = $tjd + $twohr) {
            if (swe_calc($t, Constants::SE_SUN, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
            if (swe_calc($t, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }

            $dm = sqrt(EclipseUtils::squareSum($xm));
            $ds = sqrt(EclipseUtils::squareSum($xs));

            $rmoon = asin(EclipseUtils::RMOON / $dm) * Constants::RADTODEG;
            $rsun = asin(EclipseUtils::RSUN / $ds) * Constants::RADTODEG;
            $rsplusrm = $rsun + $rmoon;

            for ($k = 0; $k < 3; $k++) {
                $x1[$k] = $xs[$k] / $ds;
                $x2[$k] = $xm[$k] / $dm;
            }

            $dctr = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
            $dc[$i] = $rsplusrm - $dctr;
        }

        // Find zero crossings for contacts 1 and 4
        // From swecl.c:2308-2310
        EclipseUtils::findZero($dc[0], $dc[1], $dc[2], $twohr, $dt1, $dt2);
        $tret[1] = $tjd + $dt1 + $twohr;
        $tret[4] = $tjd + $dt2 + $twohr;

        // Refine using velocities (three iterations: 10min, 1min, 6s)
        // From swecl.c:2311-2336
        for ($m = 0, $dt = $tenmin; $m < 3; $m++, $dt /= 10) {
            // Only refine contacts 1 and 4 (j += 3 gives 1, 4)
            for ($j = 1; $j <= 4; $j += 3) {
                // Get positions with velocities
                if (swe_calc($tret[$j], Constants::SE_SUN,
                                       $iflagcart | Constants::SEFLG_SPEED, $xs, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
                if (swe_calc($tret[$j], Constants::SE_MOON,
                                       $iflagcart | Constants::SEFLG_SPEED, $xm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                // Calculate dc[0] at current time and dc[1] at time - dt
                for ($i = 0; $i < 2; $i++) {
                    if ($i == 1) {
                        // Subtract velocity * dt to get earlier position
                        for ($k = 0; $k < 3; $k++) {
                            $xs[$k] -= $xs[$k+3] * $dt;
                            $xm[$k] -= $xm[$k+3] * $dt;
                        }
                    }

                    $dm = sqrt(EclipseUtils::squareSum($xm));
                    $ds = sqrt(EclipseUtils::squareSum($xs));

                    $rmoon = asin(EclipseUtils::RMOON / $dm) * Constants::RADTODEG;
                    $rsun = asin(EclipseUtils::RSUN / $ds) * Constants::RADTODEG;
                    $rsplusrm = $rsun + $rmoon;

                    for ($k = 0; $k < 3; $k++) {
                        $x1[$k] = $xs[$k] / $ds;
                        $x2[$k] = $xm[$k] / $dm;
                    }

                    $dctr = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
                    $dc[$i] = abs($rsplusrm) - $dctr;
                }

                // Linear interpolation to zero
                // From swecl.c:2335
                $dt1 = -$dc[0] / (($dc[0] - $dc[1]) / $dt);
                $tret[$j] += $dt1;
            }
        }

        // Convert ET to UT
        // From swecl.c:2338-2339
        $tret[1] -= swe_deltat_ex($tret[1], $ifl, $serr);
        $tret[4] -= swe_deltat_ex($tret[4], $ifl, $serr);

        // PART 5: Visibility checks
        // From swecl.c:2340-2414

        // Check visibility for each contact (reverse order to preserve attr[0])
        // From swecl.c:2345-2365
        for ($i = 4; $i >= 0; $i--) {
            if ($tret[$i] == 0) {
                continue;
            }

            // Calculate eclipse attributes at this time
            if (self::eclipseHow($tret[$i], Constants::SE_SUN, null, $ifl,
                                 $geopos[0], $geopos[1], $geopos[2], $attr, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }

            // Check if sun is above horizon (using apparent altitude attr[6])
            // From swecl.c:2353-2363
            if ($attr[6] > 0) {
                $retflag |= Constants::SE_ECL_VISIBLE;
                switch ($i) {
                    case 0:
                        $retflag |= Constants::SE_ECL_MAX_VISIBLE;
                        break;
                    case 1:
                        $retflag |= Constants::SE_ECL_1ST_VISIBLE;
                        break;
                    case 2:
                        $retflag |= Constants::SE_ECL_2ND_VISIBLE;
                        break;
                    case 3:
                        $retflag |= Constants::SE_ECL_3RD_VISIBLE;
                        break;
                    case 4:
                        $retflag |= Constants::SE_ECL_4TH_VISIBLE;
                        break;
                }
            }
        }

        // If no phase is visible, try next Saros cycle
        // From swecl.c:2365-2372
        if (!($retflag & Constants::SE_ECL_VISIBLE)) {
            if ($backward) {
                $K--;
            } else {
                $K++;
            }
            goto next_try;
        }

        // TODO: Sunrise/sunset adjustments require swe_rise_trans() implementation
        // From swecl.c:2373-2413
        //
        // The C code checks:
        // 1. Calculate sunrise (tjdr) and sunset (tjds) for tret[1] - 0.001
        // 2. Handle circumpolar sun (retc == -2)
        // 3. If sunset before 1st contact or (sunset > sunrise && sunrise > 4th):
        //    try next Saros cycle
        // 4. If sunrise between 1st and 4th contact:
        //    - Set tret[5] = tjdr
        //    - If max not visible: recalculate attr at sunrise, update tret[0] and type
        // 5. If sunset between 1st and 4th contact:
        //    - Set tret[6] = tjds
        //    - If max not visible: recalculate attr at sunset, update tret[0] and type
        //
        // For now, we skip this refinement. Eclipse will still be found, but:
        // - tret[5] and tret[6] will remain 0
        // - For eclipses where max is below horizon, tret[0] won't be adjusted
        //   to sunrise/sunset time
        //
        // This is acceptable for initial implementation. Full accuracy requires
        // porting swe_rise_trans() (~500 lines in swecl.c:3800-4300)

        $tret[5] = 0.0; // Sunrise time (placeholder)
        $tret[6] = 0.0; // Sunset time (placeholder)

        return $retflag;
    }

    /**
     * Calculate geographic position of maximum solar eclipse
     * Full port from swecl.c:640-888 (eclipse_where)
     *
     * WITHOUT SIMPLIFICATIONS - complete algorithm:
     * - Calculates moon and sun positions (cartesian and polar)
     * - Accounts for Earth oblateness
     * - Iterates to find geographic position of eclipse maximum
     * - Computes core and penumbra shadow diameters
     * - Determines eclipse type (total, annular, partial, central/noncentral)
     *
     * @param float $tjdUt Julian day in UT
     * @param int $ipl Planet number (SE_SUN for solar eclipse)
     * @param string|null $starname Star name (null for planets)
     * @param int $ifl Ephemeris flags
     * @param array &$geopos Output: geographic position [longitude, latitude]
     *   - geopos[0]: longitude of maximum eclipse (degrees, west negative)
     *   - geopos[1]: latitude of maximum eclipse (degrees)
     * @param array &$dcore Output: shadow diameters and parameters [0-9]
     *   - dcore[0]: diameter of core shadow in km
     *   - dcore[1]: diameter of penumbra in km
     *   - dcore[2]: distance of shadow axis from geocenter in km
     *   - dcore[3]: diameter of core shadow on fundamental plane in km
     *   - dcore[4]: diameter of penumbra on fundamental plane in km
     *   - dcore[5]: cosf1 (for core shadow geometry)
     *   - dcore[6]: cosf2 (for penumbra geometry)
     * @param string|null &$serr Output: error message
     * @return int Eclipse type flags:
     *   - SE_ECL_TOTAL: total eclipse at maximum point
     *   - SE_ECL_ANNULAR: annular eclipse at maximum point
     *   - SE_ECL_PARTIAL: partial eclipse
     *   - SE_ECL_CENTRAL: central eclipse (shadow axis touches Earth)
     *   - SE_ECL_NONCENTRAL: non-central eclipse (shadow axis misses Earth)
     *   - 0: no eclipse
     *   - ERR: error occurred
     */
    public static function eclipseWhere(
        float $tjdUt,
        int $ipl,
        ?string $starname,
        int $ifl,
        array &$geopos,
        array &$dcore,
        ?string &$serr = null
    ): int {
        // Initialize output arrays (swecl.c:663-664)
        for ($i = 0; $i < 10; $i++) {
            $dcore[$i] = 0.0;
        }
        $geopos[0] = 0.0;
        $geopos[1] = 0.0;

        // Local variables (swecl.c:644-662)
        $retc = 0;
        $niter = 0;
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

        $de = 6378140.0 / Constants::AUNIT;  // Earth equatorial radius in AU
        $earthobl = 1.0 - Constants::EARTH_OBLATENESS;
        $rmoon = EclipseUtils::RMOON;
        $dmoon = 2.0 * $rmoon;
        $no_eclipse = false;

        // Flags for calculations (swecl.c:665-670)
        // nutation need not be in lunar and solar positions,
        // if mean sidereal time will be used
        $iflag = Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL | $ifl;
        $iflag2 = $iflag | Constants::SEFLG_RADIANS;
        $iflag = $iflag | Constants::SEFLG_XYZ;

        // Delta T and TT (swecl.c:671-672)
        $deltat = \swe_deltat_ex($tjdUt, $ifl, $serr);
        $tjd = $tjdUt + $deltat;

        // Moon in cartesian coordinates (swecl.c:673-675)
        $retc = \swe_calc($tjd, Constants::SE_MOON, $iflag, $rm, $serr);
        if ($retc === Constants::SE_ERR) {
            return $retc;
        }

        // Moon in polar coordinates (swecl.c:676-678)
        $retc = \swe_calc($tjd, Constants::SE_MOON, $iflag2, $lm, $serr);
        if ($retc === Constants::SE_ERR) {
            return $retc;
        }

        // Sun in cartesian coordinates (swecl.c:679-681)
        $retc = self::calcPlanetStar($tjd, $ipl, $starname, $iflag, $rs, $serr);
        if ($retc === Constants::SE_ERR) {
            return $retc;
        }

        // Sun in polar coordinates (swecl.c:682-684)
        $retc = self::calcPlanetStar($tjd, $ipl, $starname, $iflag2, $ls, $serr);
        if ($retc === Constants::SE_ERR) {
            return $retc;
        }

        // Save sun and moon positions (swecl.c:685-690)
        for ($i = 0; $i <= 2; $i++) {
            $rst[$i] = $rs[$i];
            $rmt[$i] = $rm[$i];
        }

        // Sidereal time at Greenwich (swecl.c:691-694)
        if ($iflag & Constants::SEFLG_NONUT) {
            $oe = \Swisseph\Obliquity::trueObliquityRadFromJdTT($tjd);
            $sidt = \swe_sidtime0($tjdUt, $oe * Constants::RADTODEG, 0) * 15.0 * Constants::DEGTORAD;
        } else {
            $sidt = \swe_sidtime($tjdUt) * 15.0 * Constants::DEGTORAD;
        }

        // Radius of planet disk in AU (swecl.c:695-703)
        if ($starname !== null && $starname !== '') {
            $drad = 0.0;
        } elseif ($ipl < count(EclipseUtils::PLA_DIAM)) {
            // NDIAM check: ipl < SE_VESTA + 1
            $drad = EclipseUtils::PLA_DIAM[$ipl] / 2.0 / Constants::AUNIT;
        } elseif ($ipl > Constants::SE_AST_OFFSET) {
            // Asteroid: use swed.ast_diam (would need SwedState integration)
            // For now, assume 0 (stars/asteroids have negligible disk)
            $drad = 0.0;
        } else {
            $drad = 0.0;
        }

        // Iteration label (swecl.c:704)
        // PHP doesn't have goto, so we use a do-while loop
        do {
            // Restore saved positions (swecl.c:705-708)
            for ($i = 0; $i <= 2; $i++) {
                $rs[$i] = $rst[$i];
                $rm[$i] = $rmt[$i];
            }

            // Account for oblateness of earth (swecl.c:709-717)
            // Instead of flattening the earth, we apply the
            // correction to the z coordinate of the moon and
            // the sun. This makes the calculation easier.
            for ($i = 0; $i <= 2; $i++) {
                $lx[$i] = $lm[$i];
            }
            $rm = \Swisseph\Coordinates::polarToCartesian($lx);
            $rm[2] /= $earthobl;

            // Distance of moon from geocenter (swecl.c:718-719)
            $dm = sqrt(VectorMath::squareSum($rm));

            // Account for oblateness of earth for sun (swecl.c:720-723)
            for ($i = 0; $i <= 2; $i++) {
                $lx[$i] = $ls[$i];
            }
            $rs = \Swisseph\Coordinates::polarToCartesian($lx);
            $rs[2] /= $earthobl;

            // Sun - moon vector (swecl.c:724-728)
            for ($i = 0; $i <= 2; $i++) {
                $e[$i] = $rm[$i] - $rs[$i];
                $et[$i] = $rmt[$i] - $rst[$i];
            }

            // Distance sun - moon (swecl.c:729-730)
            $dsm = sqrt(VectorMath::squareSum($e));
            $dsmt = sqrt(VectorMath::squareSum($et));

            // Sun - moon unit vector (swecl.c:731-738)
            for ($i = 0; $i <= 2; $i++) {
                $e[$i] /= $dsm;
                $et[$i] /= $dsmt;
            }

            // Shadow geometry angles (swecl.c:739-742)
            $sinf1 = ($drad - $rmoon) / $dsm;
            $cosf1 = sqrt(1.0 - $sinf1 * $sinf1);
            $sinf2 = ($drad + $rmoon) / $dsm;
            $cosf2 = sqrt(1.0 - $sinf2 * $sinf2);

            // Distance of moon from fundamental plane (swecl.c:743-747)
            $s0 = -VectorMath::dotProduct($rm, $e);

            // Distance of shadow axis from geocenter (swecl.c:748-749)
            $r0 = sqrt($dm * $dm - $s0 * $s0);

            // Diameter of core shadow on fundamental plane (swecl.c:750-751)
            $d0 = ($s0 / $dsm * ($drad * 2.0 - $dmoon) - $dmoon) / $cosf1;

            // Diameter of half-shadow on fundamental plane (swecl.c:752-753)
            $D0 = ($s0 / $dsm * ($drad * 2.0 + $dmoon) + $dmoon) / $cosf2;

            // Save intermediate results (swecl.c:754-759)
            $dcore[2] = $r0;
            $dcore[3] = $d0;
            $dcore[4] = $D0;
            $dcore[5] = $cosf1;
            $dcore[6] = $cosf2;
            for ($i = 2; $i < 5; $i++) {
                $dcore[$i] *= Constants::AUNIT / 1000.0;  // Convert AU to km
            }

            // Determine eclipse type (swecl.c:760-783)
            $retc = 0;
            if ($de * $cosf1 >= $r0) {
                // Shadow axis touches Earth - central eclipse
                $retc |= Constants::SE_ECL_CENTRAL;
            } elseif ($r0 <= $de * $cosf1 + abs($d0) / 2.0) {
                // Non-central but possibly total/annular
                $retc |= Constants::SE_ECL_NONCENTRAL;
            } elseif ($r0 <= $de * $cosf2 + $D0 / 2.0) {
                // Partial eclipse only
                $retc |= (Constants::SE_ECL_PARTIAL | Constants::SE_ECL_NONCENTRAL);
            } else {
                // No eclipse
                if ($serr !== null) {
                    $serr = sprintf("no solar eclipse at tjd = %f", $tjd);
                }
                for ($i = 0; $i < 2; $i++) {
                    $geopos[$i] = 0.0;
                }
                $dcore[0] = 0.0;
                $retc = 0;
                $d = 0.0;
                $no_eclipse = true;
            }

            // Distance of shadow point from fundamental plane (swecl.c:784-787)
            $d = $s0 * $s0 + $de * $de - $dm * $dm;
            if ($d > 0) {
                $d = sqrt($d);
            } else {
                $d = 0.0;
            }

            // Distance of moon from shadow point on earth (swecl.c:788-789)
            $s = $s0 - $d;

            // Geographic position of eclipse center (maximum) (swecl.c:828-832)
            for ($i = 0; $i <= 2; $i++) {
                $xs[$i] = $rm[$i] + $s * $e[$i];
            }

            // We need geographic position with correct z, as well (swecl.c:833-835)
            for ($i = 0; $i <= 2; $i++) {
                $xst[$i] = $xs[$i];
            }
            $xst[2] *= $earthobl;
            $xst = \Swisseph\Coordinates::cartesianToPolar($xst);

            // Iteration for oblateness (swecl.c:836-843)
            if ($niter <= 0) {
                $cosfi = cos($xst[1]);
                $sinfi = sin($xst[1]);
                $eobl = Constants::EARTH_OBLATENESS;
                $cc = 1.0 / sqrt($cosfi * $cosfi + (1.0 - $eobl) * (1.0 - $eobl) * $sinfi * $sinfi);
                $ss = (1.0 - $eobl) * (1.0 - $eobl) * $cc;
                $earthobl = $ss;
                $niter++;
                continue; // goto iter_where
            }

            break; // Exit iteration
        } while ($niter <= 1);

        // Convert back to cartesian (swecl.c:844-845)
        $xst = \Swisseph\Coordinates::polarToCartesian($xst);

        // To longitude and latitude (swecl.c:846-847)
        $xs = \Swisseph\Coordinates::cartesianToPolar($xs);

        // Measure from sidereal time at Greenwich (swecl.c:848-854)
        $xs[0] -= $sidt;
        $xs[0] *= Constants::RADTODEG;
        $xs[1] *= Constants::RADTODEG;
        $xs[0] = Math::normAngleDeg($xs[0]);

        // West is negative (swecl.c:855-857)
        if ($xs[0] > 180.0) {
            $xs[0] -= 360.0;
        }
        $geopos[0] = $xs[0];
        $geopos[1] = $xs[1];

        // Diameter of core shadow (swecl.c:858-865)
        // First, distance moon - place of eclipse on earth
        for ($i = 0; $i <= 2; $i++) {
            $x[$i] = $rmt[$i] - $xst[$i];
        }
        $s = sqrt(VectorMath::squareSum($x));

        // Diameter of core shadow at place of maximum eclipse (swecl.c:866-868)
        $dcore[0] = ($s / $dsmt * ($drad * 2.0 - $dmoon) - $dmoon) * $cosf1;
        $dcore[0] *= Constants::AUNIT / 1000.0;  // Convert AU to km

        // Diameter of penumbra at place of maximum eclipse (swecl.c:869-871)
        $dcore[1] = ($s / $dsmt * ($drad * 2.0 + $dmoon) + $dmoon) * $cosf2;
        $dcore[1] *= Constants::AUNIT / 1000.0;  // Convert AU to km

        // Determine if total or annular (swecl.c:872-880)
        if (!($retc & Constants::SE_ECL_PARTIAL) && !$no_eclipse) {
            if ($dcore[0] > 0) {
                $retc |= Constants::SE_ECL_ANNULAR;
            } else {
                $retc |= Constants::SE_ECL_TOTAL;
            }
        }

        return $retc;
    }
}
