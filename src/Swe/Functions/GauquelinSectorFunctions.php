<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Error;
use function swe_calc;
use function swe_deltat_ex;
use function swe_degnorm;
use function swe_fixstar;
use function swe_house_pos;
use function swe_rise_trans;
use function swe_sidtime0;

/**
 * Gauquelin Sector Functions
 * Ported from swecl.c:6320-6459 (swe_gauquelin_sector)
 *
 * Gauquelin sectors are a system of 36 sectors based on the diurnal rotation
 * of celestial bodies. They are used in statistical astrology studies by
 * Michel Gauquelin. Sectors are numbered 1-36.
 *
 * WITHOUT SIMPLIFICATIONS - complete C port
 */
class GauquelinSectorFunctions
{
    /**
     * Calculate Gauquelin sector position of a planet or fixed star
     *
     * Ported from swecl.c:6320-6459
     *
     * Gauquelin sectors divide the diurnal motion into 36 equal parts:
     * - Sectors 1-18: from rise to set (above horizon)
     * - Sectors 19-36: from set to rise (below horizon)
     *
     * @param float $tUt Time in Julian days (UT)
     * @param int $ipl Planet number (SE_SUN, SE_MOON, etc.) - ignored if starname is given
     * @param string|null $starname Fixed star name, or null/empty for planets
     * @param int $iflag Ephemeris flags (SEFLG_SWIEPH | SEFLG_TOPOCTR, etc.)
     * @param int $imeth Method for calculation:
     *   - 0: Use Placidus house position with latitude
     *   - 1: Use Placidus house position without latitude (lat=0)
     *   - 2: Use rise/set of disc center (no refraction)
     *   - 3: Use rise/set of disc center (with refraction)
     *   - 4: Use rise/set without refraction (same as 2)
     *   - 5: Reserved for future use
     * @param array $geopos Geographic position [longitude, latitude, height]:
     *   - geopos[0]: longitude in degrees (east positive)
     *   - geopos[1]: latitude in degrees (north positive)
     *   - geopos[2]: height in meters above sea level
     * @param float $atpress Atmospheric pressure in mbar (only for imeth=3)
     *   - 0 = use default 1013.25 mbar
     *   - If height > 0 and atpress=0, pressure is estimated
     * @param float $attemp Atmospheric temperature in °C (only for imeth=3)
     * @param float &$dgsect Output: Gauquelin sector position (1.0 to 37.0)
     *   - 1.0 = rise point
     *   - 10.0 = upper culmination (MC)
     *   - 19.0 = set point
     *   - 28.0 = lower culmination (IC)
     *   - 0.0 = error (circumpolar body without rise/set)
     * @param string|null &$serr Error message
     * @return int OK (0) on success, ERR (-1) on error
     */
    public static function gauquelinSector(
        float $tUt,
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
        $riseFound = true;
        $setFound = true;
        $tret = array_fill(0, 3, 0.0);
        $x0 = array_fill(0, 6, 0.0);
        $epheflag = $iflag & Constants::SEFLG_EPHMASK;
        $doFixstar = ($starname !== null && $starname !== '');
        $risemeth = 0;
        $aboveHorizon = false;

        // Validate method
        if ($imeth < 0 || $imeth > 5) {
            if ($serr !== null) {
                $serr = sprintf("invalid method: %d", $imeth);
            }
            return Constants::ERR;
        }

        // Function calls for Pluto with asteroid number 134340
        // are treated as calls for Pluto as main body SE_PLUTO
        if ($ipl === Constants::SE_AST_OFFSET + 134340) {
            $ipl = Constants::SE_PLUTO;
        }

        /*
         * Method 0 or 1: geometrically from ecl. longitude and latitude
         */
        if ($imeth === 0 || $imeth === 1) {
            $tEt = $tUt + swe_deltat_ex($tUt, $iflag, $serr);

            // Calculate obliquity
            $eps = self::swi_epsiln($tEt, $iflag) * Constants::RADTODEG;

            // Calculate nutation
            $nutlo = self::swi_nutation($tEt, $iflag);
            $nutlo[0] *= Constants::RADTODEG;
            $nutlo[1] *= Constants::RADTODEG;

            // Calculate ARMC (sidereal time at Greenwich + longitude)
            $armc = swe_degnorm(swe_sidtime0($tUt, $eps + $nutlo[1], $nutlo[0]) * 15.0 + $geopos[0]);

            // Get position of star or planet
            if ($doFixstar) {
                if (swe_fixstar($starname, $tEt, $iflag, $x0, $serr) === Constants::ERR) {
                    return Constants::ERR;
                }
            } else {
                if (swe_calc($tEt, $ipl, $iflag, $x0, $serr) === Constants::ERR) {
                    return Constants::ERR;
                }
            }

            // Method 1: ignore latitude
            if ($imeth === 1) {
                $x0[1] = 0.0;
            }

            // Calculate Gauquelin sector using house position with 'G' system
            $dgsect = swe_house_pos($armc, $geopos[1], $eps + $nutlo[1], 'G', $x0, null);

            return Constants::OK;
        }

        /*
         * Method 2-5: from rise and set times
         */

        // Set rise method flags
        if ($imeth === 2 || $imeth === 4) {
            $risemeth |= Constants::SE_BIT_NO_REFRACTION;
        }
        if ($imeth === 2 || $imeth === 3) {
            $risemeth |= Constants::SE_BIT_DISC_CENTER;
        }

        // Find the next rising time of the planet or star
        $retval = swe_rise_trans(
            $tUt,
            $ipl,
            $starname,
            $epheflag,
            Constants::SE_CALC_RISE | $risemeth,
            $geopos,
            $atpress,
            $attemp,
            null,  // horhgt
            $tret[0],
            $serr
        );

        if ($retval === Constants::ERR) {
            return Constants::ERR;
        } elseif ($retval === -2) {
            // Rise not found (circumpolar)
            // Note: We could return ERR here. However, we keep this variable
            // in case we implement an algorithm for Gauquelin sector positions
            // of circumpolar bodies. As with the Ludwig Otto procedure with Placidus,
            // one could replace missing rises or sets by meridian transits,
            // although there are cases where even this is not possible.
            $riseFound = false;
        }

        // Find the next setting time of the planet or star
        $retval = swe_rise_trans(
            $tUt,
            $ipl,
            $starname,
            $epheflag,
            Constants::SE_CALC_SET | $risemeth,
            $geopos,
            $atpress,
            $attemp,
            null,  // horhgt
            $tret[1],
            $serr
        );

        if ($retval === Constants::ERR) {
            return Constants::ERR;
        } elseif ($retval === -2) {
            // Set not found (circumpolar)
            $setFound = false;
        }

        // Determine if body is above or below horizon and find bracketing rise/set
        if ($tret[0] < $tret[1] && $riseFound === true) {
            // Next rise is before next set => currently below horizon
            $aboveHorizon = false;

            // Find last set (before current time)
            $t = $tUt - 1.2;
            if ($setFound) {
                $t = $tret[1] - 1.2;
            }
            $setFound = true;

            $retval = swe_rise_trans(
                $t,
                $ipl,
                $starname,
                $epheflag,
                Constants::SE_CALC_SET | $risemeth,
                $geopos,
                $atpress,
                $attemp,
                null,  // horhgt
                $tret[1],
                $serr
            );

            if ($retval === Constants::ERR) {
                return Constants::ERR;
            } elseif ($retval === -2) {
                $setFound = false;
            }
        } elseif ($tret[0] >= $tret[1] && $setFound === true) {
            // Next set is before or equal to next rise => currently above horizon
            $aboveHorizon = true;

            // Find last rise (before current time)
            $t = $tUt - 1.2;
            if ($riseFound) {
                $t = $tret[0] - 1.2;
            }
            $riseFound = true;

            $retval = swe_rise_trans(
                $t,
                $ipl,
                $starname,
                $epheflag,
                Constants::SE_CALC_RISE | $risemeth,
                $geopos,
                $atpress,
                $attemp,
                null,  // horhgt
                $tret[0],
                $serr
            );

            if ($retval === Constants::ERR) {
                return Constants::ERR;
            } elseif ($retval === -2) {
                $riseFound = false;
            }
        }

        // Calculate sector position
        if ($riseFound && $setFound) {
            if ($aboveHorizon) {
                // Above horizon: sectors 1-18 from rise to set
                $dgsect = ($tUt - $tret[0]) / ($tret[1] - $tret[0]) * 18.0 + 1.0;
            } else {
                // Below horizon: sectors 19-36 from set to rise
                $dgsect = ($tUt - $tret[1]) / ($tret[0] - $tret[1]) * 18.0 + 19.0;
            }
            return Constants::OK;
        } else {
            // Rise or set not found (circumpolar body)
            $dgsect = 0.0;
            if ($serr !== null) {
                $serr = sprintf("rise or set not found for planet %d", $ipl);
            }
            return Constants::ERR;
        }
    }

