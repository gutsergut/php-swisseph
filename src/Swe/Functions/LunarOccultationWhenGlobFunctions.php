<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Error;
use Swisseph\VectorMath;
use Swisseph\Swe\Eclipses\EclipseCalculator;
use function swe_calc;
use function swe_degnorm;
use function swe_deltat_ex;

/**
 * Global lunar occultation search function
 * Ported from swecl.c:1572-1970 (swe_lun_occult_when_glob)
 *
 * Searches for the next or previous occultation of a planet or fixed star
 * by the Moon, visible from anywhere on Earth.
 *
 * @package Swisseph\Swe\Functions
 */
class LunarOccultationWhenGlobFunctions
{
    /**
     * Find global lunar occultation of planet or star
     * Ported from swecl.c:1572-1970 (swe_lun_occult_when_glob)
     *
     * This function searches for occultations of planets or fixed stars by the Moon.
     * An occultation occurs when the Moon passes in front of a celestial object
     * as seen from Earth.
     *
     * Algorithm:
     * 1. Find rough conjunction in ecliptic longitude between Moon and target
     * 2. Refine time by minimizing angular distance
     * 3. Call eclipse_where() to verify occultation occurs
     * 4. Calculate begin/end times using find_zero()
     * 5. Calculate central line times if applicable
     * 6. Detect annular-total occultations
     *
     * @param float $tjdStart Start time for search (JD in UT)
     * @param int $ipl Planet number (SE_SUN, SE_MOON, SE_MARS, etc.)
     * @param string|null $starname Fixed star name (null for planets)
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param int $ifltype Type of occultation to search for:
     *                      SE_ECL_TOTAL - Total occultations only
     *                      SE_ECL_ANNULAR - Annular occultations only (Sun only)
     *                      SE_ECL_PARTIAL - Partial occultations only
     *                      SE_ECL_CENTRAL - Central occultations only
     *                      SE_ECL_NONCENTRAL - Non-central occultations only
     *                      0 - Any type
     * @param array &$tret Output: times of eclipse phases (JD in UT):
     *                     [0] = time of maximum eclipse
     *                     [1] = time of maximum at local apparent noon (0 if no transit)
     *                     [2] = begin of eclipse
     *                     [3] = end of eclipse
     *                     [4] = begin of totality (0 if not applicable)
     *                     [5] = end of totality (0 if not applicable)
     *                     [6] = begin of center line (0 if not applicable)
     *                     [7] = end of center line (0 if not applicable)
     * @param bool $backward Search backward in time if true
     * @param string|null &$serr Output: error message
     * @return int Occultation type flags (SE_ECL_TOTAL, SE_ECL_ANNULAR, etc.) or 0 if none found
     */
    public static function whenGlob(
        float $tjdStart,
        int $ipl,
        ?string $starname,
        int $ifl,
        int $ifltype,
        array &$tret,
        bool $backward,
        ?string &$serr = null
    ): int {
        // Planet/star diameter in AU (for occultations)
        static $plaDiam = [
            1391978489.9,  // SE_SUN
            0,             // SE_MOON (not used as occulted body)
            0,             // SE_MERCURY (use default)
            0,             // SE_VENUS
            0,             // SE_MARS
            142984000.0,   // SE_JUPITER
            120536000.0,   // SE_SATURN
            51118000.0,    // SE_URANUS
            49528000.0,    // SE_NEPTUNE
            2390000.0,     // SE_PLUTO
        ];

        $retflag = 0;
        $retflag2 = 0;
        $de = 6378.140;  // Earth radius in km
        $tjd = 0.0;
        $tjds = 0.0;
        $twohr = 2.0 / 24.0;
        $tenmin = 10.0 / 24.0 / 60.0;
        $dt1 = 0.0;
        $dt2 = 0.0;
        $dadd2 = 1.0;
        $geopos = array_fill(0, 20, 0.0);
        $direction = 1;
        $dontTimes = false;
        $oneTry = ($backward & Constants::SE_ECL_ONE_TRY) !== 0;

        // Pluto as asteroid 134340 is treated as main body SE_PLUTO
        if ($ipl === Constants::SE_AST_OFFSET + 134340) {
            $ipl = Constants::SE_PLUTO;
        }

        // Filter out non-ephemeris flags
        $ifl &= Constants::SEFLG_EPHMASK;

        // Set timing accuracy (swi_set_tid_acc in C - not critical, skipped)
        // \swi_set_tid_acc($tjdStart, $ifl, 0, $serr);

        $iflag = Constants::SEFLG_EQUATORIAL | $ifl;
        $iflagcart = $iflag | Constants::SEFLG_XYZ;

        $backward = ($backward & 1) !== 0;

        // Validate ifltype parameter
        if ($ifltype === (Constants::SE_ECL_PARTIAL | Constants::SE_ECL_CENTRAL)) {
            $serr = "central partial eclipses do not exist";
            return Constants::SE_ERR;
        }

        // For non-Sun objects, annular occultations don't exist
        if ($ipl !== Constants::SE_SUN) {
            $ifltype2 = $ifltype & ~(Constants::SE_ECL_NONCENTRAL | Constants::SE_ECL_CENTRAL);
            if ($ifltype2 === Constants::SE_ECL_ANNULAR ||
                $ifltype === Constants::SE_ECL_ANNULAR_TOTAL) {
                $serr = "annular occultation do not exist for object $ipl " . ($starname ?? '');
                return Constants::SE_ERR;
            }
        }

        if ($ipl !== Constants::SE_SUN &&
            ($ifltype & (Constants::SE_ECL_ANNULAR | Constants::SE_ECL_ANNULAR_TOTAL))) {
            $ifltype &= ~(Constants::SE_ECL_ANNULAR | Constants::SE_ECL_ANNULAR_TOTAL);
        }

        // Default: search for any type
        if ($ifltype === 0) {
            $ifltype = Constants::SE_ECL_TOTAL | Constants::SE_ECL_PARTIAL |
                       Constants::SE_ECL_NONCENTRAL | Constants::SE_ECL_CENTRAL;
            if ($ipl === Constants::SE_SUN) {
                $ifltype |= Constants::SE_ECL_ANNULAR | Constants::SE_ECL_ANNULAR_TOTAL;
            }
        }

        if ($ifltype & (Constants::SE_ECL_TOTAL | Constants::SE_ECL_ANNULAR |
                        Constants::SE_ECL_ANNULAR_TOTAL)) {
            $ifltype |= Constants::SE_ECL_NONCENTRAL | Constants::SE_ECL_CENTRAL;
        }

        if ($ifltype & Constants::SE_ECL_PARTIAL) {
            $ifltype |= Constants::SE_ECL_NONCENTRAL;
        }

        // Initialize return array
        for ($i = 0; $i <= 9; $i++) {
            $tret[$i] = 0.0;
        }

        if ($backward) {
            $direction = -1;
        }

        $t = $tjdStart;
        $tjd = $t;

        // Main search loop label
        next_try:

        // Get planet/star position (ecliptic coordinates for conjunction search)
        $ls = [];
        if (EclipseCalculator::calcPlanetStar($t, $ipl, $starname, $ifl, $ls, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Fixed stars with ecliptic latitude > 7° or < -7° cannot have occultation
        if (abs($ls[1]) > 7 && $starname !== null && $starname !== '') {
            $serr = sprintf("occultation never occurs: star %s has ecl. lat. %.1f",
                          $starname, $ls[1]);
            return Constants::SE_ERR;
        }

        // Get Moon position
        $lm = [];
        if (swe_calc($t, Constants::SE_MOON, $ifl, $lm, $serr) === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Calculate longitude difference
        $dl = swe_degnorm($ls[0] - $lm[0]);
        if ($direction < 0) {
            $dl -= 360;
        }

        // Get rough conjunction in ecliptic longitude
        $conjIter = 0;
        while (abs($dl) > 0.1) {
            if ($conjIter++ > 10000) {
                $serr = "conjunction search timeout - possible infinite loop";
                return Constants::SE_ERR;
            }
            $t += $dl / 13;
            if (EclipseCalculator::calcPlanetStar($t, $ipl, $starname, $ifl, $ls, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
            if (swe_calc($t, Constants::SE_MOON, $ifl, $lm, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
            $dl = swe_degnorm($ls[0] - $lm[0]);
            if ($dl > 180) {
                $dl -= 360;
            }
        }
        $tjd = $t;

        // Check if latitude difference is too big for an occultation
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

        // Calculate radius of planet disk in AU
        if ($starname !== null && $starname !== '') {
            $drad = 0;
        } elseif ($ipl < count($plaDiam)) {
            $drad = $plaDiam[$ipl] / 2 / Constants::AUNIT;
        } elseif ($ipl > Constants::SE_AST_OFFSET) {
            // Asteroid diameter from swed.ast_diam (km -> m -> AU)
            global $swed;
            $drad = $swed->ast_diam / 2 * 1000 / Constants::AUNIT;
        } else {
            $drad = 0;
        }

        // Find time of maximum eclipse (minimum geocentric angle between Moon and target edges)
        $dtstart = $dadd2;
        $dtdiv = 3;

        $refineIter = 0;
        for ($dt = $dtstart; $dt > 0.0001; $dt /= $dtdiv) {
            if ($refineIter++ > 100) {
                $serr = "refinement loop timeout - possible infinite loop";
                return Constants::SE_ERR;
            }
            $dc = [];
            for ($i = 0, $loopT = $tjd - $dt; $i <= 2; $i++, $loopT += $dt) {
                // Get equatorial positions
                if (EclipseCalculator::calcPlanetStar($loopT, $ipl, $starname, $iflag, $ls, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
                if (swe_calc($loopT, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                // Get Cartesian positions
                $xs = [];
                if (EclipseCalculator::calcPlanetStar($loopT, $ipl, $starname, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
                $xm = [];
                if (swe_calc($loopT, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }

                // Calculate angular distance
                $dc[$i] = acos(VectorMath::dotProductUnit($xs, $xm)) * Constants::RADTODEG;

                // Subtract Moon and planet radii
                $rmoon = asin(Constants::RMOON / $lm[2]) * Constants::RADTODEG;
                $rsun = asin($drad / $ls[2]) * Constants::RADTODEG;
                $dc[$i] -= ($rmoon + $rsun);
            }

            // Find maximum (minimum distance)
            $dtint = 0.0;
            $dctr = 0.0;
            self::findMaximum($dc[0], $dc[1], $dc[2], $dt, $dtint, $dctr);
            $tjd += $dtint + $dt;
        }

        // Convert from ET to UT
        $tjd -= swe_deltat_ex($tjd, $ifl, $serr);
        $tjds = $tjd;

        // Check if occultation occurs
        $dcore = [];
        $retflag = EclipseCalculator::eclipseWhere($tjd, $ipl, $starname, $ifl, $geopos, $dcore, $serr);
        if ($retflag === Constants::SE_ERR) {
            return $retflag;
        }

        $retflag2 = $retflag;

        if ($retflag2 === 0) {
            // No occultation found
            if ($oneTry) {
                $tret[0] = $tjd;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        $tret[0] = $tjd;

        // Check if we went in the wrong direction
        if (($backward && $tret[0] >= $tjdStart - 0.0001) ||
            (!$backward && $tret[0] <= $tjdStart + 0.0001)) {
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        // Verify occultation type
        $retflag = EclipseCalculator::eclipseWhere($tjd, $ipl, $starname, $ifl, $geopos, $dcore, $serr);
        if ($retflag === Constants::SE_ERR) {
            return $retflag;
        }

        if ($retflag === 0) {
            // Can happen with extremely small percentage
            $retflag = Constants::SE_ECL_PARTIAL | Constants::SE_ECL_NONCENTRAL;
            $tret[4] = $tret[5] = $tjd;
            $dontTimes = true;
        }

        // Check if found occultation type matches requested type
        if (!($ifltype & Constants::SE_ECL_NONCENTRAL) && ($retflag & Constants::SE_ECL_NONCENTRAL)) {
            if ($oneTry) {
                $tret[0] = $tjd;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        if (!($ifltype & Constants::SE_ECL_CENTRAL) && ($retflag & Constants::SE_ECL_CENTRAL)) {
            if ($oneTry) {
                $tret[0] = $tjd;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        if (!($ifltype & Constants::SE_ECL_ANNULAR) && ($retflag & Constants::SE_ECL_ANNULAR)) {
            if ($oneTry) {
                $tret[0] = $tjd;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        if (!($ifltype & Constants::SE_ECL_PARTIAL) && ($retflag & Constants::SE_ECL_PARTIAL)) {
            if ($oneTry) {
                $tret[0] = $tjd;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        if (!($ifltype & (Constants::SE_ECL_TOTAL | Constants::SE_ECL_ANNULAR_TOTAL)) &&
            ($retflag & Constants::SE_ECL_TOTAL)) {
            if ($oneTry) {
                $tret[0] = $tjd;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        if ($dontTimes) {
            goto end_search_global;
        }

        // Calculate eclipse phase times
        // n = 0: times of eclipse begin and end
        // n = 1: times of totality begin and end
        // n = 2: times of center line begin and end
        if ($retflag & Constants::SE_ECL_PARTIAL) {
            $o = 0;
        } elseif ($retflag & Constants::SE_ECL_NONCENTRAL) {
            $o = 1;
        } else {
            $o = 2;
        }

        $dta = $twohr;
        $dtb = $tenmin;

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
            } else {
                if ($retflag & Constants::SE_ECL_NONCENTRAL) {
                    continue;
                }
                $i1 = 6;
                $i2 = 7;
            }

            // Initial 3-point sampling
            $dc = [];
            for ($i = 0, $loopT = $tjd - $dta; $i <= 2; $i++, $loopT += $dta) {
                $retflag2 = EclipseCalculator::eclipseWhere($loopT, $ipl, $starname, $ifl, $geopos, $dcore, $serr);
                if ($retflag2 === Constants::SE_ERR) {
                    return $retflag2;
                }

                if ($n === 0) {
                    $dc[$i] = $dcore[4] / 2 + $de / $dcore[5] - $dcore[2];
                } elseif ($n === 1) {
                    $dc[$i] = abs($dcore[3]) / 2 + $de / $dcore[6] - $dcore[2];
                } else {
                    $dc[$i] = $de / $dcore[6] - $dcore[2];
                }
            }

            // Find zero crossings
            self::findZero($dc[0], $dc[1], $dc[2], $dta, $dt1, $dt2);
            $tret[$i1] = $tjd + $dt1 + $dta;
            $tret[$i2] = $tjd + $dt2 + $dta;

            // Refine with 3 iterations
            for ($m = 0, $refineDt = $dtb; $m < 3; $m++, $refineDt /= 3) {
                for ($j = $i1; $j <= $i2; $j += ($i2 - $i1)) {
                    $dc = [];
                    for ($i = 0, $loopT = $tret[$j] - $refineDt; $i < 2; $i++, $loopT += $refineDt) {
                        $retflag2 = EclipseCalculator::eclipseWhere($loopT, $ipl, $starname, $ifl, $geopos, $dcore, $serr);
                        if ($retflag2 === Constants::SE_ERR) {
                            return $retflag2;
                        }

                        if ($n === 0) {
                            $dc[$i] = $dcore[4] / 2 + $de / $dcore[5] - $dcore[2];
                        } elseif ($n === 1) {
                            $dc[$i] = abs($dcore[3]) / 2 + $de / $dcore[6] - $dcore[2];
                        } else {
                            $dc[$i] = $de / $dcore[6] - $dcore[2];
                        }
                    }

                    $dt1 = $dc[1] / (($dc[1] - $dc[0]) / $refineDt);
                    $tret[$j] -= $dt1;
                }
            }
        }

        // Check for annular-total occultations
        if ($retflag & Constants::SE_ECL_TOTAL) {
            $retflag2 = EclipseCalculator::eclipseWhere($tret[0], $ipl, $starname, $ifl, $geopos, $dcore, $serr);
            if ($retflag2 === Constants::SE_ERR) {
                return $retflag2;
            }
            $dc0 = $dcore[0];

            $retflag2 = EclipseCalculator::eclipseWhere($tret[4], $ipl, $starname, $ifl, $geopos, $dcore, $serr);
            if ($retflag2 === Constants::SE_ERR) {
                return $retflag2;
            }
            $dc1 = $dcore[0];

            $retflag2 = EclipseCalculator::eclipseWhere($tret[5], $ipl, $starname, $ifl, $geopos, $dcore, $serr);
            if ($retflag2 === Constants::SE_ERR) {
                return $retflag2;
            }
            $dc2 = $dcore[0];

            // Maximum is always total, check if core shadow becomes zero
            if ($dc0 * $dc1 < 0 || $dc0 * $dc2 < 0) {
                $retflag |= Constants::SE_ECL_ANNULAR_TOTAL;
                $retflag &= ~Constants::SE_ECL_TOTAL;
            }
        }

        // Final type check
        if (!($ifltype & Constants::SE_ECL_TOTAL) && ($retflag & Constants::SE_ECL_TOTAL)) {
            if ($oneTry) {
                $tret[0] = $tjd;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        if (!($ifltype & Constants::SE_ECL_ANNULAR_TOTAL) && ($retflag & Constants::SE_ECL_ANNULAR_TOTAL)) {
            if ($oneTry) {
                $tret[0] = $tjd;
                return 0;
            }
            $t = $tjd + $direction * 20;
            $tjd = $t;
            goto next_try;
        }

        // Calculate time of maximum at local apparent noon
        $k = 2;
        $dcTransit = [];
        for ($i = 0; $i < 2; $i++) {
            $j = $i + $k;
            $tt = $tret[$j] + swe_deltat_ex($tret[$j], $ifl, $serr);

            if (EclipseCalculator::calcPlanetStar($tt, $ipl, $starname, $iflag, $ls, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }
            if (swe_calc($tt, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
                return Constants::SE_ERR;
            }

            $dcTransit[$i] = swe_degnorm($ls[0] - $lm[0]);
            if ($dcTransit[$i] > 180) {
                $dcTransit[$i] -= 360;
            }
        }

        // Check if transit occurs between begin and end
        if ($dcTransit[0] * $dcTransit[1] >= 0) {
            // No transit
            $tret[1] = 0;
        } else {
            // Find transit time
            $tjd = $tjds;
            $dt = 0.1;
            $dt1 = ($tret[3] - $tret[2]) / 2.0;
            if ($dt1 < $dt) {
                $dt = $dt1 / 2.0;
            }

            for ($j = 0; $dt > 0.01; $j++, $dt /= 3) {
                if ($j > 100) {
                    $serr = "transit refinement timeout - possible infinite loop";
                    return Constants::SE_ERR;
                }
                $dcLoop = [];
                for ($i = 0, $loopT = $tjd; $i <= 1; $i++, $loopT -= $dt) {
                    $tt = $loopT + swe_deltat_ex($loopT, $ifl, $serr);

                    if (EclipseCalculator::calcPlanetStar($tt, $ipl, $starname, $iflag, $ls, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }
                    if (swe_calc($tt, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
                        return Constants::SE_ERR;
                    }

                    $dcLoop[$i] = swe_degnorm($ls[0] - $lm[0]);
                    if ($dcLoop[$i] > 180) {
                        $dcLoop[$i] -= 360;
                    }
                }

                $a = ($dcLoop[1] - $dcLoop[0]) / $dt;
                if ($a < 1e-10) {
                    break;
                }
                $dt1 = $dcLoop[0] / $a;
                $tjd += $dt1;
            }

            $tret[1] = $tjd;
        }

        end_search_global:
        return $retflag;
    }

    /**
     * Find maximum of parabola through 3 points
     * Ported from swecl.c:5833-5851 (find_maximum)
     *
     * @param float $y00 Value at x=-dx
     * @param float $y11 Value at x=0
     * @param float $y2 Value at x=+dx
     * @param float $dx Spacing between points
     * @param float &$dxret Output: offset of maximum from center point
     * @param float &$yret Output: value at maximum
     * @return int Always returns 0 (OK)
     */
    private static function findMaximum(
        float $y00,
        float $y11,
        float $y2,
        float $dx,
        float &$dxret,
        float &$yret
    ): int {
        // Port of find_maximum from swecl.c:4133-4146
        $c = $y11;
        $b = ($y2 - $y00) / 2.0;
        $a = ($y2 + $y00) / 2.0 - $c;
        $x = -$b / 2 / $a;
        $y = (4 * $a * $c - $b * $b) / 4 / $a;
        $dxret = ($x - 1) * $dx;
        $yret = $y;

        return 0; // OK
    }

    /**
     * Find zero crossings of parabola through 3 points
     * Ported from swecl.c:5853-5897 (find_zero)
     *
     * @param float $y00 Value at x=-dx
     * @param float $y11 Value at x=0
     * @param float $y2 Value at x=+dx
     * @param float $dx Spacing between points
     * @param float &$dxret Output: offset of first zero from center
     * @param float &$dxret2 Output: offset of second zero from center
     * @return int Always returns 0 (OK)
     */
    private static function findZero(
        float $y00,
        float $y11,
        float $y2,
        float $dx,
        float &$dxret,
        float &$dxret2
    ): int {
        $a = ($y00 + $y2) / 2.0 - $y11;
        $b = ($y2 - $y00) / 2.0;
        $c = $y11;

        if ($b * $b - 4.0 * $a * $c < 0) {
            $dxret = $dxret2 = 0.0;
            return 0;
        }

        $y0 = -$b / 2.0 / $a;
        $s = $y0 * $y0 - $c / $a;

        if ($s < 0) {
            $dxret = $dxret2 = 0.0;
        } else {
            $s = sqrt($s);
            $dxret = $y0 - $s;
            $dxret2 = $y0 + $s;
        }

        return 0; // OK
    }
}

