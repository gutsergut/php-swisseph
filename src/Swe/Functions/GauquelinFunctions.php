<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\DeltaT;
use Swisseph\ErrorCodes;
use Swisseph\Math;
use Swisseph\Nutation;
use Swisseph\Obliquity;

/**
 * Gauquelin sector calculations
 *
 * Port of swe_gauquelin_sector() from swecl.c
 */
final class GauquelinFunctions
{
    /**
     * Calculate Gauquelin sector position for a planet or star
     *
     * Port of swe_gauquelin_sector() from swecl.c lines 6328-6459
     *
     * @param float $t_ut Input time (UT)
     * @param int $ipl Planet number (SE_SUN..SE_PLUTO, SE_EARTH, SE_MOON)
     * @param string|null $starname Star name (currently not supported, must be null or empty)
     * @param int $iflag Flags for ephemeris and SEFLG_TOPOCTR
     * @param int $imeth Method:
     *                   0 = with latitude
     *                   1 = without latitude
     *                   2 = from rise/set without refraction
     *                   3 = from rise/set with refraction
     *                   4 = from rise/set, no refraction, center of disc
     *                   5 = from rise/set, with refraction, center of disc
     * @param array $geopos Geographic position [longitude, latitude, height]
     * @param float $atpress Atmospheric pressure (mbar), only useful with imeth=3/5; 0 = default 1013.25
     * @param float $attemp Atmospheric temperature (°C), only useful with imeth=3/5
     * @param float &$dgsect Return value for Gauquelin sector position (1-36)
     * @param string|null &$serr Error message
     * @return int SE_OK (0) or SE_ERR (-1)
     */
    public static function gauquelinSector(
        float $t_ut,
        int $ipl,
        ?string $starname,
        int $iflag,
        int $imeth,
        array $geopos,
        float $atpress,
        float $attemp,
        float &$dgsect,
        ?string &$serr = null
    ): int {
        $dgsect = 0.0;
        $serr = null;

        // Validate method
        if ($imeth < 0 || $imeth > 5) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                "invalid method: $imeth"
            );
            return Constants::SE_ERR;
        }

        // Check if star is requested
        $do_fixstar = ($starname !== null && $starname !== '');

        // Handle Pluto with asteroid number 134340
        // Treat as main body SE_PLUTO
        if ($ipl === Constants::SE_AST_OFFSET + 134340) {
            $ipl = Constants::SE_PLUTO;
        }

        /*
         * Method 0 or 1: geometrically from ecliptic longitude and latitude
         */
        if ($imeth === 0 || $imeth === 1) {
            // Convert UT to ET
            $t_et = $t_ut + DeltaT::deltaTSecondsFromJd($t_ut) / 86400.0;

            // Get obliquity
            $eps_rad = Obliquity::meanObliquityRadFromJdTT($t_et);
            $eps = Math::radToDeg($eps_rad);

            // Get nutation
            $nutModel = Nutation::selectModelFromFlags($iflag);
            [$nutlo0, $nutlo1] = Nutation::calc($t_et, $nutModel, false);
            $nutlo0 = Math::radToDeg($nutlo0); // nutation in longitude (dpsi)
            $nutlo1 = Math::radToDeg($nutlo1); // nutation in obliquity (deps)

            // Calculate ARMC (Right Ascension of Meridian)
            // armc = swe_degnorm(swe_sidtime0(t_ut, eps + nutlo[1], nutlo[0]) * 15 + geopos[0])
            $sidtime0 = \Swisseph\Sidereal::sidtime0($t_ut, $eps + $nutlo1, $nutlo0);
            $armc = Math::normAngleDeg($sidtime0 * 15.0 + $geopos[0]);

            // Calculate planet or star position
            $x0 = [];
            if ($do_fixstar) {
                if (FixstarFunctions::fixstar($starname, $t_et, $iflag, $x0, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
            } else {
                if (PlanetsFunctions::calc($t_et, $ipl, $iflag, $x0, $serr) < 0) {
                    return Constants::SE_ERR;
                }
            }

            // If method 1, set latitude to 0 (ignore latitude)
            if ($imeth === 1) {
                $x0[1] = 0.0;
            }

            // Calculate house position using Gauquelin system ('G')
            $dgsect = HousesFunctions::housePos(
                $armc,
                $geopos[1],
                $eps + $nutlo1,
                'G',
                $x0,
                $serr
            );

            return Constants::SE_OK;
        }

        /*
         * Method 2-5: from rise and set times
         */
        $rise_found = true;
        $set_found = true;
        $tret = [0.0, 0.0, 0.0];

        // Determine rise/set method flags
        $risemeth = 0;
        if ($imeth === 2 || $imeth === 4) {
            $risemeth |= Constants::SE_BIT_NO_REFRACTION;
        }
        if ($imeth === 2 || $imeth === 3) {
            $risemeth |= Constants::SE_BIT_DISC_CENTER;
        }

        $epheflag = $iflag & Constants::SEFLG_EPHMASK;

        // Find the next rising time
        $trise = 0.0;
        $retval = RiseSetFunctions::riseTrans(
            $t_ut,
            $ipl,
            $starname,
            $epheflag,
            Constants::SE_CALC_RISE | $risemeth,
            $geopos,
            $atpress,
            $attemp,
            null,
            $trise,
            $serr
        );

        if ($retval < 0) {
            return Constants::SE_ERR;
        } elseif ($retval === -2) {
            // Circumpolar body: no rise
            $rise_found = false;
        } else {
            $tret[0] = $trise;
        }

        // Find the next setting time
        $tset = 0.0;
        $retval = RiseSetFunctions::riseTrans(
            $t_ut,
            $ipl,
            $starname,
            $epheflag,
            Constants::SE_CALC_SET | $risemeth,
            $geopos,
            $atpress,
            $attemp,
            null,
            $tset,
            $serr
        );

        if ($retval < 0) {
            return Constants::SE_ERR;
        } elseif ($retval === -2) {
            // Circumpolar body: no set
            $set_found = false;
        } else {
            $tret[1] = $tset;
        }

        // Determine if body is above or below horizon
        $above_horizon = false;

        if ($tret[0] < $tret[1] && $rise_found && $set_found) {
            // Next event is rise, so body is below horizon
            $above_horizon = false;

            // Find last set (go back 1.2 days and search)
            $t = $t_ut - 1.2;
            if ($set_found) {
                $t = $tret[1] - 1.2;
            }
            $tset_prev = 0.0;
            $retval = RiseSetFunctions::riseTrans(
                $t,
                $ipl,
                $starname,
                $epheflag,
                Constants::SE_CALC_SET | $risemeth,
                $geopos,
                $atpress,
                $attemp,
                null,
                $tset_prev,
                $serr
            );

            if ($retval < 0) {
                return Constants::SE_ERR;
            } elseif ($retval === -2) {
                $set_found = false;
            } else {
                $tret[1] = $tset_prev;
                $set_found = true;
            }
        } elseif ($tret[0] >= $tret[1] && $set_found) {
            // Next event is set, so body is above horizon
            $above_horizon = true;

            // Find last rise (go back 1.2 days and search)
            $t = $t_ut - 1.2;
            if ($rise_found) {
                $t = $tret[0] - 1.2;
            }

            $trise_prev = 0.0;
            $retval = RiseSetFunctions::riseTrans(
                $t,
                $ipl,
                $starname,
                $epheflag,
                Constants::SE_CALC_RISE | $risemeth,
                $geopos,
                $atpress,
                $attemp,
                null,
                $trise_prev,
                $serr
            );

            if ($retval < 0) {
                return Constants::SE_ERR;
            } elseif ($retval === -2) {
                $rise_found = false;
            } else {
                $tret[0] = $trise_prev;
                $rise_found = true;
            }
        }

        // Calculate Gauquelin sector
        if ($rise_found && $set_found) {
            if ($above_horizon) {
                // Sectors 1-18: from rise to set
                $dgsect = ($t_ut - $tret[0]) / ($tret[1] - $tret[0]) * 18.0 + 1.0;
            } else {
                // Sectors 19-36: from set to rise
                $dgsect = ($t_ut - $tret[1]) / ($tret[0] - $tret[1]) * 18.0 + 19.0;
            }
            return Constants::SE_OK;
        } else {
            // Circumpolar body: no rise or set found
            $dgsect = 0.0;
            if ($serr !== null) {
                $serr = sprintf("rise or set not found for planet %d", $ipl);
            }
            return Constants::SE_ERR;
        }
    }
}
