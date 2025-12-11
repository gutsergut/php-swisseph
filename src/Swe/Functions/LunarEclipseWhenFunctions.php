<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Swe\Eclipses\SarosData;

/**
 * Lunar Eclipse When Functions
 *
 * Functions for finding times of lunar eclipses globally.
 * Port from swecl.c:3378-3632 (254 lines).
 *
 * WITHOUT SIMPLIFICATIONS - full C algorithm ported.
 */
class LunarEclipseWhenFunctions
{
    /**
     * When is the next lunar eclipse anywhere on earth?
     *
     * Port from swecl.c:3378-3632.
     *
     * @param float $tjdStart Start time for search (JD UT)
     * @param int $ifl Ephemeris flag (SEFLG_SWIEPH, etc.)
     * @param int $ifltype Eclipse type to search (SE_ECL_TOTAL, etc.), 0 = any
     * @param array &$tret Return array for eclipse times (declare as [10])
     *                     [0] = time of maximum eclipse
     *                     [1] = (not used)
     *                     [2] = time of partial phase begin
     *                     [3] = time of partial phase end
     *                     [4] = time of totality begin
     *                     [5] = time of totality end
     *                     [6] = time of penumbral phase begin
     *                     [7] = time of penumbral phase end
     * @param int $backward Search backward if 1
     * @param string &$serr Error message return
     * @return int Eclipse type flags or 0 if no eclipse, ERR on error
     */
    public static function when(
        float $tjdStart,
        int $ifl,
        int $ifltype,
        array &$tret,
        int $backward,
        string &$serr
    ): int {
        return self::lunEclipseWhen($tjdStart, $ifl, $ifltype, $tret, $backward, $serr);
    }

