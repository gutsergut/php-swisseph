<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;

/**
 * Lunar eclipse search with location visibility
 *
 * Port from swecl.c:3633-3728
 */
class LunarEclipseWhenLocFunctions
{
    /**
     * Find next lunar eclipse visible from given location
     *
     * Searches for lunar eclipse visible from a specific geographic location.
     * Returns visibility flags and adjusted contact times based on moon rise/set.
     *
     * @param float $tjdStart Start time for search (JD UT)
     * @param int $ifl Ephemeris flag
     * @param array $geopos Geographic position [longitude, latitude, altitude_meters]
     * @param array &$tret Return array for eclipse times (10 elements):
     *   [0] = time of maximum eclipse (or moon rise/set if max not visible)
     *   [1] = (unused)
     *   [2] = time of partial phase begin
     *   [3] = time of partial phase end
     *   [4] = time of totality begin
     *   [5] = time of totality end
     *   [6] = time of penumbral phase begin (or 0 if moon rises during eclipse)
     *   [7] = time of penumbral phase end (or 0 if moon sets during eclipse)
     *   [8] = time of moon rise (if during eclipse)
     *   [9] = time of moon set (if during eclipse)
     * @param array &$attr Return array for eclipse attributes (20 elements)
     * @param int $backward 1 = search backward, 0 = forward
     * @param string &$serr Error message
     * @return int Eclipse type flags + visibility flags, or SE_ERR
     */
    public static function when(
        float $tjdStart,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward,
        string &$serr
    ): int {
        return self::lunEclipseWhenLoc($tjdStart, $ifl, $geopos, $tret, $attr, $backward, $serr);
    }

