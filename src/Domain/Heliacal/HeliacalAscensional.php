<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

use Swisseph\Constants;
use function abs;
use function asin;
use function fabs;
use function floor;
use function sprintf;
use function strcmp;
use function strcpy;
use function tan;

/**
 * Ascensional difference calculations for heliacal events.
 *
 * Implements calculations based on ascensio obliqua (oblique ascension)
 * for determining heliacal rising and setting times.
 *
 * Source: swehel.c lines 2452-2920
 */
final class HeliacalAscensional
{
    /**
     * Times of superior/inferior conjunction (reference epochs).
     *
     * Format: [sup_conj, inf_conj] for Mercury/Venus,
     *         [conjunction, opposition] for Mars+
     *
     * Source: swehel.c lines 2569-2577
     */
    private const TCON = [
        0.0, 0.0,           // SE_ECL_NUT (unused)
        0.0, 0.0,           // Sun (unused)
        2451550.0, 2451550.0,  // Moon
        2451604.0, 2451670.0,  // Mercury
        2451980.0, 2452280.0,  // Venus
        2451727.0, 2452074.0,  // Mars
        2451673.0, 2451877.0,  // Jupiter
        2451675.0, 2451868.0,  // Saturn
        2451581.0, 2451768.0,  // Uranus
        2451568.0, 2451753.0,  // Neptune
    ];

    /**
     * Calculate ascensio obliqua (oblique ascension).
     *
     * Oblique ascension is the right ascension adjusted for latitude:
     * AO = RA ± arcsin(tan(lat) * tan(dec))
     *
     * For descensio obliqua, use + sign; for ascensio, use - sign.
     *
     * @param float $tjd Julian day (TT)
     * @param int $ipl Planet number (or -1 for star)
     * @param string $star Star name (if ipl == -1)
     * @param int $iflag Calculation flags
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param bool $desc_obl TRUE for descensio obliqua, FALSE for ascensio
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, asc_obl] - status: OK/ERR/-2, asc_obl in degrees
     *
     * Notes:
     * - Returns -2 if object is circumpolar (cannot calculate)
     * - Uses equatorial coordinates (RA, Dec)
     *
     * Source: swehel.c lines 2452-2482
     */
    public static function get_asc_obl(
        float $tjd,
        int $ipl,
        string $star,
        int $iflag,
        array $dgeo,
        bool $desc_obl,
        ?string &$serr
    ): array {
        $epheflag = $iflag & (Constants::SEFLG_JPLEPH | Constants::SEFLG_SWIEPH | Constants::SEFLG_MOSEPH);

        // Get equatorial coordinates
        if ($ipl === -1) {
            // Note: would need swe_fixstar() implementation
            // For now, return error
            $serr = "swe_fixstar() not yet implemented";
            return [Constants::ERR, 0.0];
        } else {
            // Note: would need swe_calc() implementation
            // For now, return error
            $serr = "swe_calc() not yet implemented";
            return [Constants::ERR, 0.0];
        }

        // Check for circumpolar object
        $adp = tan($dgeo[1] * HeliacalConstants::DEGTORAD) * tan($x[1] * HeliacalConstants::DEGTORAD);
        if (fabs($adp) > 1.0) {
            if ($star !== '' && $star !== null) {
                $s = $star;
            } else {
                // Note: would need swe_get_planet_name()
                $s = "object";
            }
            $serr = sprintf("%s is circumpolar, cannot calculate heliacal event", $s);
            return [-2, 0.0];
        }

        $adp = asin($adp) / HeliacalConstants::DEGTORAD;

        if ($desc_obl) {
            $daop = $x[0] + $adp;  // Descensio obliqua
        } else {
            $daop = $x[0] - $adp;  // Ascensio obliqua
        }

        // Normalize to 0-360°
        $daop = HeliacalUtils::swe_degnorm($daop);

        return [Constants::OK, $daop];
    }

