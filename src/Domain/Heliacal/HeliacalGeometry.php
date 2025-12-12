<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

use Swisseph\Swe;

/**
 * Geometry and coordinate functions for heliacal calculations
 * Port from swehel.c lines 398-780
 */
class HeliacalGeometry
{
    /**
     * Calculate Sun's right ascension
     * Port from swehel.c:553-597
     *
     * Uses fast approximation or precise calculation based on flag
     *
     * @param float $JDNDaysUT Julian Day Number UT
     * @param int $helflag Heliacal flags
     * @param string &$serr Error message output
     * @return float Sun RA [degrees]
     */
    public static function SunRA(float $JDNDaysUT, int $helflag, string &$serr): float
    {
        static $tjdlast = null;
        static $ralast = null;

        if ($JDNDaysUT == $tjdlast) {
            return $ralast;
        }

        // Precise calculation
        $epheflag = $helflag & (Swe::SEFLG_JPLEPH | Swe::SEFLG_SWIEPH | Swe::SEFLG_MOSEPH);
        $iflag = $epheflag | Swe::SEFLG_EQUATORIAL;
        $iflag |= Swe::SEFLG_NONUT | Swe::SEFLG_TRUEPOS;

        $tjd_tt = $JDNDaysUT + Swe::swe_deltat_ex($JDNDaysUT, $epheflag, $serr);
        $result = Swe::swe_calc($tjd_tt, Swe::SE_SUN, $iflag, $serr);

        if ($result['rc'] != Swe::ERR) {
            $ralast = $result['xx'][0];
            $tjdlast = $JDNDaysUT;
            return $ralast;
        }

        // Fallback to fast approximation if calculation fails
        $date = Swe::swe_revjul($JDNDaysUT, Swe::SE_GREG_CAL);
        $tjdlast = $JDNDaysUT;
        $ralast = Swe::swe_degnorm(($date['month'] + ($date['day'] - 1) / 30.4 - 3.69) * 30);

        return $ralast;
    }

    /**
     * Calculate hour angle
     * Port from swehel.c:662-677
     *
     * From http://star-www.st-and.ac.uk/~fv/webnotes/chapt12.htm
     *
     * @param float $TopoAlt Topocentric altitude [degrees]
     * @param float $TopoDecl Topocentric declination [degrees]
     * @param float $Lat Observer latitude [degrees]
     * @return float Hour angle [hours]
     */
    public static function HourAngle(float $TopoAlt, float $TopoDecl, float $Lat): float
    {
        $Alti = $TopoAlt * HeliacalConstants::DEGTORAD;
        $decli = $TopoDecl * HeliacalConstants::DEGTORAD;
        $Lati = $Lat * HeliacalConstants::DEGTORAD;

        $ha = (sin($Alti) - sin($Lati) * sin($decli)) / cos($Lati) / cos($decli);

        if ($ha < -1) {
            $ha = -1;
        }
        if ($ha > 1) {
            $ha = 1;
        }

        return acos($ha) / HeliacalConstants::DEGTORAD / 15.0;
    }

