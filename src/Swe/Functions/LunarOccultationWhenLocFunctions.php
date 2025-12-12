<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\VectorMath;
use Swisseph\Swe\Eclipses\EclipseCalculator;

use function swe_calc;
use function swe_deltat_ex;
use function swe_degnorm;
use function swe_set_topo;
use function swe_rise_trans;

/**
 * Lunar occultation "when local" functions
 * Port of swe_lun_occult_when_loc() from swecl.c:2071-2098
 */
final class LunarOccultationWhenLocFunctions
{
    /**
     * Find next lunar occultation visible at given location
     */
    public static function whenLoc(
        float $tjdStart,
        int $ipl,
        ?string $starname,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward,
        ?string &$serr
    ): int {
        // Validate altitude
        if ($geopos[2] < Constants::SEI_ECL_GEOALT_MIN || $geopos[2] > Constants::SEI_ECL_GEOALT_MAX) {
            $serr = sprintf(
                "location for occultations must be between %.0f and %.0f m above sea",
                Constants::SEI_ECL_GEOALT_MIN,
                Constants::SEI_ECL_GEOALT_MAX
            );
            return Constants::SE_ERR;
        }

        if ($ipl < 0) {
            $ipl = 0;
        }

        if ($ipl === Constants::SE_AST_OFFSET + 134340) {
            $ipl = Constants::SE_PLUTO;
        }

        $ifl &= Constants::SEFLG_EPHMASK;

        $retflag = self::occultWhenLoc($tjdStart, $ipl, $starname, $ifl, $geopos, $tret, $attr, $backward, $serr);

        if ($retflag <= 0) {
            return $retflag;
        }

        $geopos2 = array_fill(0, 10, 0.0);
        $dcore = array_fill(0, 10, 0.0);
        $retflag2 = EclipseCalculator::eclipseWhere($tret[0], $ipl, $starname, $ifl, $geopos2, $dcore, $serr);

        if ($retflag2 === Constants::SE_ERR) {
            return $retflag2;
        }

        if ($retflag2 >= 0) {
            $retflag |= ($retflag2 & Constants::SE_ECL_NONCENTRAL);
        }
        $attr[3] = $dcore[0];

        return $retflag;
    }