    /**
     * Calculate difference of ascensio obliqua between Sun and object.
     *
     * Returns the angular difference (in degrees) between the oblique
     * ascensions of Sun and object. For acronychal events, the object's
     * oblique ascension is taken in the opposite direction and 180° is
     * subtracted.
     *
     * @param float $tjd Julian day (TT)
     * @param int $ipl Planet number (or -1 for star)
     * @param string $star Star name (if ipl == -1)
     * @param int $iflag Calculation flags
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param bool $desc_obl TRUE for descensio obliqua, FALSE for ascensio
     * @param bool $is_acronychal TRUE for acronychal rising/setting
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, dsunpl] - status: OK/ERR/-2, dsunpl: difference in degrees
     *
     * Source: swehel.c lines 2521-2543
     */
    public static function get_asc_obl_diff(
        float $tjd,
        int $ipl,
        string $star,
        int $iflag,
        array $dgeo,
        bool $desc_obl,
        bool $is_acronychal,
        ?string &$serr
    ): array {
        // Ascensio obliqua of Sun
        [$retval, $aosun] = self::get_asc_obl($tjd, Constants::SE_SUN, "", $iflag, $dgeo, $desc_obl, $serr);
        if ($retval !== Constants::OK) {
            return [$retval, 0.0];
        }

        // For acronychal events, flip the direction
        if ($is_acronychal) {
            $desc_obl = !$desc_obl;
        }

        // Ascensio obliqua of object
        [$retval, $aopl] = self::get_asc_obl($tjd, $ipl, $star, $iflag, $dgeo, $desc_obl, $serr);
        if ($retval !== Constants::OK) {
            return [$retval, 0.0];
        }

        $dsunpl = HeliacalUtils::swe_degnorm($aosun - $aopl);

        if ($is_acronychal) {
            $dsunpl = HeliacalUtils::swe_degnorm($dsunpl - 180.0);
        }

        if ($dsunpl > 180.0) {
            $dsunpl -= 360.0;
        }

        return [Constants::OK, $dsunpl];
    }

    /**
     * Find date of conjunction with Sun.
     *
     * Finds the next conjunction (0°) or opposition (180°) with the Sun
     * after tjd_start. Uses synodic period and iterative refinement.
     *
     * Algorithm:
     * 1. Get reference epoch from TCON table
     * 2. Calculate approximate date using synodic period
     * 3. Refine using Newton's method until ds < 0.5°
     *
     * @param float $tjd_start Starting Julian day (UT)
     * @param int $ipl Planet number
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param int $TypeEvent Event type (1-4)
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, tjd] - status: OK/ERR, tjd: conjunction time (JD UT)
     *
     * Notes:
     * - For Mercury/Venus: TypeEvent 1,2 = inferior conj, 3,4 = superior conj
     * - For Mars+: TypeEvent 1,2 = conjunction, 3,4 = opposition
     *
     * Source: swehel.c lines 2579-2602
     */
    public static function find_conjunct_sun(
        float $tjd_start,
        int $ipl,
        int $helflag,
        int $TypeEvent,
        ?string &$serr
    ): array {
        $epheflag = $helflag & (Constants::SEFLG_JPLEPH | Constants::SEFLG_SWIEPH | Constants::SEFLG_MOSEPH);

        $daspect = 0.0;
        if ($ipl >= Constants::SE_MARS && $TypeEvent >= 3) {
            $daspect = 180.0; // Opposition
        }

        // Get reference epoch from table
        $i = (int) ((($TypeEvent - 1) / 2) + $ipl * 2);
        $tjd0 = self::TCON[$i];

        // Calculate approximate conjunction date
        $dsynperiod = HeliacalPhenomena::get_synodic_period($ipl);
        $tjdcon = $tjd0 + floor(($tjd_start - $tjd0) / $dsynperiod + 1) * $dsynperiod;

        // Refine using Newton's method
        $ds = 100.0;
        while ($ds > 0.5) {
            // Note: would need swe_calc() with SEFLG_SPEED
            // For now, simplified
            $serr = "swe_calc() not yet implemented";
            return [Constants::ERR, $tjdcon];

            // Original algorithm:
            // $ds = swe_degnorm($x[0] - $xs[0] - $daspect);
            // if ($ds > 180) $ds -= 360;
            // $tjdcon -= $ds / ($x[3] - $xs[3]);
        }

        return [Constants::OK, $tjdcon];
    }

