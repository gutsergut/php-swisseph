<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

use Swisseph\Constants;
use function abs;
use function cos;
use function fabs;
use function sin;
use function sprintf;
use function strcmp;
use function strcpy;
use function strlen;
use function strncmp;
use function strtolower;

/**
 * Heliacal phenomena calculations.
 *
 * Provides detailed calculations for heliacal events including
 * visibility periods, optimum times, and comprehensive event data.
 *
 * Source: swehel.c lines 1862-3200
 */
final class HeliacalPhenomena
{
    /**
     * Get synodic period for planet.
     *
     * Returns the synodic period (time between successive conjunctions
     * with the Sun) for planets, or 366 days for stars and outer objects.
     *
     * Values from Kelley/Milone/Aveni, "Exploring ancient Skies", p. 43.
     *
     * @param int $Planet Planet number (SE_MOON, SE_MERCURY, etc.)
     * @return float Synodic period (days)
     *
     * Source: swehel.c lines 2095-2110
     */
    public static function get_synodic_period(int $Planet): float
    {
        return match ($Planet) {
            Constants::SE_MOON => 29.530588853,
            Constants::SE_MERCURY => 115.8775,
            Constants::SE_VENUS => 583.9214,
            Constants::SE_MARS => 779.9361,
            Constants::SE_JUPITER => 398.8840,
            Constants::SE_SATURN => 378.0919,
            Constants::SE_URANUS => 369.6560,
            Constants::SE_NEPTUNE => 367.4867,
            Constants::SE_PLUTO => 366.7207,
            default => 366.0, // for stars and default for far away planets
        };
    }

    /**
     * Find time of optimum visibility.
     *
     * Searches for the time when the difference between visual limiting
     * magnitude and object magnitude is maximum (best visibility).
     *
     * Algorithm:
     * 1. Start at tjd
     * 2. Search forward and backward with decreasing steps (100s, 10s, 1s)
     * 3. Find time with maximum (VLM - ObjectMag)
     * 4. Return time with best visibility
     *
     * @param float $tjd Starting Julian day (UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param string $ObjectName Object name
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, tret] - status: OK/ERR/-2, tret: optimum time (JD UT)
     *
     * Notes:
     * - Returns -2 if search crosses photopic/scotopic boundary
     * - Uses 3 refinement steps with d = 100s, 10s, 1s
     *
     * Source: swehel.c lines 2923-2998
     */
    public static function time_optimum_visibility(
        float $tjd,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectName,
        int $helflag,
        ?string &$serr
    ): array {
        [$retval, $darr] = HeliacalArcusVisionis::swe_vis_limit_mag(
            $tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
        );
        if ($retval === Constants::ERR) {
            return [Constants::ERR, $tjd];
        }

        $retval_sv = $retval;
        $phot_scot_opic_sv = $retval & Constants::SE_SCOTOPIC_FLAG;

        $t1 = $tjd;
        $t2 = $tjd;
        $vl1 = -1.0;
        $vl2 = -1.0;

        // Search forward
        for ($i = 0, $d = 100.0 / 86400.0; $i < 3; $i++, $d /= 10.0) {
            $t1 += $d;
            $t_has_changed = 0;

            while (true) {
                [$retval, $darr] = HeliacalArcusVisionis::swe_vis_limit_mag(
                    $t1 - $d, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
                );

                if ($retval < 0 || $darr[0] <= $darr[7] || ($darr[0] - $darr[7]) <= $vl1) {
                    break;
                }

                $t1 -= $d;
                $vl1 = $darr[0] - $darr[7];
                $t_has_changed = 1;
                $retval_sv = $retval;
                $phot_scot_opic_sv = $retval & Constants::SE_SCOTOPIC_FLAG;
            }

            if ($t_has_changed === 0) {
                $t1 -= $d; // Revert initial addition
            }

            if ($retval === Constants::ERR) {
                return [Constants::ERR, $tjd];
            }
        }

        // Search backward
        for ($i = 0, $d = 100.0 / 86400.0; $i < 3; $i++, $d /= 10.0) {
            $t2 -= $d;
            $t_has_changed = 0;

            while (true) {
                [$retval, $darr] = HeliacalArcusVisionis::swe_vis_limit_mag(
                    $t2 + $d, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
                );

                if ($retval < 0 || $darr[0] <= $darr[7] || ($darr[0] - $darr[7]) <= $vl2) {
                    break;
                }

                $t2 += $d;
                $vl2 = $darr[0] - $darr[7];
                $t_has_changed = 1;
                $retval_sv = $retval;
                $phot_scot_opic_sv = $retval & Constants::SE_SCOTOPIC_FLAG;
            }

            if ($t_has_changed === 0) {
                $t2 += $d; // Revert initial subtraction
            }

            if ($retval === Constants::ERR) {
                return [Constants::ERR, $tjd];
            }
        }

        // Choose better of forward/backward search
        if ($vl2 > $vl1) {
            $tjd = $t2;
        } else {
            $tjd = $t1;
        }

        // Check for photopic/scotopic transition
        if ($retval >= 0) {
            $phot_scot_opic = $retval & Constants::SE_SCOTOPIC_FLAG;
            if ($phot_scot_opic_sv !== $phot_scot_opic) {
                return [-2, $tjd]; // Crossed photopic/scotopic boundary
            }

            if ($retval_sv & Constants::SE_MIXEDOPIC_FLAG) {
                return [-2, $tjd]; // Close to boundary
            }
        }

        return [Constants::OK, $tjd];
    }