    /**
     * Calculate object location (altitude, azimuth, RA, Dec)
     * Port from swehel.c:683-735
     *
     * @param float $JDNDaysUT Julian Day Number UT
     * @param array $dgeo Geographic location [longitude, latitude, altitude]
     * @param array $datm Atmospheric parameters [pressure, temperature]
     * @param string $ObjectName Object name
     * @param int $Angle Angle type: 0=TopoAlt, 1=Azi, 2=TopoDec, 3=TopoRA, 4=AppAlt, 5=GeoDec, 6=GeoRA
     * @param int $helflag Heliacal flags
     * @param float &$dret Output: requested angle value [degrees]
     * @param string &$serr Error message output
     * @return int OK or ERR
     */
    public static function ObjectLoc(
        float $JDNDaysUT,
        array $dgeo,
        array $datm,
        string $ObjectName,
        int $Angle,
        int $helflag,
        float &$dret,
        string &$serr
    ): int {
        $epheflag = $helflag & (Swe::SEFLG_JPLEPH | Swe::SEFLG_SWIEPH | Swe::SEFLG_MOSEPH);
        $iflag = Swe::SEFLG_EQUATORIAL | $epheflag;

        if (!($helflag & HeliacalConstants::SE_HELFLAG_HIGH_PRECISION)) {
            $iflag |= Swe::SEFLG_NONUT | Swe::SEFLG_TRUEPOS;
        }

        if ($Angle < 5) {
            $iflag |= Swe::SEFLG_TOPOCTR;
        }

        if ($Angle == 7) {
            $Angle = 0;
        }

        $tjd_tt = $JDNDaysUT + Swe::swe_deltat_ex($JDNDaysUT, $epheflag, $serr);
        $Planet = HeliacalMagnitude::DeterObject($ObjectName);

        if ($Planet != -1) {
            $result = Swe::swe_calc($tjd_tt, $Planet, $iflag, $serr);
            if ($result['rc'] == Swe::ERR) {
                return Swe::ERR;
            }
            $x = $result['xx'];
        } else {
            $result = Swe::swe_fixstar($ObjectName, $tjd_tt, $iflag, $serr);
            if ($result['rc'] == Swe::ERR) {
                return Swe::ERR;
            }
            $x = $result['xx'];
        }

        if ($Angle == 2 || $Angle == 5) {
            $dret = $x[1]; // Declination
        } elseif ($Angle == 3 || $Angle == 6) {
            $dret = $x[0]; // Right ascension
        } else {
            $xin = [$x[0], $x[1]];
            $xaz = Swe::swe_azalt($JDNDaysUT, Swe::SE_EQU2HOR, $dgeo, $datm[0], $datm[1], $xin, $serr);

            if ($Angle == 0) {
                $dret = $xaz['xaz'][1]; // Topocentric altitude
            } elseif ($Angle == 4) {
                $dret = HeliacalAtmosphere::AppAltfromTopoAlt($xaz['xaz'][1], $datm[0], $datm[1], $helflag);
            } elseif ($Angle == 1) {
                $xaz['xaz'][0] += 180;
                if ($xaz['xaz'][0] >= 360) {
                    $xaz['xaz'][0] -= 360;
                }
                $dret = $xaz['xaz'][0]; // Azimuth
            }
        }

        return Swe::OK;
    }

    /**
     * Calculate azimuth/altitude in cartesian coordinates
     * Port from swehel.c:737-778
     *
     * @param float $JDNDaysUT Julian Day Number UT
     * @param array $dgeo Geographic location [longitude, latitude, altitude]
     * @param array $datm Atmospheric parameters [pressure, temperature]
     * @param string $ObjectName Object name
     * @param int $helflag Heliacal flags
     * @param array &$dret Output: [azi, true_alt, app_alt, cart_x, cart_y, cart_z]
     * @param string &$serr Error message output
     * @return int OK or ERR
     */
    public static function azalt_cart(
        float $JDNDaysUT,
        array $dgeo,
        array $datm,
        string $ObjectName,
        int $helflag,
        array &$dret,
        string &$serr
    ): int {
        $epheflag = $helflag & (Swe::SEFLG_JPLEPH | Swe::SEFLG_SWIEPH | Swe::SEFLG_MOSEPH);
        $iflag = Swe::SEFLG_EQUATORIAL | Swe::SEFLG_TOPOCTR | $epheflag;

        if (!($helflag & HeliacalConstants::SE_HELFLAG_HIGH_PRECISION)) {
            $iflag |= Swe::SEFLG_NONUT | Swe::SEFLG_TRUEPOS;
        }

        $tjd_tt = $JDNDaysUT + Swe::swe_deltat_ex($JDNDaysUT, $epheflag, $serr);
        $Planet = HeliacalMagnitude::DeterObject($ObjectName);

        if ($Planet != -1) {
            $result = Swe::swe_calc($tjd_tt, $Planet, $iflag, $serr);
            if ($result['rc'] == Swe::ERR) {
                return Swe::ERR;
            }
            $x = $result['xx'];
        } else {
            $result = Swe::swe_fixstar($ObjectName, $tjd_tt, $iflag, $serr);
            if ($result['rc'] == Swe::ERR) {
                return Swe::ERR;
            }
            $x = $result['xx'];
        }

        $xin = [$x[0], $x[1]];
        $xaz_result = Swe::swe_azalt($JDNDaysUT, Swe::SE_EQU2HOR, $dgeo, $datm[0], $datm[1], $xin, $serr);
        $xaz = $xaz_result['xaz'];

        $dret[0] = $xaz[0]; // Azimuth
        $dret[1] = $xaz[1]; // True altitude
        $dret[2] = $xaz[2]; // Apparent altitude

        // Convert to cartesian coordinates (for apparent altitude)
        $xaz[1] = $xaz[2];
        $xaz[2] = 1;
        $xaz_cart = Swe::swi_polcart($xaz);

        $dret[3] = $xaz_cart[0]; // Cart X
        $dret[4] = $xaz_cart[1]; // Cart Y
        $dret[5] = $xaz_cart[2]; // Cart Z

        return Swe::OK;
    }