    /**
     * Find date when object and Sun have same ascensio obliqua.
     *
     * This is the key function for heliacal events based on the
     * ascensio obliqua method. It finds when the object rises/sets
     * at the same time as the Sun (cosmical rising/setting).
     *
     * Algorithm:
     * 1. Coarse search (10-day steps) to find sign change
     * 2. Fine search (bisection) to refine to 0.00001°
     *
     * @param float $tjd_start Starting Julian day (UT)
     * @param int $ipl Planet number (or -1 for star)
     * @param string $star Star name (if ipl == -1)
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param int $evtyp Event type (SE_EVENING_LAST, SE_MORNING_FIRST, etc.)
     * @param float $dperiod Maximum search period (days, 0 = unlimited)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, tjdret] - status: OK/ERR/-2, tjdret: event time (JD UT)
     *
     * Notes:
     * - Returns -2 if event not found within dperiod
     * - evtyp: 1=morning first, 2=evening last, 3=evening first, 4=morning last
     * - SE_ACRONYCHAL_RISING/SETTING also supported
     *
     * Source: swehel.c lines 2604-2684
     */
    public static function get_asc_obl_with_sun(
        float $tjd_start,
        int $ipl,
        string $star,
        int $helflag,
        int $evtyp,
        float $dperiod,
        array $dgeo,
        ?string &$serr
    ): array {
        $is_acronychal = false;
        $epheflag = $helflag & (Constants::SEFLG_JPLEPH | Constants::SEFLG_SWIEPH | Constants::SEFLG_MOSEPH);

        $desc_obl = false;
        $retro = false;

        // Determine parameters based on event type
        if ($evtyp === Constants::SE_EVENING_LAST || $evtyp === Constants::SE_EVENING_FIRST) {
            $desc_obl = true;
        }

        if ($evtyp === Constants::SE_MORNING_FIRST || $evtyp === Constants::SE_EVENING_LAST) {
            $retro = true;
        }

        if ($evtyp === Constants::SE_ACRONYCHAL_RISING) {
            $desc_obl = true;
        }

        if ($evtyp === Constants::SE_ACRONYCHAL_RISING || $evtyp === Constants::SE_ACRONYCHAL_SETTING) {
            $is_acronychal = true;
            if ($ipl !== Constants::SE_MOON) {
                $retro = true;
            }
        }

        // Coarse search: find approximate date
        $tjd = $tjd_start;
        $dsunpl_save = -999999999.0;

        [$retval, $dsunpl] = self::get_asc_obl_diff($tjd, $ipl, $star, $epheflag, $dgeo, $desc_obl, $is_acronychal, $serr);
        if ($retval !== Constants::OK) {
            return [$retval, 0.0];
        }

        $daystep = 20.0;
        $i = 0;

        while (
            $dsunpl_save === -999999999.0 ||
            fabs($dsunpl) + fabs($dsunpl_save) > 180.0 ||
            ($retro && !($dsunpl_save < 0 && $dsunpl >= 0)) ||
            (!$retro && !($dsunpl_save >= 0 && $dsunpl < 0))
        ) {
            $i++;
            if ($i > 5000) {
                $serr = "loop in get_asc_obl_with_sun() (1)";
                return [Constants::ERR, 0.0];
            }

            $dsunpl_save = $dsunpl;
            $tjd += 10.0;

            if ($dperiod > 0 && $tjd - $tjd_start > $dperiod) {
                return [-2, 0.0];
            }

            [$retval, $dsunpl] = self::get_asc_obl_diff($tjd, $ipl, $star, $epheflag, $dgeo, $desc_obl, $is_acronychal, $serr);
            if ($retval !== Constants::OK) {
                return [$retval, 0.0];
            }
        }

        // Fine search: bisection to 0.00001°
        $tjd_start = $tjd - $daystep;
        $daystep /= 2.0;
        $tjd = $tjd_start + $daystep;

        [$retval, $dsunpl_test] = self::get_asc_obl_diff($tjd, $ipl, $star, $epheflag, $dgeo, $desc_obl, $is_acronychal, $serr);
        if ($retval !== Constants::OK) {
            return [$retval, 0.0];
        }

        $i = 0;
        while (fabs($dsunpl) > 0.00001) {
            $i++;
            if ($i > 5000) {
                $serr = "loop in get_asc_obl_with_sun() (2)";
                return [Constants::ERR, 0.0];
            }

            if ($dsunpl_save * $dsunpl_test >= 0) {
                $dsunpl_save = $dsunpl_test;
                $tjd_start = $tjd;
            } else {
                $dsunpl = $dsunpl_test;
            }

            $daystep /= 2.0;
            $tjd = $tjd_start + $daystep;

            [$retval, $dsunpl_test] = self::get_asc_obl_diff($tjd, $ipl, $star, $epheflag, $dgeo, $desc_obl, $is_acronychal, $serr);
            if ($retval !== Constants::OK) {
                return [$retval, 0.0];
            }
        }

        return [Constants::OK, $tjd];
    }