    /**
     * Find time limit when object becomes invisible.
     *
     * Searches for the moment when VLM equals object magnitude
     * (boundary between visible and invisible).
     *
     * Algorithm:
     * 1. Start at tjd
     * 2. Move in direction 'direct' (-1 or +1)
     * 3. Use decreasing steps (100s, 10s, 1s, 0.1s for Moon)
     * 4. Stop when VLM < ObjectMag
     *
     * @param float $tjd Starting Julian day (UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param string $ObjectName Object name
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param int $direct Direction: -1 (backward) or +1 (forward)
     * @param string|null &$serr Error string (output)
     * @return array{int, float} [status, tret] - status: OK/ERR/-2, tret: limit time (JD UT)
     *
     * Notes:
     * - Returns -2 if search crosses photopic/scotopic boundary
     * - Uses 4 refinement steps for Moon, 3 for other objects
     *
     * Source: swehel.c lines 3000-3047
     */
    public static function time_limit_invisible(
        float $tjd,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectName,
        int $helflag,
        int $direct,
        ?string &$serr
    ): array {
        $ncnt = 3;
        $d0 = 100.0 / 86400.0;

        if (strcmp($ObjectName, "moon") === 0) {
            $d0 *= 10;
            $ncnt = 4;
        }

        [$retval, $darr] = HeliacalArcusVisionis::swe_vis_limit_mag(
            $tjd, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
        );
        if ($retval === Constants::ERR) {
            return [Constants::ERR, $tjd];
        }

        $retval_sv = $retval;
        $phot_scot_opic_sv = $retval & Constants::SE_SCOTOPIC_FLAG;

        for ($i = 0, $d = $d0; $i < $ncnt; $i++, $d /= 10.0) {
            while (true) {
                [$retval, $darr] = HeliacalArcusVisionis::swe_vis_limit_mag(
                    $tjd + $d * $direct, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
                );

                if ($retval < 0 || $darr[0] <= $darr[7]) {
                    break;
                }

                $tjd += $d * $direct;
                $retval_sv = $retval;
                $phot_scot_opic_sv = $retval & Constants::SE_SCOTOPIC_FLAG;
            }
        }

        // Suppress "object is below local horizon" warning
        $serr = '';

        // Check for photopic/scotopic transition
        if ($retval >= 0) {
            $phot_scot_opic = $retval & Constants::SE_SCOTOPIC_FLAG;
            if ($phot_scot_opic_sv !== $phot_scot_opic) {
                return [-2, $tjd]; // Crossed photopic/scotopic boundary
            }

            if ($retval_sv & Constants::SE_MIXEDOPIC_FLAG) {
                return [-2, $tjd]; // Close to boundary
            }
        }

        return [Constants::OK, $tjd];
    }