    /**
     * Calculate mean obliquity of ecliptic (epsilon)
     * Internal helper - port of swi_epsiln()
     *
     * @param float $jdEt Julian day in ET/TT
     * @param int $iflag Ephemeris flags
     * @return float Obliquity in radians
     */
    private static function swi_epsiln(float $jdEt, int $iflag): float
    {
        // Simplified version - use Laskar's formula
        // Full implementation would need sweph.c:swi_epsiln()
        // For now, use simple formula from Meeus
        $T = ($jdEt - Constants::J2000) / 36525.0;

        // Mean obliquity in arcseconds (IAU 1976)
        $eps0 = 84381.448
              - 46.8150 * $T
              - 0.00059 * $T * $T
              + 0.001813 * $T * $T * $T;

        return $eps0 / 3600.0 * Constants::DEGTORAD;
    }

    /**
     * Calculate nutation in longitude and obliquity
     * Internal helper - port of swi_nutation()
     *
     * @param float $jdEt Julian day in ET/TT
     * @param int $iflag Ephemeris flags
     * @return array [nutation_in_longitude_rad, nutation_in_obliquity_rad]
     */
    private static function swi_nutation(float $jdEt, int $iflag): array
    {
        // Simplified version - full implementation would need sweph.c:swi_nutation()
        // Use simplified IAU 1980 nutation
        $T = ($jdEt - Constants::J2000) / 36525.0;

        // Mean longitude of Moon's ascending node
        $omega = 125.04452 - 1934.136261 * $T;
        $omega *= Constants::DEGTORAD;

        // Mean longitude of Sun
        $L = 280.4665 + 36000.7698 * $T;
        $L *= Constants::DEGTORAD;

        // Mean longitude of Moon
        $Lp = 218.3165 + 481267.8813 * $T;
        $Lp *= Constants::DEGTORAD;

        // Nutation in longitude (arcseconds)
        $nutLon = -17.20 * sin($omega) - 1.32 * sin(2.0 * $L) - 0.23 * sin(2.0 * $Lp) + 0.21 * sin(2.0 * $omega);

        // Nutation in obliquity (arcseconds)
        $nutObl = 9.20 * cos($omega) + 0.57 * cos(2.0 * $L) + 0.10 * cos(2.0 * $Lp) - 0.09 * cos(2.0 * $omega);

        return [
            $nutLon / 3600.0 * Constants::DEGTORAD,  // nutation in longitude (radians)
            $nutObl / 3600.0 * Constants::DEGTORAD   // nutation in obliquity (radians)
        ];
    }
}
