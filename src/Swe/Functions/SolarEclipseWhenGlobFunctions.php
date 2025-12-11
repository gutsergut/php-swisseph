<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\VectorMath;
use Swisseph\Swe\Eclipses\EclipseCalculator;

/**
 * Solar Eclipse Global Search Functions
 *
 * Port from swecl.c:1185-1570 (swe_sol_eclipse_when_glob)
 * NO SIMPLIFICATIONS - Complete algorithm implementation
 *
 * Algorithm:
 * - Uses Meeus formula to find approximate new moon times
 * - Iteratively refines to find minimum Sun-Moon angular distance
 * - Calls eclipse_where() to verify eclipse and get type
 * - Calculates begin/end times for eclipse phases
 * - Determines if eclipse is annular-total
 * - Finds time of local apparent noon during eclipse
 */
class SolarEclipseWhenGlobFunctions
{
    // Moon radius in AU (swecl.c:85)
    private const RMOON = Constants::DMOON / 2.0 / Constants::AUNIT;

    // Sun radius in AU (swecl.c:79)
    private const RSUN = Constants::DSUN / 2.0 / Constants::AUNIT;

    /**
     * Find next solar eclipse anywhere on Earth
     *
     * Port from swecl.c:1185-1570
     * NO SIMPLIFICATIONS - Full algorithm
     *
     * @param float $tjdStart Starting Julian day (UT) for search
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, etc)
     * @param int $ifltype Eclipse type filter (SE_ECL_TOTAL, etc), 0 = any type
     * @param array &$tret Times array [10] (output):
     *   [0] = time of maximum eclipse
     *   [1] = time of eclipse at local apparent noon
     *   [2] = time of eclipse begin
     *   [3] = time of eclipse end
     *   [4] = time of totality begin
     *   [5] = time of totality end
     *   [6] = time of center line begin
     *   [7] = time of center line end
     *   [8] = time when annular-total becomes total (not implemented)
     *   [9] = time when annular-total becomes annular (not implemented)
     * @param int $backward Search direction: 0=forward, 1=backward
     * @param string &$serr Error message (output)
     * @return int Eclipse type flags (SE_ECL_TOTAL|ANNULAR|PARTIAL|CENTRAL|NONCENTRAL|ANNULAR_TOTAL)
     */
    public static function sweEclipseWhenGlob(
        float $tjdStart,
        int $ifl,
        int $ifltype,
        array &$tret,
        int $backward,
        string &$serr
    ): int {
        // Earth radius in km (swecl.c:1191)
        $de = 6378.140;

        // Initialize variables (swecl.c:1192-1204)
        $retflag = 0;
        $retflag2 = 0;
        $direction = $backward ? -1 : 1;
        $dontTimes = false;

        // Time intervals (swecl.c:1200-1201)
        $twohr = 2.0 / 24.0;
        $tenmin = 10.0 / 24.0 / 60.0;

        // Strip ephemeris mask (swecl.c:1205)
        $ifl &= Constants::SEFLG_EPHMASK;

        // Set tidal acceleration (swecl.c:1206)
        \swe_set_tid_acc($tjdStart);

        // Setup flags (swecl.c:1207-1208)
        $iflag = Constants::SEFLG_EQUATORIAL | $ifl;
        $iflagCart = $iflag | Constants::SEFLG_XYZ;

        // Validate eclipse type combinations (swecl.c:1209-1218)
        if ($ifltype === (Constants::SE_ECL_PARTIAL | Constants::SE_ECL_CENTRAL)) {
            $serr = "central partial eclipses do not exist";
            return Constants::SE_ERR;
        }
        if ($ifltype === (Constants::SE_ECL_ANNULAR_TOTAL | Constants::SE_ECL_NONCENTRAL)) {
            $serr = "non-central hybrid (annular-total) eclipses do not exist";
            return Constants::SE_ERR;
        }

        // Default: search for all types (swecl.c:1219-1220)
        if ($ifltype === 0) {
            $ifltype = Constants::SE_ECL_TOTAL | Constants::SE_ECL_ANNULAR | Constants::SE_ECL_PARTIAL
                     | Constants::SE_ECL_ANNULAR_TOTAL | Constants::SE_ECL_NONCENTRAL | Constants::SE_ECL_CENTRAL;
        }

        // Expand type flags (swecl.c:1221-1224)
        if ($ifltype === Constants::SE_ECL_TOTAL ||
            $ifltype === Constants::SE_ECL_ANNULAR ||
            $ifltype === Constants::SE_ECL_ANNULAR_TOTAL) {
            $ifltype |= (Constants::SE_ECL_NONCENTRAL | Constants::SE_ECL_CENTRAL);
        }
        if ($ifltype === Constants::SE_ECL_PARTIAL) {
            $ifltype |= Constants::SE_ECL_NONCENTRAL;
        }

        // Initial K value for lunation series (swecl.c:1227)
        $K = (int)(($tjdStart - Constants::J2000) / 365.2425 * 12.3685);
        $K -= $direction;

        // Safety limit: max ~100 years of lunations (1236 per century)
        $maxIterations = 1236;
        $iteration = 0;

        // Main search loop (swecl.c:1228)
        while (true) {
            if (++$iteration > $maxIterations) {
                $serr = "No eclipse found within search range (~100 years)";
                return Constants::SE_ERR;
            }

            $retflag = 0;
            $dontTimes = false;

            // Initialize tret array (swecl.c:1230-1231)
            for ($i = 0; $i <= 9; $i++) {
                $tret[$i] = 0.0;
            }

            // Calculate T and powers (swecl.c:1232-1233)
            $T = $K / 1236.85;
            $T2 = $T * $T;
            $T3 = $T2 * $T;
            $T4 = $T3 * $T;

            // Calculate F (argument of latitude) (swecl.c:1234-1238)
            $Ff = \swe_degnorm(160.7108 + 390.67050274 * $K
                             - 0.0016341 * $T2
                             - 0.00000227 * $T3
                             + 0.000000011 * $T4);
            if ($Ff > 180.0) {
                $Ff -= 180.0;
            }

            // Check if eclipse is possible (swecl.c:1241-1244)
            if ($Ff > 21.0 && $Ff < 159.0) {
                // No eclipse possible
                $K += $direction;
                continue; // goto next_try
            }

            // Approximate time of geocentric maximum eclipse (Meeus formula) (swecl.c:1245-1252)
            $tjd = 2451550.09765 + 29.530588853 * $K
                                 + 0.0001337 * $T2
                                 - 0.000000150 * $T3
                                 + 0.00000000073 * $T4;

            // Mean anomaly of Sun (swecl.c:1253-1256)
            $M = \swe_degnorm(2.5534 + 29.10535669 * $K
                                    - 0.0000218 * $T2
                                    - 0.00000011 * $T3);

            // Mean anomaly of Moon (swecl.c:1257-1261)
            $Mm = \swe_degnorm(201.5643 + 385.81693528 * $K
                                       + 0.1017438 * $T2
                                       + 0.00001239 * $T3
                                       + 0.000000058 * $T4);

            // Eccentricity correction (swecl.c:1262)
            $E = 1.0 - 0.002516 * $T - 0.0000074 * $T2;

            // Convert to radians (swecl.c:1263-1264)
            $M *= Constants::DEGTORAD;
            $Mm *= Constants::DEGTORAD;

            // Correction to time (swecl.c:1265-1266)
            $tjd = $tjd - 0.4075 * sin($Mm) + 0.1721 * $E * sin($M);

            // Iterative refinement to find minimum angle (swecl.c:1267-1302)
            $dtstart = 1.0;
            if ($tjd < 2000000.0 || $tjd > 2500000.0) {
                $dtstart = 5.0;
            }
            $dtdiv = 4.0;

            for ($dt = $dtstart; $dt > 0.0001; $dt /= $dtdiv) {
                $dc = [];
                for ($i = 0, $t = $tjd - $dt; $i <= 2; $i++, $t += $dt) {
                    $ls = [];
                    $lm = [];
                    $xs = [];
                    $xm = [];

                    if (\swe_calc($t, Constants::SE_SUN, $iflag, $ls, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }
                    if (\swe_calc($t, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }
                    if (\swe_calc($t, Constants::SE_SUN, $iflagCart, $xs, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }
                    if (\swe_calc($t, Constants::SE_MOON, $iflagCart, $xm, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }

                    // Normalize vectors (swecl.c:1292-1295)
                    $xa = [
                        $xs[0] / $ls[2],
                        $xs[1] / $ls[2],
                        $xs[2] / $ls[2]
                    ];
                    $xb = [
                        $xm[0] / $lm[2],
                        $xm[1] / $lm[2],
                        $xm[2] / $lm[2]
                    ];

                    // Angular distance (swecl.c:1296)
                    $dc[$i] = acos(VectorMath::dotProductUnit($xa, $xb)) * Constants::RADTODEG;

                    // Subtract sum of angular radii (swecl.c:1297-1299)
                    $rmoon = asin(self::RMOON / $lm[2]) * Constants::RADTODEG;
                    $rsun = asin(self::RSUN * 2.0 / 2.0 / Constants::AUNIT / $ls[2]) * Constants::RADTODEG;
                    $dc[$i] -= ($rmoon + $rsun);
                }

                // Find parabolic maximum (swecl.c:1301)
                $dtint = 0.0;
                $dctr = 0.0;
                self::findMaximum($dc[0], $dc[1], $dc[2], $dt, $dtint, $dctr);
                $tjd += $dtint + $dt;
            }

            if ($iteration >= 6 && $iteration <= 8) {
            }

            // Convert to UT (swecl.c:1303-1305)
            $tjds = $tjd - \swe_deltat_ex($tjd, $ifl, $serr);
            $tjds = $tjd - \swe_deltat_ex($tjds, $ifl, $serr);
            $tjds = $tjd = $tjd - \swe_deltat_ex($tjds, $ifl, $serr);

            // Check if eclipse exists at this time (swecl.c:1306-1307)
            $geopos = [];
            $dcore = [];
            $retflag = EclipseCalculator::eclipseWhere($tjd, Constants::SE_SUN, null, $ifl, $geopos, $dcore, $serr);
            if ($retflag === Constants::SE_ERR) {
                return $retflag;
            }
            $retflag2 = $retflag;

            // Double-check with eclipse_how() (swecl.c:1308-1312)
            $attr = [];
            $retflag2 = SolarEclipseWhereFunctions::eclipseHow($tjd, Constants::SE_SUN, null, $ifl,
                $geopos[0], $geopos[1], 0.0, $attr, $serr);
            if ($retflag2 === Constants::SE_ERR) {
                return $retflag2;
            }
            if ($retflag2 === 0) {
                $K += $direction;
                continue; // goto next_try
            }

            $tret[0] = $tjds;  // Use UT time for comparison

            // Check if we need to continue searching (swecl.c:1317-1321)
            // backward: skip if eclipse >= start (want earlier ones)
            // forward: skip if eclipse <= start (want later ones)
            if (($backward && $tret[0] >= $tjdStart - 0.0001) ||
                (!$backward && $tret[0] <= $tjdStart + 0.0001)) {
                $K += $direction;
                continue; // goto next_try
            }

            // Get eclipse type (swecl.c:1322-1328)
            // Reset topocentric position that was set by eclipseHow()
            \swe_set_topo(0.0, 0.0, 0.0);
            // Force recalculation of Sun/Moon to clear topocentric cache
            $dummy = [];
            \swe_calc($tjd, Constants::SE_SUN, $iflag, $dummy, $serr);
            \swe_calc($tjd, Constants::SE_MOON, $iflag, $dummy, $serr);

            $retflag = EclipseCalculator::eclipseWhere($tjd, Constants::SE_SUN, null, $ifl, $geopos, $dcore, $serr);
            if ($retflag === Constants::SE_ERR) {
                return $retflag;
            }
            if ($retflag === 0) {
                // Extremely small eclipse
                $retflag = Constants::SE_ECL_PARTIAL | Constants::SE_ECL_NONCENTRAL;
                $tret[4] = $tret[5] = $tjd;
                $dontTimes = true;
            }

            // Check if eclipse type is wanted (swecl.c:1329-1353)
            if (!($ifltype & Constants::SE_ECL_NONCENTRAL) && ($retflag & Constants::SE_ECL_NONCENTRAL)) {
                $K += $direction;
                continue;
            }
            if (!($ifltype & Constants::SE_ECL_CENTRAL) && ($retflag & Constants::SE_ECL_CENTRAL)) {
                $K += $direction;
                continue;
            }
            if (!($ifltype & Constants::SE_ECL_ANNULAR) && ($retflag & Constants::SE_ECL_ANNULAR)) {
                $K += $direction;
                continue;
            }
            if (!($ifltype & Constants::SE_ECL_PARTIAL) && ($retflag & Constants::SE_ECL_PARTIAL)) {
                $K += $direction;
                continue;
            }
            if (!($ifltype & (Constants::SE_ECL_TOTAL | Constants::SE_ECL_ANNULAR_TOTAL)) &&
                ($retflag & Constants::SE_ECL_TOTAL)) {
                $K += $direction;
                continue;
            }

            if ($dontTimes) {
                break; // goto end_search_global
            }

            // Calculate begin/end times for different eclipse phases (swecl.c:1354-1430)
            // n = 0: times of eclipse begin and end (tret[2], tret[3])
            // n = 1: times of totality begin and end (tret[4], tret[5])
            // n = 2: times of center line begin and end (tret[6], tret[7])

            $o = 0;
            if ($retflag & Constants::SE_ECL_PARTIAL) {
                $o = 0;
            } elseif ($retflag & Constants::SE_ECL_NONCENTRAL) {
                $o = 1;
            } else {
                $o = 2;
            }

            $dta = $twohr;
            $dtb = $tenmin / 3.0;

            for ($n = 0; $n <= $o; $n++) {
                if ($n === 0) {
                    $i1 = 2;
                    $i2 = 3;
                } elseif ($n === 1) {
                    if ($retflag & Constants::SE_ECL_PARTIAL) {
                        continue;
                    }
                    $i1 = 4;
                    $i2 = 5;
                } else { // $n === 2
                    if ($retflag & Constants::SE_ECL_NONCENTRAL) {
                        continue;
                    }
                    $i1 = 6;
                    $i2 = 7;
                }

                // First approximation (swecl.c:1388-1396)
                $dc = [];
                for ($i = 0, $t = $tjd - $dta; $i <= 2; $i++, $t += $dta) {
                    $retflag2 = EclipseCalculator::eclipseWhere($t, Constants::SE_SUN, null, $ifl, $geopos, $dcore, $serr);
                    if ($retflag2 === Constants::SE_ERR) {
                        return $retflag2;
                    }

                    if ($n === 0) {
                        $dc[$i] = $dcore[4] / 2.0 + $de / $dcore[5] - $dcore[2];
                    } elseif ($n === 1) {
                        $dc[$i] = abs($dcore[3]) / 2.0 + $de / $dcore[6] - $dcore[2];
                    } else { // $n === 2
                        $dc[$i] = $de / $dcore[6] - $dcore[2];
                    }
                }

                $dt1 = 0.0;
                $dt2 = 0.0;
                self::findZero($dc[0], $dc[1], $dc[2], $dta, $dt1, $dt2);
                $tret[$i1] = $tjd + $dt1 + $dta;
                $tret[$i2] = $tjd + $dt2 + $dta;

                // Refinement (swecl.c:1401-1417)
                for ($m = 0, $dt = $dtb; $m < 3; $m++, $dt /= 3.0) {
                    for ($j = $i1; $j <= $i2; $j += ($i2 - $i1)) {
                        for ($i = 0, $t = $tret[$j] - $dt; $i < 2; $i++, $t += $dt) {
                            $retflag2 = EclipseCalculator::eclipseWhere($t, Constants::SE_SUN, null, $ifl, $geopos, $dcore, $serr);
                            if ($retflag2 === Constants::SE_ERR) {
                                return $retflag2;
                            }

                            if ($n === 0) {
                                $dc[$i] = $dcore[4] / 2.0 + $de / $dcore[5] - $dcore[2];
                            } elseif ($n === 1) {
                                $dc[$i] = abs($dcore[3]) / 2.0 + $de / $dcore[6] - $dcore[2];
                            } else { // $n === 2
                                $dc[$i] = $de / $dcore[6] - $dcore[2];
                            }
                        }
                        $dt1 = $dc[1] / (($dc[1] - $dc[0]) / $dt);
                        $tret[$j] -= $dt1;
                    }
                }
            }

            // Check for annular-total eclipses (swecl.c:1418-1440)
            if ($retflag & Constants::SE_ECL_TOTAL) {
                $retflag2 = EclipseCalculator::eclipseWhere($tret[0], Constants::SE_SUN, null, $ifl, $geopos, $dcore, $serr);
                if ($retflag2 === Constants::SE_ERR) {
                    return $retflag2;
                }
                $dc[0] = $dcore[0];

                $retflag2 = EclipseCalculator::eclipseWhere($tret[4], Constants::SE_SUN, null, $ifl, $geopos, $dcore, $serr);
                if ($retflag2 === Constants::SE_ERR) {
                    return $retflag2;
                }
                $dc[1] = $dcore[0];

                $retflag2 = EclipseCalculator::eclipseWhere($tret[5], Constants::SE_SUN, null, $ifl, $geopos, $dcore, $serr);
                if ($retflag2 === Constants::SE_ERR) {
                    return $retflag2;
                }
                $dc[2] = $dcore[0];

                // The maximum is always total, and there is either one or two times
                // before and after when the core shadow becomes zero and totality
                // changes into annularity or vice versa
                if ($dc[0] * $dc[1] < 0.0 || $dc[0] * $dc[2] < 0.0) {
                    $retflag |= Constants::SE_ECL_ANNULAR_TOTAL;
                    $retflag &= ~Constants::SE_ECL_TOTAL;
                }
            }

            // Check if total eclipse is wanted (swecl.c:1441-1444)
            if (!($ifltype & Constants::SE_ECL_TOTAL) && ($retflag & Constants::SE_ECL_TOTAL)) {
                $K += $direction;
                continue;
            }

            // Check if annular-total eclipse is wanted (swecl.c:1445-1448)
            if (!($ifltype & Constants::SE_ECL_ANNULAR_TOTAL) && ($retflag & Constants::SE_ECL_ANNULAR_TOTAL)) {
                $K += $direction;
                continue;
            }

            // Time of maximum eclipse at local apparent noon (swecl.c:1449-1505)
            // First, find out if there is a solar transit between begin and end of eclipse
            $k = 2;
            $dc = [];
            for ($i = 0; $i < 2; $i++) {
                $j = $i + $k;
                $tt = $tret[$j] + \swe_deltat_ex($tret[$j], $ifl, $serr);

                $ls = [];
                if (\swe_calc($tt, Constants::SE_SUN, $iflag, $ls, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $lm = [];
                if (\swe_calc($tt, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $dc[$i] = \swe_degnorm($ls[0] - $lm[0]);
                if ($dc[$i] > 180.0) {
                    $dc[$i] -= 360.0;
                }
            }

            if ($dc[0] * $dc[1] >= 0.0) {
                // No transit
                $tret[1] = 0.0;
            } else {
                $tjd = $tjds;
                $dt = 0.1;
                $dt1 = ($tret[3] - $tret[2]) / 2.0;
                if ($dt1 < $dt) {
                    $dt = $dt1 / 2.0;
                }

                for ($j = 0; $dt > 0.01; $j++, $dt /= 3.0) {
                    for ($i = 0, $t = $tjd; $i <= 1; $i++, $t -= $dt) {
                        $tt = $t + \swe_deltat_ex($t, $ifl, $serr);

                        $ls = [];
                        if (\swe_calc($tt, Constants::SE_SUN, $iflag, $ls, $serr) === Constants::SE_ERR) {
                            return Constants::SE_ERR;
                        }

                        $lm = [];
                        if (\swe_calc($tt, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
                            return Constants::SE_ERR;
                        }

                        $dc[$i] = \swe_degnorm($ls[0] - $lm[0]);
                        if ($dc[$i] > 180.0) {
                            $dc[$i] -= 360.0;
                        }
                    }

                    $a = ($dc[1] - $dc[0]) / $dt;
                    if ($a < 1e-10) {
                        break;
                    }
                    $dt1 = $dc[0] / $a;
                    $tjd += $dt1;
                }
                $tret[1] = $tjd;
            }

            break; // end_search_global
        }

        return $retflag;
    }

    /**
     * Find parabolic maximum/minimum
     * Port from swephlib.c find_maximum()
     *
     * @param float $y1 Function value at x-dx
     * @param float $y2 Function value at x
     * @param float $y3 Function value at x+dx
     * @param float $dx Step size
     * @param float &$dxret Offset of extremum from x
     * @param float &$dyret Function value at extremum
     */
    private static function findMaximum(
        float $y1,
        float $y2,
        float $y3,
        float $dx,
        float &$dxret,
        float &$dyret
    ): void {
        $a = ($y1 + $y3) / 2.0 - $y2;
        $b = ($y3 - $y1) / 2.0;
        $c = $y2;
        $x = 0.0;

        if ($a !== 0.0) {
            $x = -$b / 2.0 / $a;
        }

        $dxret = ($x - 1.0) * $dx;  // C code: (x - 1) * dx
        $dyret = (4.0 * $a * $c - $b * $b) / 4.0 / $a;  // C code: (4*a*c - b*b) / 4 / a
    }

    /**
     * Find zeros of parabola
     * Port from swecl.c:4148-4162 (find_zero)
     *
     * Finds the two zeros of a parabola defined by three points.
     * Returns OK if zeros are real, ERR if complex.
     *
     * @param float $y00 Function value at x-dx
     * @param float $y11 Function value at x
     * @param float $y2 Function value at x+dx
     * @param float $dx Step size
     * @param float &$dxret Offset of first zero from x
     * @param float &$dxret2 Offset of second zero from x
     * @return int OK or ERR
     */
    private static function findZero(
        float $y00,
        float $y11,
        float $y2,
        float $dx,
        float &$dxret,
        float &$dxret2
    ): int {
        $c = $y11;
        $b = ($y2 - $y00) / 2.0;
        $a = ($y2 + $y00) / 2.0 - $c;

        $discriminant = $b * $b - 4.0 * $a * $c;
        if ($discriminant < 0.0) {
            return Constants::SE_ERR;
        }

        $x1 = (-$b + sqrt($discriminant)) / 2.0 / $a;
        $x2 = (-$b - sqrt($discriminant)) / 2.0 / $a;

        $dxret = ($x1 - 1.0) * $dx;
        $dxret2 = ($x2 - 1.0) * $dx;

        return Constants::SE_OK;
    }
}