    /**
     * Find heliacal day when object first becomes visible.
     *
     * This is the main search algorithm for heliacal events using the
     * visual limiting magnitude method. It searches day by day from
     * tjd_start, checking at each sunrise/sunset if VLM > ObjectMag.
     *
     * Algorithm:
     * 1. Determine search direction and parameters based on event type and planet
     * 2. Loop through days (step size varies by planet)
     * 3. At each day's sunrise/sunset, check if object is visible
     * 4. Refine to minute precision when visibility changes
     * 5. Move away from sunset if needed (vis_limit_mag has strange behavior there)
     *
     * @param float $tjd Starting Julian day (UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param string $ObjectName Object name
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param int $TypeEvent Event type (1-4)
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, thel] - status: OK/ERR/-2, thel: heliacal time (JD UT)
     *
     * Notes:
     * - Search parameters vary by planet:
     *   * Moon: 16 days, 1-day steps
     *   * Mercury: 60 days, 5-day steps
     *   * Venus: 300 days, 5-15 day steps
     *   * Mars+: 300-400 days, 15-20 day steps
     *   * Stars: depends on magnitude
     * - Returns -2 if event not found within search period
     * - TypeEvent: 1=morning first, 2=evening last, 3=evening first, 4=morning last
     *
     * Source: swehel.c lines 2757-2920
     */
    public static function get_heliacal_day(
        float $tjd,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectName,
        int $helflag,
        int $TypeEvent,
        ?string &$serr
    ): array {
        $ipl = HeliacalMagnitude::DeterObject($ObjectName);

        // Determine search direction
        $is_rise_or_set = 0;
        $direct_day = 0.0;
        $direct_time = 0.0;

        switch ($TypeEvent) {
            case 1: // Morning first
                $is_rise_or_set = Constants::SE_CALC_RISE;
                $direct_day = 1.0;
                $direct_time = -1.0;
                break;
            case 2: // Evening last
                $is_rise_or_set = Constants::SE_CALC_SET;
                $direct_day = -1.0;
                $direct_time = 1.0;
                break;
            case 3: // Evening first
                $is_rise_or_set = Constants::SE_CALC_SET;
                $direct_day = 1.0;
                $direct_time = 1.0;
                break;
            case 4: // Morning last
                $is_rise_or_set = Constants::SE_CALC_RISE;
                $direct_day = -1.0;
                $direct_time = -1.0;
                break;
        }

        // Determine search parameters by planet
        $tfac = 1.0;

        switch ($ipl) {
            case Constants::SE_MOON:
                $ndays = 16;
                $daystep = 1.0;
                break;
            case Constants::SE_MERCURY:
                $ndays = 60;
                $daystep = 5.0;
                $tfac = 5.0;
                break;
            case Constants::SE_VENUS:
                $ndays = 300;
                $tjd -= 30.0 * $direct_day;
                $daystep = 5.0;
                if ($TypeEvent >= 3) {
                    $daystep = 15.0;
                    $tfac = 3.0;
                }
                break;
            case Constants::SE_MARS:
                $ndays = 400;
                $daystep = 15.0;
                $tfac = 5.0;
                break;
            case Constants::SE_SATURN:
                $ndays = 300;
                $daystep = 20.0;
                $tfac = 5.0;
                break;
            case -1: // Star
                $ndays = 300;
                // Note: would need call_swe_fixstar_mag()
                $dmag = 2.0; // Assume magnitude 2
                $daystep = 15.0;
                $tfac = 10.0;
                if ($dmag > 2) {
                    $daystep = 15.0;
                }
                if ($dmag < 0) {
                    $tfac = 3.0;
                }
                break;
            default:
                $ndays = 300;
                $daystep = 15.0;
                $tfac = 3.0;
                break;
        }

        $tend = $tjd + $ndays * $direct_day;
        $retval_old = -2;

        for ($tday = $tjd, $i = 0;
             ($direct_day > 0 && $tday < $tend) || ($direct_day < 0 && $tday > $tend);
             $tday += $daystep * $direct_day, $i++) {

            if ($i > 0) {
                $tday -= 0.3 * $direct_day;
            }

            // Get Sun rise/set time
            [$retval, $tret] = HeliacalGeometry::my_rise_trans(
                $tday, Constants::SE_SUN, "", $is_rise_or_set, $helflag, $dgeo, $datm, $serr
            );
            if ($retval === Constants::ERR) {
                return [Constants::ERR, 0.0];
            }

            // Sun does not rise: try next day
            if ($retval === -2) {
                $retval_old = $retval;
                continue;
            }

            // Check visibility at Sun rise/set time
            [$retval, $darr] = HeliacalArcusVisionis::swe_vis_limit_mag(
                $tret, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
            );
            if ($retval === Constants::ERR) {
                return [Constants::ERR, 0.0];
            }

            // Object appeared above horizon: reduce daystep
            if ($retval_old === -2 && $retval >= 0 && $daystep > 1) {
                $retval_old = $retval;
                $tday -= $daystep * $direct_day;
                $daystep = 1.0;
                if ($ipl >= Constants::SE_MARS || $ipl === -1) {
                    $daystep = 5.0;
                }
                continue;
            }

            $retval_old = $retval;

            // Object below horizon: try next day
            if ($retval === -2) {
                continue;
            }

            $vdelta = $darr[0] - $darr[7];

            // Find minute of object's becoming visible
            $div = 1440.0; // Minutes per day
            $vd = -1.0;
            $visible_at_sunsetrise = 1;

            while ($retval !== -2 && ($vd = $darr[0] - $darr[7]) < 0) {
                $visible_at_sunsetrise = 0;

                if ($vd < -1.0) {
                    $tret += 5.0 / $div * $direct_time * $tfac;
                } elseif ($vd < -0.5) {
                    $tret += 2.0 / $div * $direct_time * $tfac;
                } elseif ($vd < -0.1) {
                    $tret += 1.0 / $div * $direct_time * $tfac;
                } else {
                    $tret += 1.0 / $div * $direct_time;
                }

                [$retval, $darr] = HeliacalArcusVisionis::swe_vis_limit_mag(
                    $tret, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
                );
                if ($retval === Constants::ERR) {
                    return [Constants::ERR, 0.0];
                }
            }

            // Move away from sunset if needed (vis_limit_mag has strange behavior there)
            if ($visible_at_sunsetrise) {
                for ($j = 0; $j < 10; $j++) {
                    [$retval2, $darr2] = HeliacalArcusVisionis::swe_vis_limit_mag(
                        $tret + 1.0 / $div * $direct_time, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
                    );

                    if ($retval2 >= 0 && $darr2[0] - $darr2[7] > $vd) {
                        $vd = $darr2[0] - $darr2[7];
                        $tret += 1.0 / $div * $direct_time;
                        $darr = $darr2;
                    }
                }
            }

            $vdelta = $darr[0] - $darr[7];

            // Object is visible: save time of appearance
            if ($vdelta > 0) {
                if (($ipl >= Constants::SE_MARS || $ipl === -1) && $daystep > 1) {
                    $tday -= $daystep * $direct_day;
                    $daystep = 1.0;
                } else {
                    return [Constants::OK, $tret];
                }
            }
        }

        $serr = "heliacal event does not happen";
        return [-2, 0.0];
    }
}