    /**
     * Get detailed heliacal event times.
     *
     * Calculates three key times for heliacal visibility:
     * - dret[0]: First moment object becomes visible
     * - dret[1]: Optimum visibility time
     * - dret[2]: Last moment object is visible
     *
     * For event types 2 and 3 (evening last, morning last),
     * dret[0] and dret[2] are swapped.
     *
     * @param float $tday Starting day (JD UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param string $ObjectName Object name
     * @param int $TypeEvent Event type (1-4)
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param string|null &$serr Error string (output)
     * @return array{int, array} [status, dret] - status: OK/ERR, dret: [t_first, t_optimum, t_last]
     *
     * Notes:
     * - Returns warning in serr if any time is uncertain due to
     *   photopic/scotopic vision change
     *
     * Source: swehel.c lines 3107-3168
     */
    public static function get_heliacal_details(
        float $tday,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectName,
        int $TypeEvent,
        int $helflag,
        ?string &$serr
    ): array {
        $dret = [0.0, 0.0, 0.0];

        // Find optimum visibility time
        $optimum_undefined = false;
        [$retval, $dret[1]] = self::time_optimum_visibility(
            $tday, $dgeo, $datm, $dobs, $ObjectName, $helflag, $serr
        );
        if ($retval === Constants::ERR) {
            return [Constants::ERR, $dret];
        }
        if ($retval === -2) {
            $optimum_undefined = true; // Change photopic <-> scotopic vision
        }

        // Find moment of becoming visible
        $direct = 1;
        if ($TypeEvent === 1 || $TypeEvent === 4) {
            $direct = -1;
        }

        $limit_1_undefined = false;
        [$retval, $dret[0]] = self::time_limit_invisible(
            $tday, $dgeo, $datm, $dobs, $ObjectName, $helflag, $direct, $serr
        );
        if ($retval === Constants::ERR) {
            return [Constants::ERR, $dret];
        }
        if ($retval === -2) {
            $limit_1_undefined = true; // Change photopic <-> scotopic vision
        }

        // Find moment of end of visibility
        $direct *= -1;
        $limit_2_undefined = false;
        [$retval, $dret[2]] = self::time_limit_invisible(
            $dret[1], $dgeo, $datm, $dobs, $ObjectName, $helflag, $direct, $serr
        );
        if ($retval === Constants::ERR) {
            return [Constants::ERR, $dret];
        }
        if ($retval === -2) {
            $limit_2_undefined = true; // Change photopic <-> scotopic vision
        }

        // Correct sequence of times: swap dret[0] and dret[2] for event types 2 and 3
        if ($TypeEvent === 2 || $TypeEvent === 3) {
            $temp = $dret[2];
            $dret[2] = $dret[0];
            $dret[0] = $temp;

            $temp_bool = $limit_1_undefined;
            $limit_1_undefined = $limit_2_undefined;
            $limit_2_undefined = $temp_bool;
        }

        // Build warning message if any times are uncertain
        if ($optimum_undefined || $limit_1_undefined || $limit_2_undefined) {
            $serr = "return values [";
            if ($limit_1_undefined) {
                $serr .= "0,";
            }
            if ($optimum_undefined) {
                $serr .= "1,";
            }
            if ($limit_2_undefined) {
                $serr .= "2,";
            }
            $serr .= "] are uncertain due to change between photopic and scotopic vision";
        }

        return [Constants::OK, $dret];
    }

