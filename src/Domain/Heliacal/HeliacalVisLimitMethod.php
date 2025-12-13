<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

use Swisseph\Constants;
use Swisseph\Swe;

/**
 * Heliacal event computation via Visual Limiting Magnitude method
 * Full port from swehel.c lines 3043-3334 (get_acronychal_day, heliacal_ut_vis_lim, moon_event_vis_lim)
 *
 * WITHOUT SIMPLIFICATIONS - complete C algorithm:
 * - VLM method using sky brightness and object magnitude
 * - Acronychal rising/setting detection via solar altitude thresholds
 * - Heliacal details: t_first, t_optimum, t_last visibility
 * - Moon event handling with sunrise/sunset corrections
 * - Full integration with time_limit_invisible and time_optimum_visibility
 */
final class HeliacalVisLimitMethod
{
    /**
     * Get acronychal rising or setting day
     * Port from swehel.c:3043-3105 (get_acronychal_day)
     *
     * Finds the day when object rises/sets opposite to the sun
     * (solar altitude around -12° during astronomical twilight).
     *
     * @param float $tjd Starting JD in UT
     * @param array $dgeo Geographic position [lon, lat, height_m]
     * @param array $datm Atmospheric conditions [pressure_mbar, temp_C, RH, VR_km]
     * @param array $dobs Observer parameters [age, SN, etc.]
     * @param string $ObjectName Object name
     * @param int $helflag Calculation flags
     * @param int $TypeEvent Event type: 3=acronychal rising, 4=acronychal setting
     * @param float &$thel Output: JD of acronychal event
     * @param string|null &$serr Error message
     * @return int OK or ERR
     */
    public static function get_acronychal_day(
        float $tjd,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectName,
        int $helflag,
        int $TypeEvent,
        float &$thel,
        ?string &$serr = null
    ): int {
        $tret = 0.0;
        $tret_dark = 0.0;
        $darr = array_fill(0, 30, 0.0);
        $dtret = 999.0;
        $direct = 0;
        $is_rise_or_set = 0;

        $ipl = HeliacalMagnitude::DeterObject($ObjectName);
        $helflag |= Constants::SE_HELFLAG_VISLIM_PHOTOPIC;

        if ($TypeEvent === 3 || $TypeEvent === 5) {
            $is_rise_or_set = Constants::SE_CALC_RISE;
            $direct = -1;
        } else {
            $is_rise_or_set = Constants::SE_CALC_SET;
            $direct = 1;
        }

        $dtret = 999.0;
        while (abs($dtret) > 0.5 / 1440.0) {
            $tjd += 0.7 * $direct;
            if ($direct < 0) {
                $tjd -= 1;
            }

            $retval = HeliacalGeometry::my_rise_trans($tjd, $ipl, $ObjectName, $is_rise_or_set, $helflag, $dgeo, $datm, $tjd, $serr);
            if ($retval === Constants::ERR) {
                return Constants::ERR;
            }

            $retval = HeliacalArcusVisionis::swe_vis_limit_mag($tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag, $darr, $serr);
            if ($retval === Constants::ERR) {
                return Constants::ERR;
            }

            while ($darr[0] < $darr[7]) {
                $tjd += 10.0 / 1440.0 * (-$direct);
                $retval = HeliacalArcusVisionis::swe_vis_limit_mag($tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag, $darr, $serr);
                if ($retval === Constants::ERR) {
                    return Constants::ERR;
                }
            }

            $retval = HeliacalPhenomena::time_limit_invisible(
                $tjd,
                $dgeo,
                $datm,
                $dobs,
                $ObjectName,
                $helflag | Constants::SE_HELFLAG_VISLIM_DARK,
                $direct,
                $tret_dark,
                $serr
            );
            if ($retval === Constants::ERR) {
                return Constants::ERR;
            }

            $retval = HeliacalPhenomena::time_limit_invisible(
                $tjd,
                $dgeo,
                $datm,
                $dobs,
                $ObjectName,
                $helflag | Constants::SE_HELFLAG_VISLIM_NOMOON,
                $direct,
                $tret,
                $serr
            );
            if ($retval === Constants::ERR) {
                return Constants::ERR;
            }

            $dtret = abs($tret - $tret_dark);
        }

        if (HeliacalGeometry::azalt_cart($tret, $dgeo, $datm, 'sun', $helflag, $darr, $serr) === Constants::ERR) {
            return Constants::ERR;
        }

        $thel = $tret;
        if ($darr[1] < -12) {
            $serr = sprintf('acronychal rising/setting not available, %f', $darr[1]);
            return Constants::OK;
        } else {
            $serr = sprintf('solar altitude, %f', $darr[1]);
        }

        return Constants::OK;
    }