    /**
     * Wrapper for swe_rise_trans with star name handling
     * Port from swehel.c:398-407
     *
     * @param float $tjd Julian Day Number
     * @param int $ipl Planet ID
     * @param string $star Star name
     * @param int $helflag Heliacal flags
     * @param int $eventtype Event type (SE_CALC_RISE, SE_CALC_SET, etc.)
     * @param array $dgeo Geographic location
     * @param float $atpress Atmospheric pressure
     * @param float $attemp Atmospheric temperature
     * @param float &$tret Output: time of event
     * @param string &$serr Error message output
     * @return int OK or ERR
     */
    public static function call_swe_rise_trans(
        float $tjd,
        int $ipl,
        string $star,
        int $helflag,
        int $eventtype,
        array $dgeo,
        float $atpress,
        float $attemp,
        float &$tret,
        string &$serr
    ): int {
        $iflag = $helflag & (Swe::SEFLG_JPLEPH | Swe::SEFLG_SWIEPH | Swe::SEFLG_MOSEPH);
        return Swe::swe_rise_trans($tjd, $ipl, $star, $iflag, $eventtype, $dgeo, $atpress, $attemp, $tret, $serr);
    }

    /**
     * Fast rise/set calculation (planets only, lat < 63°)
     * Port from swehel.c:416-506
     *
     * Written by Dieter Koch. Much faster than swe_rise_trans() for planets.
     * Use swe_rise_trans() for circumpolar cases (lat > 63°) or fixed stars.
     *
     * @param float $tjd_start Start time
     * @param int $ipl Planet ID
     * @param array $dgeo Geographic location
     * @param array $datm Atmospheric parameters
     * @param int $eventflag Event flag (SE_CALC_RISE or SE_CALC_SET)
     * @param int $helflag Heliacal flags
     * @param float &$trise Output: time of event
     * @param string &$serr Error message output
     * @return int OK or ERR
     */
    public static function calc_rise_and_set(
        float $tjd_start,
        int $ipl,
        array $dgeo,
        array $datm,
        int $eventflag,
        int $helflag,
        float &$trise,
        string &$serr
    ): int {
        $epheflag = $helflag & (Swe::SEFLG_JPLEPH | Swe::SEFLG_SWIEPH | Swe::SEFLG_MOSEPH);
        $iflag = Swe::SEFLG_EQUATORIAL | $epheflag;

        if (!($helflag & HeliacalConstants::SE_HELFLAG_HIGH_PRECISION)) {
            $iflag |= Swe::SEFLG_NONUT | Swe::SEFLG_TRUEPOS;
        }

        $tjd0 = $tjd_start;
        $tjdnoon = floor($tjd0) - $dgeo[0] / 15.0 / 24.0;

        // Calculate Sun position
        $xs_result = Swe::swe_calc_ut($tjd0, Swe::SE_SUN, $iflag, $serr);
        if ($xs_result['rc'] == Swe::ERR) {
            $serr = "error in calc_rise_and_set(): calc(sun) failed";
            return Swe::ERR;
        }
        $xs = $xs_result['xx'];

        // Calculate planet position
        $xx_result = Swe::swe_calc_ut($tjd0, $ipl, $iflag, $serr);
        if ($xx_result['rc'] == Swe::ERR) {
            $serr = "error in calc_rise_and_set(): calc(planet) failed";
            return Swe::ERR;
        }
        $xx = $xx_result['xx'];

        $tjdnoon -= Swe::swe_degnorm($xs[0] - $xx[0]) / 360.0;

        // Check if planet is above or below horizon
        $xaz = Swe::swe_azalt($tjd0, Swe::SE_EQU2HOR, $dgeo, $datm[0], $datm[1], [$xx[0], $xx[1]], $serr);

        // Adjust tjdnoon based on event type and current position
        if ($eventflag & Swe::SE_CALC_RISE) {
            if ($xaz['xaz'][2] > 0) {
                while ($tjdnoon - $tjd0 < 0.5) $tjdnoon += 1;
                while ($tjdnoon - $tjd0 > 1.5) $tjdnoon -= 1;
            } else {
                while ($tjdnoon - $tjd0 < 0.0) $tjdnoon += 1;
                while ($tjdnoon - $tjd0 > 1.0) $tjdnoon -= 1;
            }
        } else {
            if ($xaz['xaz'][2] > 0) {
                while ($tjd0 - $tjdnoon > 0.5) $tjdnoon += 1;
                while ($tjd0 - $tjdnoon < -0.5) $tjdnoon -= 1;
            } else {
                while ($tjd0 - $tjdnoon > 0.0) $tjdnoon += 1;
                while ($tjd0 - $tjdnoon < -1.0) $tjdnoon -= 1;
            }
        }

        // Get planet position at noon
        $xx_result = Swe::swe_calc_ut($tjdnoon, $ipl, $iflag, $serr);
        if ($xx_result['rc'] == Swe::ERR) {
            return Swe::ERR;
        }
        $xx = $xx_result['xx'];

        // Calculate apparent radius
        $rdi = 0;
        if ($ipl == Swe::SE_SUN) {
            $rdi = asin(696000000.0 / 1.49597870691e+11 / $xx[2]) / HeliacalConstants::DEGTORAD;
        } elseif ($ipl == Swe::SE_MOON) {
            $rdi = asin(1737000.0 / 1.49597870691e+11 / $xx[2]) / HeliacalConstants::DEGTORAD;
        }

        if ($eventflag & Swe::SE_BIT_DISC_CENTER) {
            $rdi = 0;
        }

        // Refraction + disc radius
        $rh = -(34.5 / 60.0 + $rdi);

        // Semi-diurnal arc
        $sda = acos(-tan($dgeo[1] * HeliacalConstants::DEGTORAD) * tan($xx[1] * HeliacalConstants::DEGTORAD))
               * HeliacalConstants::RADTODEG;

        // Initial estimate
        if ($eventflag & Swe::SE_CALC_RISE) {
            $tjdrise = $tjdnoon - $sda / 360.0;
        } else {
            $tjdrise = $tjdnoon + $sda / 360.0;
        }

        // Refine with 2 iterations using velocity
        $iflag = $epheflag | Swe::SEFLG_SPEED | Swe::SEFLG_EQUATORIAL;
        if ($ipl == Swe::SE_MOON) {
            $iflag |= Swe::SEFLG_TOPOCTR;
        }
        if (!($helflag & HeliacalConstants::SE_HELFLAG_HIGH_PRECISION)) {
            $iflag |= Swe::SEFLG_NONUT | Swe::SEFLG_TRUEPOS;
        }

        $dfac = 1 / 365.25;
        for ($i = 0; $i < 2; $i++) {
            $xx_result = Swe::swe_calc_ut($tjdrise, $ipl, $iflag, $serr);
            if ($xx_result['rc'] == Swe::ERR) {
                return Swe::ERR;
            }
            $xx = $xx_result['xx'];

            $xaz = Swe::swe_azalt($tjdrise, Swe::SE_EQU2HOR, $dgeo, $datm[0], $datm[1], [$xx[0], $xx[1]], $serr);

            $xx[0] -= $xx[3] * $dfac;
            $xx[1] -= $xx[4] * $dfac;
            $xaz2 = Swe::swe_azalt($tjdrise - $dfac, Swe::SE_EQU2HOR, $dgeo, $datm[0], $datm[1], [$xx[0], $xx[1]], $serr);

            $tjdrise -= ($xaz['xaz'][1] - $rh) / ($xaz['xaz'][1] - $xaz2['xaz'][1]) * $dfac;
        }

        $trise = $tjdrise;
        return Swe::OK;
    }