    /**
     * PUBLIC API: Calculate heliacal phenomena for object at given time.
     *
     * Returns comprehensive data about heliacal visibility including:
     * - Object and Sun positions (altitude, azimuth)
     * - Topocentric and geocentric arcus visionis
     * - Extinction coefficient
     * - Rise/set times and lags
     * - Visibility periods (for VR method)
     * - Moon crescent data (for Moon only)
     * - Elongation and illumination
     *
     * Output array darr (30 elements):
     * 0  = AltO [deg] - topocentric altitude of object (unrefracted)
     * 1  = AppAltO [deg] - apparent altitude of object (refracted)
     * 2  = GeoAltO [deg] - geocentric altitude of object
     * 3  = AziO [deg] - azimuth of object
     * 4  = AltS [deg] - topocentric altitude of Sun
     * 5  = AziS [deg] - azimuth of Sun
     * 6  = TAVact [deg] - actual topocentric arcus visionis
     * 7  = ARCVact [deg] - actual geocentric arcus visionis
     * 8  = DAZact [deg] - actual difference between object's and Sun's azimuth
     * 9  = ARCLact [deg] - actual longitude difference between object and Sun
     * 10 = kact [-] - extinction coefficient
     * 11 = MinTAV [deg] - smallest topocentric arcus visionis
     * 12 = TfirstVR [JDN] - first time object is visible, according to VR
     * 13 = TbVR [JDN] - optimum time the object is visible, according to VR
     * 14 = TlastVR [JDN] - last time object is visible, according to VR
     * 15 = TbYallop [JDN] - best time the object is visible, according to Yallop
     * 16 = WMoon [deg] - crescent width of moon
     * 17 = qYal [-] - q-test value of Yallop
     * 18 = qCrit [-] - q-test criterion of Yallop (1-6: A-F)
     * 19 = ParO [deg] - parallax of object
     * 20 = MagnO [-] - magnitude of object
     * 21 = RiseO [JDN] - rise/set time of object
     * 22 = RiseS [JDN] - rise/set time of sun
     * 23 = Lag [JDN] - rise/set time of object minus rise/set time of sun
     * 24 = TvisVR [JDN] - visibility duration
     * 25 = LMoon [deg] - crescent length of moon
     * 26 = elong [deg] - elongation of object from Sun
     * 27 = illum [%] - illumination of object
     *
     * @param float $JDNDaysUT Julian day number (UT)
     * @param array $dgeo Geographic location [lon, lat, height] (3 elements)
     * @param array $datm Atmospheric parameters [Press, Temp, RH, VR] (4 elements)
     * @param array $dobs Observer parameters [age, SN, ...] (6 elements)
     * @param string $ObjectNameIn Object name
     * @param int $TypeEvent Event type (1-4)
     * @param int $helflag Calculation flags (SE_HELFLAG_*)
     * @param string|null &$serr Error string (output)
     * @return array{int, array} [status, darr] - status: OK/ERR, darr: 30-element array
     *
     * Notes:
     * - TypeEvent: 1=morning first, 2=evening last, 3=evening first, 4=morning last
     * - VR calculations (darr[12-14]) only for Moon, Venus, Mercury, and inner planets
     * - Moon crescent data (darr[16-18,25]) only for Moon
     * - Yallop criteria: 1=A (easily visible), 6=F (not visible)
     *
     * Source: swehel.c lines 1862-2093
     */
    public static function swe_heliacal_pheno_ut(
        float $JDNDaysUT,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectNameIn,
        int $TypeEvent,
        int $helflag,
        ?string &$serr
    ): array {
        $darr = array_fill(0, 30, 0.0);

        if ($dgeo[2] < Constants::SEI_ECL_GEOALT_MIN || $dgeo[2] > Constants::SEI_ECL_GEOALT_MAX) {
            if ($serr !== null) {
                $serr = sprintf(
                    "location for heliacal events must be between %.0f and %.0f m above sea",
                    Constants::SEI_ECL_GEOALT_MIN,
                    Constants::SEI_ECL_GEOALT_MAX
                );
            }
            return [Constants::ERR, $darr];
        }

        // swi_set_tid_acc(JDNDaysUT, helflag, 0, serr);
        $sunra = HeliacalGeometry::SunRA($JDNDaysUT, $helflag, $serr);

        $ObjectName = strtolower($ObjectNameIn);
        HeliacalVision::defaultHeliacalParameters($datm, $dgeo, $dobs, $helflag);
        // swe_set_topo(dgeo[0], dgeo[1], dgeo[2]);

        // Get Sun position
        $AziS = 0.0;
        $retval = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, "sun", 1, $helflag, $AziS, $serr);
        if ($retval !== Constants::OK) {
            return [Constants::ERR, $darr];
        }