    /**
     * Heliacal event calculation using Visual Limiting Magnitude method
     * Port from swehel.c:3163-3247 (heliacal_ut_vis_lim)
     *
     * Main VLM algorithm:
     * 1. Find conjunction with Sun (or ascensio obliqua for stars)
     * 2. Find heliacal day using get_heliacal_day
     * 3. Calculate detailed visibility times (first/optimum/last)
     *
     * @param float $tjd_start Starting JD in UT
     * @param array $dgeo Geographic position [lon, lat, height_m]
     * @param array $datm Atmospheric conditions [pressure_mbar, temp_C, RH, VR_km]
     * @param array $dobs Observer parameters [age, SN, etc.]
     * @param string $ObjectName Object name
     * @param int $TypeEventIn Event type: 1=heliacal rising, 2=heliacal setting, 3=acronychal rising, 4=acronychal setting
     * @param int $helflag Calculation flags
     * @param array &$dret Output array [0]=JD of event, [1]=t_optimum, [2]=t_last, [3-9]=reserved
     * @param string|null &$serr_ret Error message
     * @return int OK, -2 (not found), or ERR
     */
    public static function heliacal_ut_vis_lim(
        float $tjd_start,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectName,
        int $TypeEventIn,
        int $helflag,
        array &$dret,
        ?string &$serr_ret = null
    ): int {
        $d = 0.0;
        $darr = array_fill(0, 10, 0.0);
        $direct = 1.0;
        $tjd = 0.0;
        $tday = 0.0;
        $retval = Constants::OK;
        $TypeEvent = $TypeEventIn;
        $serr = '';

        for ($i = 0; $i < 10; $i++) {
            $dret[$i] = 0.0;
        }
        $dret[0] = $tjd_start;

        $ipl = HeliacalMagnitude::DeterObject($ObjectName);

        if ($ipl === Constants::SE_MERCURY) {
            $tjd = $tjd_start - 30;
        } else {
            $tjd = $tjd_start - 50; // -50 makes sure no event is missed
        }

        $helflag2 = $helflag;

        // heliacal event
        if ($ipl === Constants::SE_MERCURY || $ipl === Constants::SE_VENUS || $TypeEvent <= 2) {
            if ($ipl === -1) {
                // find date when star rises with sun (cosmic rising)
                $retval = HeliacalAscensional::get_asc_obl_with_sun($tjd, $ipl, $ObjectName, $helflag, $TypeEvent, 0, $dgeo, $tjd, $serr);
                if ($retval !== Constants::OK) {
                    goto swe_heliacal_err; // retval may be -2 or ERR
                }
            } else {
                // find date of conjunction of object with sun
                $retval = HeliacalAscensional::find_conjunct_sun($tjd, $ipl, $helflag, $TypeEvent, $tjd, $serr);
                if ($retval === Constants::ERR) {
                    goto swe_heliacal_err;
                }
            }
            // find the day and minute on which the object becomes visible
            $retval = HeliacalAscensional::get_heliacal_day($tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag2, $TypeEvent, $tday, $serr);
            if ($retval !== Constants::OK) {
                goto swe_heliacal_err;
            }
        // acronychal event
        } else {
            $retval = HeliacalAscensional::get_asc_obl_with_sun($tjd, $ipl, $ObjectName, $helflag, $TypeEvent, 0, $dgeo, $tjd, $serr);
            if ($retval !== Constants::OK) {
                goto swe_heliacal_err;
            }

            $tday = $tjd;
            $retval = self::get_acronychal_day($tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag2, $TypeEvent, $tday, $serr);
            if ($retval !== Constants::OK) {
                goto swe_heliacal_err;
            }
        }

        $dret[0] = $tday;

        if (!($helflag & Constants::SE_HELFLAG_NO_DETAILS)) {
            // more precise event times for
            // - morning first, evening last
            // - venus and mercury's evening first and morning last
            if ($ipl === Constants::SE_MERCURY || $ipl === Constants::SE_VENUS || $TypeEvent <= 2) {
                [$retval, $dret_details] = HeliacalPhenomena::get_heliacal_details($tday, $dgeo, $datm, $dobs, $ObjectName, $TypeEvent, $helflag2, $serr);
                if ($retval === Constants::ERR) {
                    goto swe_heliacal_err;
                }
                // Update dret with detailed times
                $dret = $dret_details;
            }
        }

        swe_heliacal_err:
        if ($serr_ret !== null && $serr !== '') {
            $serr_ret = $serr;
        }
        return $retval;
    }