    /**
     * Rise/set/transit calculation dispatcher
     * Port from swehel.c:508-525
     *
     * Uses fast algorithm for planets at lat < 63°, rigorous for stars and circumpolar cases
     *
     * @param float $tjd Julian Day Number
     * @param int $ipl Planet ID
     * @param string $starname Star name
     * @param int $eventtype Event type
     * @param int $helflag Heliacal flags
     * @param array $dgeo Geographic location
     * @param array $datm Atmospheric parameters
     * @param float &$tret Output: time of event
     * @param string &$serr Error message output
     * @return int OK or ERR
     */
    public static function my_rise_trans(
        float $tjd,
        int $ipl,
        string $starname,
        int $eventtype,
        int $helflag,
        array $dgeo,
        array $datm,
        float &$tret,
        string &$serr
    ): int {
        if ($starname !== null && $starname !== '') {
            $ipl = HeliacalMagnitude::DeterObject($starname);
        }

        // For non-circumpolar planets use fast algorithm
        if ($ipl != -1 && abs($dgeo[1]) < 63) {
            return self::calc_rise_and_set($tjd, $ipl, $dgeo, $datm, $eventtype, $helflag, $tret, $serr);
        } else {
            // For stars and circumpolar planets use rigorous algorithm
            return self::call_swe_rise_trans($tjd, $ipl, $starname, $helflag, $eventtype, $dgeo,
                                            $datm[0], $datm[1], $tret, $serr);
        }
    }