    /**
     * Internal implementation of swe_lun_eclipse_when()
     *
     * Port from swecl.c:3378-3632.
     *
     * @internal
     */
    private static function lunEclipseWhen(
        float $tjdStart,
        int $ifl,
        int $ifltype,
        array &$tret,
        int $backward,
        string &$serr
    ): int {
        // Constants (swecl.c:3389-3397)
        $twohr = 2.0 / 24.0;
        $tenmin = 10.0 / 24.0 / 60.0;
        $direction = 1;

        // Physical constants in AU
        $AUNIT = 1.49597870700e+11;
        $RMOON = 1737400.0 / $AUNIT;
        $RSUN = 695990000.0 / $AUNIT;
        $REARTH = 6378136.6 / $AUNIT;
        $RADTODEG = 57.29577951308232088;
        $DEGTORAD = 1.0 / $RADTODEG;
        $J2000 = 2451545.0;

        // Initialize (swecl.c:3398-3400)
        $ifl &= Constants::SEFLG_EPHMASK;
        \swe_set_tid_acc($tjdStart, $ifl, 0);

        $iflag = Constants::SEFLG_EQUATORIAL | $ifl;
        $iflagcart = $iflag | Constants::SEFLG_XYZ;

        // Remove invalid flags for lunar eclipses (swecl.c:3401-3410)
        $ifltype &= ~(Constants::SE_ECL_CENTRAL | Constants::SE_ECL_NONCENTRAL);
        if ($ifltype & (Constants::SE_ECL_ANNULAR | Constants::SE_ECL_ANNULAR_TOTAL)) {
            $ifltype &= ~(Constants::SE_ECL_ANNULAR | Constants::SE_ECL_ANNULAR_TOTAL);
            if ($ifltype === 0) {
                $serr = "annular lunar eclipses don't exist";
                return Constants::SE_ERR;
            }
        }

        if ($ifltype === 0) {
            $ifltype = Constants::SE_ECL_TOTAL | Constants::SE_ECL_PENUMBRAL | Constants::SE_ECL_PARTIAL;
        }

        // DEBUG
        // error_log(sprintf("DEBUG: ifltype after init = %d (TOTAL=%d, PENUMBRAL=%d, PARTIAL=%d)",
        //     $ifltype, Constants::SE_ECL_TOTAL, Constants::SE_ECL_PENUMBRAL, Constants::SE_ECL_PARTIAL));

        if ($backward) {
            $direction = -1;
        }

        // Meeus lunation number (swecl.c:3415-3416)
        $K = (int)(($tjdStart - $J2000) / 365.2425 * 12.3685);
        $K -= $direction;

        // Main search loop (swecl.c:3417)
        next_try:
        $retflag = 0;
        $tret = array_fill(0, 10, 0.0);

        // Calculate approximate time using Meeus formulae (swecl.c:3420-3426)
        $kk = $K + 0.5;
        $T = $kk / 1236.85;
        $T2 = $T * $T;
        $T3 = $T2 * $T;
        $T4 = $T3 * $T;

        // Moon's argument of latitude F (swecl.c:3427-3431)
        $F = \swe_degnorm(160.7108 + 390.67050274 * $kk
                         - 0.0016341 * $T2
                         - 0.00000227 * $T3
                         + 0.000000011 * $T4);
        $Ff = $F;
        if ($Ff > 180) {
            $Ff -= 180;
        }

        // Check if eclipse is possible (swecl.c:3432-3435)
        if ($Ff > 21 && $Ff < 159) {
            $K += $direction;
            goto next_try;
        }

        // Approximate time of geocentric maximum eclipse (swecl.c:3436-3440)
        $tjd = 2451550.09765 + 29.530588853 * $kk
                             + 0.0001337 * $T2
                             - 0.000000150 * $T3
                             + 0.00000000073 * $T4;

        // Sun's mean anomaly (swecl.c:3441-3443)
        $M = \swe_degnorm(2.5534 + 29.10535669 * $kk
                         - 0.0000218 * $T2
                         - 0.00000011 * $T3);

        // Moon's mean anomaly (swecl.c:3444-3447)
        $Mm = \swe_degnorm(201.5643 + 385.81693528 * $kk
                          + 0.1017438 * $T2
                          + 0.00001239 * $T3
                          + 0.000000058 * $T4);

        // Moon's ascending node (swecl.c:3448-3450)
        $Om = \swe_degnorm(124.7746 - 1.56375580 * $kk
                          + 0.0020691 * $T2
                          + 0.00000215 * $T3);

        // Eccentricity correction (swecl.c:3451)
        $E = 1 - 0.002516 * $T - 0.0000074 * $T2;

        // Additional term A1 (swecl.c:3452)
        $A1 = \swe_degnorm(299.77 + 0.107408 * $kk - 0.009173 * $T2);

        // Convert to radians (swecl.c:3453-3457)
        $M *= $DEGTORAD;
        $Mm *= $DEGTORAD;
        $F *= $DEGTORAD;
        $Om *= $DEGTORAD;
        $F1 = $F - 0.02665 * sin($Om) * $DEGTORAD;
        $A1 *= $DEGTORAD;

        // Apply corrections to time (swecl.c:3458-3473)
        $tjd = $tjd - 0.4075 * sin($Mm)
                    + 0.1721 * $E * sin($M)
                    + 0.0161 * sin(2 * $Mm)
                    - 0.0097 * sin(2 * $F1)
                    + 0.0073 * $E * sin($Mm - $M)
                    - 0.0050 * $E * sin($Mm + $M)
                    - 0.0023 * sin($Mm - 2 * $F1)
                    + 0.0021 * $E * sin(2 * $M)
                    + 0.0012 * sin($Mm + 2 * $F1)
                    + 0.0006 * $E * sin(2 * $Mm + $M)
                    - 0.0004 * sin(3 * $Mm)
                    - 0.0003 * $E * sin($M + 2 * $F1)
                    + 0.0003 * sin($A1)
                    - 0.0002 * $E * sin($M - 2 * $F1)
                    - 0.0002 * $E * sin(2 * $Mm - $M)
                    - 0.0002 * sin($Om);

        // Precise computation using iterative refinement (swecl.c:3479-3486)
        $dtstart = 0.1;
        if ($tjd < 2100000 || $tjd > 2500000) {
            $dtstart = 5;
        }
        $dtdiv = 4;

        // Find minimum selenocentric angle (swecl.c:3487-3516)
        for ($j = 0, $dt = $dtstart; $dt > 0.001; $j++, $dt /= $dtdiv) {
            $dc = [];
            for ($i = 0, $t = $tjd - $dt; $i <= 2; $i++, $t += $dt) {
                $xs = [];
                $xm = [];

                // Sun position (swecl.c:3490-3491)
                if (\swe_calc($t, Constants::SE_SUN, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                // Moon position (swecl.c:3492-3493)
                if (\swe_calc($t, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                // Selenocentric coordinates (swecl.c:3494-3497)
                for ($m = 0; $m < 3; $m++) {
                    $xs[$m] -= $xm[$m];  // selenocentric sun
                    $xm[$m] = -$xm[$m];  // selenocentric earth
                }

                // Distances (swecl.c:3498-3499)
                $ds = sqrt($xs[0]*$xs[0] + $xs[1]*$xs[1] + $xs[2]*$xs[2]);
                $dm = sqrt($xm[0]*$xm[0] + $xm[1]*$xm[1] + $xm[2]*$xm[2]);

                // Unit vectors (swecl.c:3500-3503)
                $xa = [];
                $xb = [];
                for ($m = 0; $m < 3; $m++) {
                    $xa[$m] = $xs[$m] / $ds;
                    $xb[$m] = $xm[$m] / $dm;
                }

                // Angular distance minus radii (swecl.c:3504-3507)
                $dctr = acos($xa[0]*$xb[0] + $xa[1]*$xb[1] + $xa[2]*$xb[2]) * $RADTODEG;
                $rearth = asin($REARTH / $dm) * $RADTODEG;
                $rsun = asin($RSUN / $ds) * $RADTODEG;
                $dc[$i] = $dctr - ($rearth + $rsun);
            }

            // Find maximum (minimum of dc) (swecl.c:3508-3509)
            $dtint = 0;
            $dctrMin = 0;
            self::findMaximum($dc[0], $dc[1], $dc[2], $dt, $dtint, $dctrMin);
            $tjd += $dtint + $dt;
        }

        // Convert from TT to UT (swecl.c:3510-3512)
        $tjd2 = $tjd - \swe_deltat_ex($tjd, $ifl, $serr);
        $tjd2 = $tjd - \swe_deltat_ex($tjd2, $ifl, $serr);
        $tjd = $tjd - \swe_deltat_ex($tjd2, $ifl, $serr);

        // Check if eclipse occurs (swecl.c:3513-3514)
        $attr = [];
        $retflag = \swe_lun_eclipse_how($tjd, $ifl, null, $attr, $serr);
        if ($retflag === Constants::SE_ERR) {
            return $retflag;
        }

        if ($retflag === 0) {
            $K += $direction;
            goto next_try;
        }

        $tret[0] = $tjd;

        // Check if eclipse is within search range (swecl.c:3519-3523)
        if (($backward && $tret[0] >= $tjdStart - 0.0001)
            || (!$backward && $tret[0] <= $tjdStart + 0.0001)) {
            $K += $direction;
            goto next_try;
        }

        // Check if eclipse type is wanted (swecl.c:3524-3538)
        // DEBUG
        // error_log(sprintf("DEBUG: Found eclipse retflag=%d at JD=%.6f", $retflag, $tjd));
        // error_log(sprintf("DEBUG: Checking ifltype=%d & PENUMBRAL=%d = %d",
        //     $ifltype, Constants::SE_ECL_PENUMBRAL, $ifltype & Constants::SE_ECL_PENUMBRAL));

        if (!($ifltype & Constants::SE_ECL_PENUMBRAL) && ($retflag & Constants::SE_ECL_PENUMBRAL)) {
            $K += $direction;
            goto next_try;
        }

        if (!($ifltype & Constants::SE_ECL_PARTIAL) && ($retflag & Constants::SE_ECL_PARTIAL)) {
            $K += $direction;
            goto next_try;
        }

        if (!($ifltype & Constants::SE_ECL_TOTAL) && ($retflag & Constants::SE_ECL_TOTAL)) {
            $K += $direction;
            goto next_try;
        }

        // Calculate contact times (swecl.c:3539-3628)
        // n=0: penumbral phase, n=1: partial phase, n=2: totality
        $o = 0;
        if ($retflag & Constants::SE_ECL_PENUMBRAL) {
            $o = 0;
        } elseif ($retflag & Constants::SE_ECL_PARTIAL) {
            $o = 1;
        } else {
            $o = 2;
        }

        $dta = $twohr;
        $dtb = $tenmin;

        for ($n = 0; $n <= $o; $n++) {
            // Select indices for this phase (swecl.c:3549-3555)
            if ($n === 0) {
                $i1 = 6; $i2 = 7;  // penumbral begin/end
            } elseif ($n === 1) {
                $i1 = 2; $i2 = 3;  // partial begin/end
            } else {
                $i1 = 4; $i2 = 5;  // totality begin/end
            }

            // Find approximate contact times (swecl.c:3556-3566)
            $dc = [];
            for ($i = 0, $t = $tjd - $dta; $i <= 2; $i++, $t += $dta) {
                $dcore = [];
                $attr_tmp = [];
                $retflag2 = LunarEclipseFunctions::lunEclipseHow($t, $ifl, $attr_tmp, $dcore, $serr);
                if ($retflag2 === Constants::SE_ERR) {
                    return $retflag2;
                }

                if ($n === 0) {
                    $dc[$i] = $dcore[2] / 2 + $RMOON / $dcore[4] - $dcore[0];
                } elseif ($n === 1) {
                    $dc[$i] = $dcore[1] / 2 + $RMOON / $dcore[3] - $dcore[0];
                } else {
                    $dc[$i] = $dcore[1] / 2 - $RMOON / $dcore[3] - $dcore[0];
                }
            }

            $dt1 = 0;
            $dt2 = 0;
            self::findZero($dc[0], $dc[1], $dc[2], $dta, $dt1, $dt2);
            $dtb = ($dt1 + $dta) / 2;
            $tret[$i1] = $tjd + $dt1 + $dta;
            $tret[$i2] = $tjd + $dt2 + $dta;

            // Refine contact times (swecl.c:3572-3590)
            for ($m = 0, $dt = $dtb / 2; $m < 3; $m++, $dt /= 2) {
                for ($j = $i1; $j <= $i2; $j += ($i2 - $i1)) {
                    $dc = [];
                    for ($i = 0, $t = $tret[$j] - $dt; $i < 2; $i++, $t += $dt) {
                        $dcore = [];
                        $retflag2 = LunarEclipseFunctions::lunEclipseHow($t, $ifl, $attr, $dcore, $serr);
                        if ($retflag2 === Constants::SE_ERR) {
                            return $retflag2;
                        }

                        if ($n === 0) {
                            $dc[$i] = $dcore[2] / 2 + $RMOON / $dcore[4] - $dcore[0];
                        } elseif ($n === 1) {
                            $dc[$i] = $dcore[1] / 2 + $RMOON / $dcore[3] - $dcore[0];
                        } else {
                            $dc[$i] = $dcore[1] / 2 - $RMOON / $dcore[3] - $dcore[0];
                        }
                    }

                    $dt1 = $dc[1] / (($dc[1] - $dc[0]) / $dt);
                    $tret[$j] -= $dt1;
                }
            }
        }

        return $retflag;
    }

    /**
     * Find maximum of parabola through 3 points
     *
     * Port from swecl.c (find_maximum helper function)
     */
    private static function findMaximum(
        float $y1,
        float $y2,
        float $y3,
        float $dx,
        float &$dxret,
        float &$yret
    ): void {
        $a = ($y1 + $y3) / 2 - $y2;
        $b = ($y3 - $y1) / 2;
        $c = $y2;

        if ($a == 0) {
            $dxret = 0;
            $yret = $y2;
            return;
        }

        $x = -$b / 2 / $a;
        $dxret = ($x - 1) * $dx;
        $yret = ($a * $x + $b) * $x + $c;
    }

    /**
     * Find zeros of parabola through 3 points
     *
     * Port from swecl.c (find_zero helper function)
     */
    private static function findZero(
        float $y1,
        float $y2,
        float $y3,
        float $dx,
        float &$dx1,
        float &$dx2
    ): void {
        $a = ($y1 + $y3) / 2 - $y2;
        $b = ($y3 - $y1) / 2;
        $c = $y2;

        if ($a == 0) {
            $dx1 = $dx2 = 0;
            return;
        }

        $x1 = (-$b + sqrt($b * $b - 4 * $a * $c)) / 2 / $a;
        $x2 = (-$b - sqrt($b * $b - 4 * $a * $c)) / 2 / $a;

        $dx1 = ($x1 - 1) * $dx;
        $dx2 = ($x2 - 1) * $dx;
    }
}