    /**
     * Internal function - single large function with goto as in C
     * Port of occult_when_loc() from swecl.c:2412-2804
     */
    private static function occultWhenLoc(
        float $tjdStart,
        int $ipl,
        ?string $starname,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward,
        ?string &$serr
    ): int {
        // Planet diameters in km
        $plaDiam = [0, 0, 4879.4, 12103.6, 0, 142984.0, 120536.0, 51118.0, 49528.0, 2390.0];

        $retflag = 0;
        $twomin = 2.0 / 24.0 / 60.0;
        $tensec = 10.0 / 24.0 / 60.0 / 60.0;
        $twohr = 2.0 / 24.0;
        $tenmin = 10.0 / 24.0 / 60.0;
        $dadd2 = 1.0;

        $direction = 1;
        $oneTry = ($backward & Constants::SE_ECL_ONE_TRY) !== 0;
        $stopAfterThis = false;
        $backward = ($backward & 1) !== 0;

        if ($backward) {
            $direction = -1;
        }

        swe_set_topo($geopos[0], $geopos[1], $geopos[2]);

        for ($i = 0; $i <= 9; $i++) {
            $tret[$i] = 0.0;
        }

        $iflag = Constants::SEFLG_TOPOCTR | $ifl;
        $iflaggeo = $iflag & ~Constants::SEFLG_TOPOCTR;
        $iflagcart = $iflag | Constants::SEFLG_XYZ;

        $t = $tjdStart;
        $tjd = $tjdStart;

        // === MAIN LOOP WITH GOTO ===
        next_try:

        $ls = [];
        if (EclipseCalculator::calcPlanetStar($t, $ipl, $starname, $iflaggeo, $ls, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        if (abs($ls[1]) > 7 && $starname !== null && $starname !== '') {
            $serr = sprintf("occultation never occurs: star %s has ecl. lat. %.1f", $starname, $ls[1]);
            return Constants::SE_ERR;
        }

        $lm = [];
        if (swe_calc($t, Constants::SE_MOON, $iflaggeo, $lm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Rough conjunction in longitude
        $dl = swe_degnorm($ls[0] - $lm[0]);
        if ($direction < 0) {
            $dl -= 360;
        }

        while (abs($dl) > 0.1) {
            $t += $dl / 13;
            if (EclipseCalculator::calcPlanetStar($t, $ipl, $starname, $iflaggeo, $ls, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
            if (swe_calc($t, Constants::SE_MOON, $iflaggeo, $lm, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
            $dl = swe_degnorm($ls[0] - $lm[0]);
            if ($dl > 180) {
                $dl -= 360;
            }
        }

        $tjd = $t;

        $drad = abs($ls[1] - $lm[1]);
        if ($drad > 2) {
            if ($oneTry) {
                $tret[0] = $t + $direction;
                return 0;
            }
            $t += $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        // Planet radius in AU
        if ($starname !== null && $starname !== '') {
            $drad = 0;
        } elseif ($ipl < count($plaDiam)) {
            $drad = $plaDiam[$ipl] / 2 / Constants::AUNIT;
        } elseif ($ipl > Constants::SE_AST_OFFSET) {
            global $swed;
            $drad = $swed->ast_diam / 2 * 1000 / Constants::AUNIT;
        } else {
            $drad = 0;
        }

        // Refinement loop
        $dtdiv = 2;
        $dtstart = $dadd2;

        for ($dt = $dtstart; $dt > 0.00001; $dt /= $dtdiv) {
            if ($dt < 0.01) {
                $dtdiv = 2;
            }

            $dc = [];
            for ($i = 0, $t = $tjd - $dt; $i <= 2; $i++, $t += $dt) {
                $xs = [];
                if (EclipseCalculator::calcPlanetStar($t, $ipl, $starname, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $ls = [];
                if (EclipseCalculator::calcPlanetStar($t, $ipl, $starname, $iflag, $ls, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $xm = [];
                if (swe_calc($t, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $lm = [];
                if (swe_calc($t, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                if ($dt < 0.1 && abs($ls[1] - $lm[1]) > 2) {
                    if ($oneTry || $stopAfterThis) {
                        $stopAfterThis = true;
                    } else {
                        $t = $tjd + $direction * 20;
                        $tjd = $t;
                        goto next_try;
                    }
                }

                $dc[$i] = acos(VectorMath::dotProductUnit($xs, $xm)) * Constants::RADTODEG;
                $rmoon = asin(Constants::RMOON / $lm[2]) * Constants::RADTODEG;
                $rsun = asin($drad / $ls[2]) * Constants::RADTODEG;
                $dc[$i] -= ($rmoon + $rsun);
            }

            $dctr = 0;
            $dtint = 0;
            self::findMaximum($dc[0], $dc[1], $dc[2], $dt, $dtint, $dctr);
            $tjd += $dtint + $dt;
        }

        if ($stopAfterThis) {
            $tret[0] = $tjd + $direction;
            return 0;
        }

        // Final positions
        $xs = [];
        if (EclipseCalculator::calcPlanetStar($tjd, $ipl, $starname, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        $ls = [];
        if (EclipseCalculator::calcPlanetStar($tjd, $ipl, $starname, $iflag, $ls, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        $xm = [];
        if (swe_calc($tjd, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        $lm = [];
        if (swe_calc($tjd, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        $dctr = acos(VectorMath::dotProductUnit($xs, $xm)) * Constants::RADTODEG;
        $rmoon = asin(Constants::RMOON / $lm[2]) * Constants::RADTODEG;
        $rsun = asin($drad / $ls[2]) * Constants::RADTODEG;
        $rsplusrm = $rsun + $rmoon;
        $rsminusrm = $rsun - $rmoon;

        if ($dctr > $rsplusrm) {
            if ($oneTry) {
                $tret[0] = $tjd + $direction;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        // Convert to UT
        $tret[0] = $tjd - swe_deltat_ex($tjd, $ifl, $serr);
        $tret[0] = $tjd - swe_deltat_ex($tret[0], $ifl, $serr);

        if (($backward && $tret[0] >= $tjdStart - 0.0001) ||
            (!$backward && $tret[0] <= $tjdStart + 0.0001)) {
            if ($oneTry) {
                $tret[0] = $tjd + $direction;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        // Eclipse type
        if ($dctr < $rsminusrm) {
            $retflag = Constants::SE_ECL_ANNULAR;
        } elseif ($dctr < abs($rsminusrm)) {
            $retflag = Constants::SE_ECL_TOTAL;
        } elseif ($dctr <= $rsplusrm) {
            $retflag = Constants::SE_ECL_PARTIAL;
        }

        $dctrmin = $dctr;

        // Contacts 2 and 3
        if ($dctr > abs($rsminusrm)) {
            $tret[2] = $tret[3] = 0;
        } else {
            $dc = [];
            $dc[1] = abs($rsminusrm) - $dctrmin;

            for ($i = 0, $t = $tjd - $twomin; $i <= 2; $i += 2, $t = $tjd + $twomin) {
                $xs = [];
                if (EclipseCalculator::calcPlanetStar($t, $ipl, $starname, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $xm = [];
                if (swe_calc($t, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $dm = sqrt(VectorMath::squareSum($xm));
                $ds = sqrt(VectorMath::squareSum($xs));
                $rmoon = asin(Constants::RMOON / $dm) * Constants::RADTODEG;
                $rmoon *= 0.99916;
                $rsun = asin($drad / $ds) * Constants::RADTODEG;
                $rsminusrm = $rsun - $rmoon;

                $x1 = [];
                $x2 = [];
                for ($k = 0; $k < 3; $k++) {
                    $x1[$k] = $xs[$k] / $ds;
                    $x2[$k] = $xm[$k] / $dm;
                }

                $dctr = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
                $dc[$i] = abs($rsminusrm) - $dctr;
            }

            $dt1 = 0;
            $dt2 = 0;
            self::findZero($dc[0], $dc[1], $dc[2], $twomin, $dt1, $dt2);
            $tret[2] = $tjd + $dt1 + $twomin;
            $tret[3] = $tjd + $dt2 + $twomin;

            // Refine 2/3
            for ($m = 0, $dt = $tensec; $m < 2; $m++, $dt /= 10) {
                for ($j = 2; $j <= 3; $j++) {
                    $xs = [];
                    if (EclipseCalculator::calcPlanetStar($tret[$j], $ipl, $starname, $iflagcart | Constants::SEFLG_SPEED, $xs, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }

                    $xm = [];
                    if (swe_calc($tret[$j], Constants::SE_MOON, $iflagcart | Constants::SEFLG_SPEED, $xm, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }

                    $dc = [];
                    for ($i = 0; $i < 2; $i++) {
                        if ($i === 1) {
                            for ($k = 0; $k < 3; $k++) {
                                $xs[$k] -= $xs[$k + 3] * $dt;
                                $xm[$k] -= $xm[$k + 3] * $dt;
                            }
                        }

                        $dm = sqrt(VectorMath::squareSum($xm));
                        $ds = sqrt(VectorMath::squareSum($xs));
                        $rmoon = asin(Constants::RMOON / $dm) * Constants::RADTODEG;
                        $rmoon *= 0.99916;
                        $rsun = asin($drad / $ds) * Constants::RADTODEG;
                        $rsminusrm = $rsun - $rmoon;

                        $x1 = [];
                        $x2 = [];
                        for ($k = 0; $k < 3; $k++) {
                            $x1[$k] = $xs[$k] / $ds;
                            $x2[$k] = $xm[$k] / $dm;
                        }

                        $dctr = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
                        $dc[$i] = abs($rsminusrm) - $dctr;
                    }

                    $dt1 = -$dc[0] / (($dc[0] - $dc[1]) / $dt);
                    $tret[$j] += $dt1;
                }
            }

            $tret[2] -= swe_deltat_ex($tret[2], $ifl, $serr);
            $tret[3] -= swe_deltat_ex($tret[3], $ifl, $serr);
        }

        // Contacts 1 and 4
        $dc = [];
        $dc[1] = $rsplusrm - $dctrmin;

        if ($starname === null || $starname === '') {
            for ($i = 0, $t = $tjd - $twohr; $i <= 2; $i += 2, $t = $tjd + $twohr) {
                $xs = [];
                if (EclipseCalculator::calcPlanetStar($t, $ipl, $starname, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $xm = [];
                if (swe_calc($t, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                $dm = sqrt(VectorMath::squareSum($xm));
                $ds = sqrt(VectorMath::squareSum($xs));
                $rmoon = asin(Constants::RMOON / $dm) * Constants::RADTODEG;
                $rsun = asin($drad / $ds) * Constants::RADTODEG;
                $rsplusrm = $rsun + $rmoon;

                $x1 = [];
                $x2 = [];
                for ($k = 0; $k < 3; $k++) {
                    $x1[$k] = $xs[$k] / $ds;
                    $x2[$k] = $xm[$k] / $dm;
                }

                $dctr = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
                $dc[$i] = $rsplusrm - $dctr;
            }

            $dt1 = 0;
            $dt2 = 0;
            self::findZero($dc[0], $dc[1], $dc[2], $twohr, $dt1, $dt2);
            $tret[1] = $tjd + $dt1 + $twohr;
            $tret[4] = $tjd + $dt2 + $twohr;

            // Refine 1/4
            for ($m = 0, $dt = $tenmin; $m < 3; $m++, $dt /= 10) {
                for ($j = 1; $j <= 4; $j += 3) {
                    $xs = [];
                    if (EclipseCalculator::calcPlanetStar($tret[$j], $ipl, $starname, $iflagcart | Constants::SEFLG_SPEED, $xs, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }

                    $xm = [];
                    if (swe_calc($tret[$j], Constants::SE_MOON, $iflagcart | Constants::SEFLG_SPEED, $xm, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }

                    $dc = [];
                    for ($i = 0; $i < 2; $i++) {
                        if ($i === 1) {
                            for ($k = 0; $k < 3; $k++) {
                                $xs[$k] -= $xs[$k + 3] * $dt;
                                $xm[$k] -= $xm[$k + 3] * $dt;
                            }
                        }

                        $dm = sqrt(VectorMath::squareSum($xm));
                        $ds = sqrt(VectorMath::squareSum($xs));
                        $rmoon = asin(Constants::RMOON / $dm) * Constants::RADTODEG;
                        $rsun = asin($drad / $ds) * Constants::RADTODEG;
                        $rsplusrm = $rsun + $rmoon;

                        $x1 = [];
                        $x2 = [];
                        for ($k = 0; $k < 3; $k++) {
                            $x1[$k] = $xs[$k] / $ds;
                            $x2[$k] = $xm[$k] / $dm;
                        }

                        $dctr = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;
                        $dc[$i] = abs($rsplusrm) - $dctr;
                    }

                    $dt1 = -$dc[0] / (($dc[0] - $dc[1]) / $dt);
                    $tret[$j] += $dt1;
                }
            }

            $tret[1] -= swe_deltat_ex($tret[1], $ifl, $serr);
            $tret[4] -= swe_deltat_ex($tret[4], $ifl, $serr);
        } else {
            // Fixed stars
            $tret[1] = $tret[2];
            $tret[4] = $tret[3];
        }

        // Visibility
        for ($i = 4; $i >= 0; $i--) {
            if ($tret[$i] == 0) {
                continue;
            }

            $attr = [];
            if (EclipseCalculator::eclipseHow($tret[$i], $ipl, $starname, $ifl, $geopos[0], $geopos[1], $geopos[2], $attr, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }

            if ($attr[6] > 0) {
                $retflag |= Constants::SE_ECL_VISIBLE;
                switch ($i) {
                    case 0: $retflag |= Constants::SE_ECL_MAX_VISIBLE; break;
                    case 1: $retflag |= Constants::SE_ECL_1ST_VISIBLE; break;
                    case 2: $retflag |= Constants::SE_ECL_2ND_VISIBLE; break;
                    case 3: $retflag |= Constants::SE_ECL_3RD_VISIBLE; break;
                    case 4: $retflag |= Constants::SE_ECL_4TH_VISIBLE; break;
                }
            }
        }

        // Not visible - retry
        if (!($retflag & Constants::SE_ECL_VISIBLE)) {
            if ($oneTry) {
                $tret[0] = $tjd + $direction;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        // Rise/set times
        $tjdr = 0;
        $tjds = 0;
        $retc = swe_rise_trans(
            $tret[1] - 0.1,
            $ipl,
            $starname,
            $iflag,
            Constants::SE_CALC_RISE | Constants::SE_BIT_DISC_BOTTOM,
            $geopos,
            0,
            0,
            null,
            $tjdr,
            $serr
        );

        if ($retc === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        if ($retc >= 0) {
            $retc = swe_rise_trans(
                $tret[1] - 0.1,
                $ipl,
                $starname,
                $iflag,
                Constants::SE_CALC_SET | Constants::SE_BIT_DISC_BOTTOM,
                $geopos,
                0,
                0,
                null,
                $tjds,
                $serr
            );

            if ($retc === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
        }

        if ($retc >= 0) {
            if ($tjdr > $tret[1] && $tjdr < $tret[4]) {
                $tret[5] = $tjdr;
            }
            if ($tjds > $tret[1] && $tjds < $tret[4]) {
                $tret[6] = $tjds;
            }
        }

        // Daylight flags
        $retc = swe_rise_trans($tret[1], Constants::SE_SUN, null, $iflag, Constants::SE_CALC_RISE, $geopos, 0, 0, null, $tjdr, $serr);
        if ($retc === Constants::SE_ERR) return Constants::SE_ERR;

        if ($retc >= 0) {
            $retc = swe_rise_trans($tret[1], Constants::SE_SUN, null, $iflag, Constants::SE_CALC_SET, $geopos, 0, 0, null, $tjds, $serr);
            if ($retc === Constants::SE_ERR) return Constants::SE_ERR;
        }

        if ($retc >= 0 && $tjds < $tjdr) {
            $retflag |= Constants::SE_ECL_OCC_BEG_DAYLIGHT;
        }

        $retc = swe_rise_trans($tret[4], Constants::SE_SUN, null, $iflag, Constants::SE_CALC_RISE, $geopos, 0, 0, null, $tjdr, $serr);
        if ($retc === Constants::SE_ERR) return Constants::SE_ERR;

        if ($retc >= 0) {
            $retc = swe_rise_trans($tret[4], Constants::SE_SUN, null, $iflag, Constants::SE_CALC_SET, $geopos, 0, 0, null, $tjds, $serr);
            if ($retc === Constants::SE_ERR) return Constants::SE_ERR;
        }

        if ($retc >= 0 && $tjds < $tjdr) {
            $retflag |= Constants::SE_ECL_OCC_END_DAYLIGHT;
        }

        return $retflag;
    }

    private static function findZero(float $y00, float $y11, float $y2, float $dx, &$dxret, &$dxret2): int
    {
        $c = $y11;
        $b = ($y2 - $y00) / 2.0;
        $a = ($y2 + $y00) / 2.0 - $c;

        if ($b * $b - 4 * $a * $c < 0) {
            return Constants::SE_ERR;
        }

        $x1 = (-$b + sqrt($b * $b - 4 * $a * $c)) / 2 / $a;
        $x2 = (-$b - sqrt($b * $b - 4 * $a * $c)) / 2 / $a;

        $dxret = ($x1 - 1) * $dx;
        $dxret2 = ($x2 - 1) * $dx;

        return 0;
    }

    private static function findMaximum(float $y00, float $y11, float $y2, float $dx, &$dxret, &$yret): int
    {
        $c = $y11;
        $b = ($y2 - $y00) / 2.0;
        $a = ($y2 + $y00) / 2.0 - $c;
        $x = -$b / 2 / $a;
        $y = (4 * $a * $c - $b * $b) / 4 / $a;
        $dxret = ($x - 1) * $dx;
        $yret = $y;

        return 0;
    }
}