    /**
     * Rise/set wrapper for object by name
     * Port from swehel.c:535-551
     *
     * @param float $JDNDaysUT Julian Day Number UT
     * @param array $dgeo Geographic location
     * @param array $datm Atmospheric parameters
     * @param string $ObjectName Object name
     * @param int $RSEvent Event type (1=rise, 2=set, 3=up transit, 4=down transit)
     * @param int $helflag Heliacal flags
     * @param int $Rim 0=center, 1=top edge
     * @param float &$tret Output: time of event
     * @param string &$serr Error message output
     * @return int OK or ERR
     */
    public static function RiseSet(
        float $JDNDaysUT,
        array $dgeo,
        array $datm,
        string $ObjectName,
        int $RSEvent,
        int $helflag,
        int $Rim,
        float &$tret,
        string &$serr
    ): int {
        $eventtype = $RSEvent;
        if ($Rim == 0) {
            $eventtype |= Swe::SE_BIT_DISC_CENTER;
        }

        $Planet = HeliacalMagnitude::DeterObject($ObjectName);

        if ($Planet != -1) {
            return self::my_rise_trans($JDNDaysUT, $Planet, "", $eventtype, $helflag, $dgeo, $datm, $tret, $serr);
        } else {
            return self::my_rise_trans($JDNDaysUT, -1, $ObjectName, $eventtype, $helflag, $dgeo, $datm, $tret, $serr);
        }
    }
}