    /**
     * Internal implementation of swe_lun_eclipse_when_loc
     *
     * Port from swecl.c:3633-3728
     *
     * @param float $tjdStart Start time (JD UT)
     * @param int $ifl Ephemeris flag
     * @param array $geopos [longitude, latitude, altitude_meters]
     * @param array &$tret Eclipse times [10]
     * @param array &$attr Eclipse attributes [20]
     * @param int $backward 1=backward, 0=forward
     * @param string &$serr Error message
     * @return int Eclipse type + visibility flags, or SE_ERR
     */
    private static function lunEclipseWhenLoc(
        float $tjdStart,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward,
        string &$serr
    ): int {
        // Initialize return arrays
        $tret = array_fill(0, 10, 0.0);
        $attr = array_fill(0, 20, 0.0);

        // Validate altitude (swecl.c:3639-3643)
        if ($geopos[2] < Constants::SEI_ECL_GEOALT_MIN || $geopos[2] > Constants::SEI_ECL_GEOALT_MAX) {
            $serr = sprintf(
                "location for eclipses must be between %.0f and %.0f m above sea",
                Constants::SEI_ECL_GEOALT_MIN,
                Constants::SEI_ECL_GEOALT_MAX
            );
            return Constants::SE_ERR;
        }

        // Remove JPL horizon flags (swecl.c:3644)
        $ifl &= ~(Constants::SEFLG_JPLHOR | Constants::SEFLG_JPLHOR_APPROX);

        next_lun_ecl:

        // Find next global lunar eclipse (swecl.c:3646-3648)
        // Initialize local error variable for nested call
        $serrLocal = '';
        $retflag = \swe_lun_eclipse_when($tjdStart, $ifl, 0, $tret, $backward, $serrLocal);
        if ($retflag === Constants::SE_ERR) {
            // Pass error message from nested call to caller
            $serr = $serrLocal ?: "swe_lun_eclipse_when() returned SE_ERR";
            return Constants::SE_ERR;
        }

        // Check visibility of eclipse phases (swecl.c:3649-3676)
        $retflag = 0;
        for ($i = 7; $i >= 0; $i--) {
            if ($i === 1) continue;  // Skip unused index
            if ($tret[$i] === 0.0) continue;

            // Check if moon is above horizon at this phase
            $serrHow = '';
            $retflag2 = \swe_lun_eclipse_how($tret[$i], $ifl, $geopos, $attr, $serrHow);
            if ($retflag2 === Constants::SE_ERR) {
                $serr = $serrHow;
                return Constants::SE_ERR;
            }

            // attr[6] = apparent altitude (swecl.c:3658)
            if ($attr[6] > 0) {  // Moon above horizon
                $retflag |= Constants::SE_ECL_VISIBLE;

                // Set visibility flags for specific phases (swecl.c:3659-3670)
                switch ($i) {
                    case 0:
                        $retflag |= Constants::SE_ECL_MAX_VISIBLE;
                        break;
                    case 2:
                        $retflag |= Constants::SE_ECL_PARTBEG_VISIBLE;
                        break;
                    case 3:
                        $retflag |= Constants::SE_ECL_PARTEND_VISIBLE;
                        break;
                    case 4:
                        $retflag |= Constants::SE_ECL_TOTBEG_VISIBLE;
                        break;
                    case 5:
                        $retflag |= Constants::SE_ECL_TOTEND_VISIBLE;
                        break;
                    case 6:
                        $retflag |= Constants::SE_ECL_PENUMBBEG_VISIBLE;
                        break;
                    case 7:
                        $retflag |= Constants::SE_ECL_PENUMBEND_VISIBLE;
                        break;
                }
            }
        }

        // If eclipse not visible, search for next one (swecl.c:3677-3682)
        if (!($retflag & Constants::SE_ECL_VISIBLE)) {
            if ($backward) {
                $tjdStart = $tret[0] - 25;
            } else {
                $tjdStart = $tret[0] + 25;
            }
            goto next_lun_ecl;
        }

        // Calculate moon rise and set during eclipse (swecl.c:3683-3684)
        $tjdMax = $tret[0];
        $tjdr = 0.0;
        $tjds = 0.0;

        // Moon rise during eclipse period (swecl.c:3685-3686)
        $serrRise = '';
        $retc = \swe_rise_trans(
            $tret[6] - 0.001,
            Constants::SE_MOON,
            null,
            $ifl,
            Constants::SE_CALC_RISE | Constants::SE_BIT_DISC_BOTTOM,
            $geopos,
            0.0,      // atpress (standard pressure)
            0.0,      // attemp (standard temperature)
            0.0,      // horhgt (horizon height)
            $tjdr,
            $serrRise
        );
        if ($retc === Constants::SE_ERR) {
            $serr = $serrRise;
            return Constants::SE_ERR;
        }

        // Moon set during eclipse period (swecl.c:3687-3688)
        if ($retc >= 0) {
            $serrSet = '';
            $retc = \swe_rise_trans(
                $tret[6] - 0.001,
                Constants::SE_MOON,
                null,
                $ifl,
                Constants::SE_CALC_SET | Constants::SE_BIT_DISC_BOTTOM,
                $geopos,
                0.0,      // atpress
                0.0,      // attemp
                0.0,      // horhgt
                $tjds,
                $serrSet
            );
            if ($retc === Constants::SE_ERR) {
                $serr = $serrSet;
                return Constants::SE_ERR;
            }
        }

        // Check if moon is visible during eclipse (swecl.c:3689-3696)
        if ($retc >= 0) {
            // Moon sets before eclipse begins or rises after it ends
            if ($tjds < $tret[6] || ($tjds > $tjdr && $tjdr > $tret[7])) {
                if ($backward) {
                    $tjdStart = $tret[0] - 25;
                } else {
                    $tjdStart = $tret[0] + 25;
                }
                goto next_lun_ecl;
            }

            // Moon rises during eclipse (swecl.c:3697-3707)
            if ($tjdr > $tret[6] && $tjdr < $tret[7]) {
                $tret[6] = 0;  // Penumbral begin not visible
                for ($i = 2; $i <= 5; $i++) {
                    if ($tjdr > $tret[$i]) {
                        $tret[$i] = 0;  // Phase begin not visible
                    }
                }
                $tret[8] = $tjdr;  // Moon rise time
                if ($tjdr > $tret[0]) {
                    $tjdMax = $tjdr;  // Maximum is at moon rise
                }
            }

            // Moon sets during eclipse (swecl.c:3708-3718)
            if ($tjds > $tret[6] && $tjds < $tret[7]) {
                $tret[7] = 0;  // Penumbral end not visible
                for ($i = 2; $i <= 5; $i++) {
                    if ($tjds < $tret[$i]) {
                        $tret[$i] = 0;  // Phase end not visible
                    }
                }
                $tret[9] = $tjds;  // Moon set time
                if ($tjds < $tret[0]) {
                    $tjdMax = $tjds;  // Maximum is at moon set
                }
            }
        }

        // Recalculate eclipse at maximum visible time (swecl.c:3719-3720)
        $tret[0] = $tjdMax;
        $serrMax = '';
        $retflag2 = \swe_lun_eclipse_how($tjdMax, $ifl, $geopos, $attr, $serrMax);
        if ($retflag2 === Constants::SE_ERR) {
            $serr = $serrMax;
            return Constants::SE_ERR;
        }

        // If no eclipse at maximum time, search for next one (swecl.c:3721-3726)
        if ($retflag2 === 0) {
            if ($backward) {
                $tjdStart = $tret[0] - 25;
            } else {
                $tjdStart = $tret[0] + 25;
            }
            goto next_lun_ecl;
        }

        // Combine eclipse type with visibility flags (swecl.c:3727)
        $retflag |= ($retflag2 & Constants::SE_ECL_ALLTYPES_LUNAR);

        return $retflag;
    }
}