    /**
     * Moon event calculation using Visual Limiting Magnitude method
     * Port from swehel.c:3249-3334 (moon_event_vis_lim)
     *
     * Finds lunar crescent visibility using VLM method:
     * 1. Find conjunction with Sun
     * 2. Find heliacal day
     * 3. Find t_optimum, t_first, t_last visibility
     * 4. Correct for sunrise/sunset if moon visible during day
     *
     * @param float $tjdstart Starting JD in UT
     * @param array $dgeo Geographic position [lon, lat, height_m]
     * @param array $datm Atmospheric conditions [pressure_mbar, temp_C, RH, VR_km]
     * @param array $dobs Observer parameters [age, SN, etc.]
     * @param int $TypeEvent Event type: 3=evening first, 4=morning last
     * @param int $helflag Calculation flags
     * @param array &$dret Output array [0]=t_first, [1]=t_optimum, [2]=t_last
     * @param string|null &$serr_ret Error message
     * @return int OK or ERR
     */
    public static function moon_event_vis_lim(
        float $tjdstart,
        array $dgeo,
        array $datm,
        array $dobs,
        int $TypeEvent,
        int $helflag,
        array &$dret,
        ?string &$serr_ret = null
    ): int {
        $tjd = 0.0;
        $trise = 0.0;
        $serr = '';
        $ObjectName = 'moon';
        $ipl = Constants::SE_MOON;
        $direct = 0;

        $dret[0] = $tjdstart; // will be returned in error case

        if ($TypeEvent === 1 || $TypeEvent === 2) {
            $serr_ret = 'error: the moon has no morning first or evening last';
            return Constants::ERR;
        }

        $helflag2 = $helflag;
        $helflag2 &= ~Constants::SE_HELFLAG_HIGH_PRECISION;

        // check Synodic/phase Period
        $tjd = $tjdstart - 30; // -50 makes sure no event is missed

        $retval = HeliacalAscensional::find_conjunct_sun($tjd, $ipl, $helflag, $TypeEvent, $tjd, $serr);
        if ($retval === Constants::ERR) {
            return Constants::ERR;
        }

        // find the day and minute on which the object becomes visible
        $retval = HeliacalAscensional::get_heliacal_day($tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag2, $TypeEvent, $tjd, $serr);
        if ($retval !== Constants::OK) {
            goto moon_event_err;
        }
        $dret[0] = $tjd;

        // find next optimum visibility
        $retval = HeliacalPhenomena::time_optimum_visibility($tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag, $tjd, $serr);
        if ($retval === Constants::ERR) {
            goto moon_event_err;
        }
        $dret[1] = $tjd;

        // find moment of becoming visible
        $direct = 1;
        if ($TypeEvent === 4) {
            $direct = -1;
        }

        $retval = HeliacalPhenomena::time_limit_invisible($tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag, $direct, $tjd, $serr);
        if ($retval === Constants::ERR) {
            goto moon_event_err;
        }
        $dret[2] = $tjd;

        // find moment of end of visibility
        $direct *= -1;
        $retval = HeliacalPhenomena::time_limit_invisible($dret[1], $dgeo, $datm, $dobs, $ObjectName, $helflag, $direct, $tjd, $serr);
        $dret[0] = $tjd;
        if ($retval === Constants::ERR) {
            goto moon_event_err;
        }

        // if the moon is visible before sunset, we return sunset as start time
        if ($TypeEvent === 3) {
            $retval = HeliacalGeometry::my_rise_trans($tjd, Constants::SE_SUN, '', Constants::SE_CALC_SET, $helflag, $dgeo, $datm, $trise, $serr);
            if ($retval === Constants::ERR) {
                return Constants::ERR;
            }
            if ($trise < $dret[1]) {
                $dret[0] = $trise;
            }
        // if the moon is visible after sunrise, we return sunrise as end time
        } else {
            $retval = HeliacalGeometry::my_rise_trans($dret[1], Constants::SE_SUN, '', Constants::SE_CALC_RISE, $helflag, $dgeo, $datm, $trise, $serr);
            if ($retval === Constants::ERR) {
                return Constants::ERR;
            }
            if ($dret[0] > $trise) {
                $dret[0] = $trise;
            }
        }

        // correct order of the three times:
        if ($TypeEvent === 4) {
            $tjd = $dret[0];
            $dret[0] = $dret[2];
            $dret[2] = $tjd;
        }

        moon_event_err:
        if ($serr_ret !== null && $serr !== '') {
            $serr_ret = $serr;
        }
        return $retval;
    }
}