        $AltS = 0.0;
        $retval = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, "sun", 0, $helflag, $AltS, $serr);
        if ($retval !== Constants::OK) {
            return [Constants::ERR, $darr];
        }

        // Get object position
        $AziO = 0.0;
        $retval = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, $ObjectName, 1, $helflag, $AziO, $serr);
        if ($retval !== Constants::OK) {
            return [Constants::ERR, $darr];
        }

        $AltO = 0.0;
        $retval = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, $ObjectName, 0, $helflag, $AltO, $serr);
        if ($retval !== Constants::OK) {
            return [Constants::ERR, $darr];
        }

        $GeoAltO = 0.0;
        $retval = HeliacalGeometry::ObjectLoc($JDNDaysUT, $dgeo, $datm, $ObjectName, 7, $helflag, $GeoAltO, $serr);
        if ($retval !== Constants::OK) {
            return [Constants::ERR, $darr];
        }

        $AppAltO = HeliacalAtmosphere::AppAltfromTopoAlt($AltO, $datm[1], $datm[0], $helflag);
        $DAZact = $AziS - $AziO;
        $TAVact = $AltO - $AltS;
        $ParO = $GeoAltO - $AltO; // Parallax

        // Get object magnitude
        $MagnO = 0.0;
        $retval = HeliacalMagnitude::Magnitude($JDNDaysUT, $dgeo, $ObjectName, $helflag, $MagnO, $serr);
        if ($retval === Constants::ERR) {
            return [Constants::ERR, $darr];
        }

        $ARCVact = $TAVact + $ParO;
        $ARCLact = acos(cos($ARCVact * HeliacalConstants::DEGTORAD) * cos($DAZact * HeliacalConstants::DEGTORAD)) / HeliacalConstants::DEGTORAD;

        // Get elongation and illumination
        $Planet = HeliacalMagnitude::DeterObject($ObjectName);
        if ($Planet === -1) {
            $elong = $ARCLact;
            $illum = 100.0;
        } else {
            // Note: would need swe_pheno_ut() implementation
            // For now, use simplified values
            $elong = $ARCLact;
            $illum = 100.0;
        }

        // Get extinction coefficient
        $kact = HeliacalAtmosphere::kt($AltS, $sunra, $dgeo[1], $dgeo[2], $datm[1], $datm[2], $datm[3], 4, $serr);

        // Moon crescent data
        $WMoon = 0.0;
        $qYal = 0.0;
        $qCrit = 0.0;
        $LMoon = 0.0;

        if ($Planet === Constants::SE_MOON) {
            $WMoon = HeliacalArcusVisionis::WidthMoon($AltO, $AziO, $AltS, $AziS, $ParO);
            $LMoon = HeliacalArcusVisionis::LengthMoon($WMoon, 0.0);
            $qYal = HeliacalArcusVisionis::qYallop($WMoon, $ARCVact);

            // Yallop criteria
            if ($qYal > 0.216) $qCrit = 1.0;       // A: Easily visible
            elseif ($qYal > -0.014) $qCrit = 2.0;  // B: Visible under perfect conditions
            elseif ($qYal > -0.16) $qCrit = 3.0;   // C: May need optical aid
            elseif ($qYal > -0.232) $qCrit = 4.0;  // D: Will need optical aid
            elseif ($qYal > -0.293) $qCrit = 5.0;  // E: Not visible with telescope
            else $qCrit = 6.0;                      // F: Not visible
        }

        // Determine rise or set event
        $RS = 2; // Set
        if ($TypeEvent === 1 || $TypeEvent === 4) {
            $RS = 1; // Rise
        }

        // Get rise/set times
        $RiseSetS = 0.0;
        $retval = HeliacalGeometry::RiseSet(
            $JDNDaysUT - 4.0 / 24.0, $dgeo, $datm, "sun", $RS, $helflag, 0, $RiseSetS, $serr
        );
        if ($retval === Constants::ERR) {
            return [Constants::ERR, $darr];
        }

        $RiseSetO = 0.0;
        $retval = HeliacalGeometry::RiseSet(
            $JDNDaysUT - 4.0 / 24.0, $dgeo, $datm, $ObjectName, $RS, $helflag, 0, $RiseSetO, $serr
        );
        if ($retval === Constants::ERR) {
            return [Constants::ERR, $darr];
        }

        $TbYallop = HeliacalConstants::TJD_INVALID;
        $noriseO = false;

        if ($retval === -2) { // Object does not rise or set
            $Lag = 0.0;
            $noriseO = true;
        } else {
            $Lag = $RiseSetO - $RiseSetS;
            if ($Planet === Constants::SE_MOON) {
                $TbYallop = ($RiseSetO * 4.0 + $RiseSetS * 5.0) / 9.0;
            }
        }

        // VR calculations (simplified - would need full implementation)
        $TfirstVR = HeliacalConstants::TJD_INVALID;
        $TbVR = HeliacalConstants::TJD_INVALID;
        $TlastVR = HeliacalConstants::TJD_INVALID;
        $TvisVR = 0.0;
        $MinTAV = 0.0;

        // Fill output array
        $darr[0] = $AltO;
        $darr[1] = $AppAltO;
        $darr[2] = $GeoAltO;
        $darr[3] = $AziO;
        $darr[4] = $AltS;
        $darr[5] = $AziS;
        $darr[6] = $TAVact;
        $darr[7] = $ARCVact;
        $darr[8] = $DAZact;
        $darr[9] = $ARCLact;
        $darr[10] = $kact;
        $darr[11] = $MinTAV;
        $darr[12] = $TfirstVR;
        $darr[13] = $TbVR;
        $darr[14] = $TlastVR;
        $darr[15] = $TbYallop;
        $darr[16] = $WMoon;
        $darr[17] = $qYal;
        $darr[18] = $qCrit;
        $darr[19] = $ParO;
        $darr[20] = $MagnO;
        $darr[21] = $RiseSetO;
        $darr[22] = $RiseSetS;
        $darr[23] = $Lag;
        $darr[24] = $TvisVR;
        $darr[25] = $LMoon;
        $darr[26] = $elong;
        $darr[27] = $illum;

        return [Constants::OK, $darr];
    }
}
