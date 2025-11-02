<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;

/**
 * Horizontal coordinate transformations
 * Full port from swecl.c:2788-2878 (swe_azalt and swe_azalt_rev)
 *
 * WITHOUT SIMPLIFICATIONS - complete C algorithm with:
 * - Uses swe_sidtime, swe_calc, swe_deltat_ex, swe_cotrans, swe_degnorm
 * - Atmospheric refraction via swe_refrac_extended
 * - Matches C API signature exactly
 */
class HorizonFunctions
{
    /**
     * Convert equatorial or ecliptic coordinates to horizontal coordinates
     * Port from swecl.c:2788-2822 (swe_azalt)
     *
     * C API: void swe_azalt(double tjd_ut, int32 calc_flag, double *geopos,
     *                       double atpress, double attemp, double *xin, double *xaz)
     *
     * This matches the original C API parameter order exactly.
     *
     * @param float $tjd_ut Julian day, Universal Time
     * @param int $calc_flag SE_ECL2HOR (0) or SE_EQU2HOR (1)
     * @param array $geopos [longitude (deg), latitude (deg), height (m)]
     * @param float $atpress Atmospheric pressure in mbar/hPa (0 = auto-estimate)
     * @param float $attemp Atmospheric temperature in °C
     * @param array $xin Input coordinates [coord1, coord2] in degrees
     * @param array $xaz Output [azimuth, true_alt, apparent_alt] in degrees
     * @return void
     */
    public static function azalt(
        float $tjd_ut,
        int $calc_flag,
        array $geopos,
        float $atpress,
        float $attemp,
        array $xin,
        array &$xaz
    ): void {
        $x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xra = [0.0, 0.0, 1.0];

        // Calculate ARMC (sidereal time at Greenwich + longitude)
        $armc = \swe_degnorm(\swe_sidtime($tjd_ut) * 15.0 + $geopos[0]);

        // Copy input coordinates
        $xra[0] = $xin[0];
        $xra[1] = $xin[1];

        // If ecliptic coordinates, convert to equatorial
        if ($calc_flag === Constants::SE_ECL2HOR) {
            $serr = '';
            \swe_calc($tjd_ut + \swe_deltat_ex($tjd_ut, -1, $serr), Constants::SE_ECL_NUT, 0, $x, $serr);
            $eps_true = $x[0]; // True obliquity
            $xra_out = [0.0, 0.0, 0.0];
            \swe_cotrans($xra, -$eps_true, $xra_out);
            $xra = $xra_out;
        }

        // Calculate meridian distance
        $mdd = \swe_degnorm($xra[0] - $armc);

        // Rotate by meridian distance
        $x[0] = \swe_degnorm($mdd - 90.0);
        $x[1] = $xra[1];
        $x[2] = 1.0;

        // Azimuth from east, counterclockwise
        // Rotate by (90 - latitude)
        $x_out = [0.0, 0.0, 0.0];
        \swe_cotrans($x, 90.0 - $geopos[1], $x_out);
        $x = $x_out;        // Convert azimuth from south to west
        $x[0] = \swe_degnorm($x[0] + 90.0);
        $xaz[0] = 360.0 - $x[0];
        $xaz[1] = $x[1]; // True altitude

        // Estimate atmospheric pressure if not provided
        if ($atpress == 0.0) {
            $atpress = 1013.25 * pow(1.0 - 0.0065 * $geopos[2] / 288.0, 5.255);
        }

        // Calculate apparent altitude with refraction
        $dret = null;
        $xaz[2] = \swe_refrac_extended($x[1], $geopos[2], $atpress, $attemp, Constants::SE_LAPSE_RATE,
                                       Constants::SE_TRUE_TO_APP, $dret);
    }    /**
     * Convert horizontal coordinates to equatorial or ecliptic coordinates
     * Port from swecl.c:2838-2878 (swe_azalt_rev)
     *
     * C API: void swe_azalt_rev(double tjd_ut, int32 calc_flag, double *geopos,
     *                           double *xin, double *xout)
     *
     * @param float $tjd_ut Julian day, Universal Time
     * @param int $calc_flag SE_HOR2ECL (0) or SE_HOR2EQU (1)
     * @param array $geopos [longitude (deg), latitude (deg), height (m)]
     * @param array $xin Input [azimuth, true_altitude] in degrees
     * @param array $xout Output coordinates [coord1, coord2] in degrees
     * @return void
     */
    public static function azalt_rev(
        float $tjd_ut,
        int $calc_flag,
        array $geopos,
        array $xin,
        array &$xout
    ): void {
        $x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xaz = [0.0, 0.0, 1.0];

        $geolon = $geopos[0];
        $geolat = $geopos[1];

        // Calculate ARMC
        $armc = \swe_degnorm(\swe_sidtime($tjd_ut) * 15.0 + $geolon);

        // Copy input coordinates
        $xaz[0] = $xin[0];
        $xaz[1] = $xin[1];

        // Azimuth is from south, clockwise
        // We need it from east, counterclockwise
        $xaz[0] = 360.0 - $xaz[0];
        $xaz[0] = \swe_degnorm($xaz[0] - 90.0);

        // Convert to equatorial positions
        $dang = $geolat - 90.0;
        $xaz_out = [0.0, 0.0, 0.0];
        \swe_cotrans($xaz, $dang, $xaz_out);
        $xaz = $xaz_out;

        $xaz[0] = \swe_degnorm($xaz[0] + $armc + 90.0);
        $xout[0] = $xaz[0];
        $xout[1] = $xaz[1];

        // Convert to ecliptic positions if requested
        if ($calc_flag === Constants::SE_HOR2ECL) {
            $serr = '';
            \swe_calc($tjd_ut + \swe_deltat_ex($tjd_ut, -1, $serr), Constants::SE_ECL_NUT, 0, $x, $serr);
            $eps_true = $x[0]; // True obliquity
            $x_out = [0.0, 0.0, 0.0];
            \swe_cotrans($xaz, $eps_true, $x_out);
            $xout[0] = $x_out[0];
            $xout[1] = $x_out[1];
        }
    }

    /**
     * Extended refraction function with lapse rate.
     * Port of swe_refrac_extended() from swecl.c:3035
     *
     * @param float $inalt Altitude in degrees (true or apparent, depending on calc_flag)
     * @param float $geoalt Observer altitude above sea level in meters
     * @param float $atpress Atmospheric pressure in millibars (hectopascals)
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @param float $lapse_rate Temperature lapse rate (dT/dh) in K/m
     * @param int $calc_flag SE_TRUE_TO_APP or SE_APP_TO_TRUE
     * @param array|null $dret Optional return array with 4 elements:
     *                         [0] = true altitude
     *                         [1] = apparent altitude
     *                         [2] = refraction value
     *                         [3] = dip of horizon
     * @return float Calculated altitude (apparent or true, depending on calc_flag)
     */
    public static function refracExtended(
        float $inalt,
        float $geoalt,
        float $atpress,
        float $attemp,
        float $lapse_rate,
        int $calc_flag,
        ?array &$dret = null
    ): float {
        // Initialize dret array if needed
        $use_dret = ($dret !== null);
        if (!$use_dret) {
            $dret = [0.0, 0.0, 0.0, 0.0];
        }

        // Calculate dip of horizon
        $dip = self::calcDip($geoalt, $atpress, $attemp, $lapse_rate);

        // Make sure that inalt <= 90
        if ($inalt > 90) {
            $inalt = 180 - $inalt;
        }

        if ($calc_flag === Constants::SE_TRUE_TO_APP) {
            // True to apparent altitude
            if ($inalt < -10) {
                $dret[0] = $inalt;
                $dret[1] = $inalt;
                $dret[2] = 0.0;
                $dret[3] = $dip;
                return $inalt;
            }

            // Newton iteration to find apparent altitude
            $y = $inalt;
            $D = 0.0;
            $yy0 = 0.0;
            $D0 = $D;

            for ($i = 0; $i < 5; $i++) {
                $D = self::calcAstronomicalRefr($y, $atpress, $attemp);
                $N = $y - $yy0;
                $yy0 = $D - $D0 - $N; // denominator of derivative

                if ($N != 0.0 && $yy0 != 0.0) {
                    // Newton iteration with numerically estimated derivative
                    $N = $y - $N * ($inalt + $D - $y) / $yy0;
                } else {
                    // Can't do it on first pass
                    $N = $inalt + $D;
                }

                $yy0 = $y;
                $D0 = $D;
                $y = $N;
            }

            $refr = $D;

            if ($inalt + $refr < $dip) {
                $dret[0] = $inalt;
                $dret[1] = $inalt;
                $dret[2] = 0.0;
                $dret[3] = $dip;
                return $inalt;
            }

            $dret[0] = $inalt;
            $dret[1] = $inalt + $refr;
            $dret[2] = $refr;
            $dret[3] = $dip;

            return $inalt + $refr;
        } else {
            // Apparent to true altitude (SE_APP_TO_TRUE)
            $refr = self::calcAstronomicalRefr($inalt, $atpress, $attemp);
            $trualt = $inalt - $refr;

            if ($inalt > $dip) {
                $dret[0] = $trualt;
                $dret[1] = $inalt;
                $dret[2] = $refr;
                $dret[3] = $dip;
            } else {
                $dret[0] = $inalt;
                $dret[1] = $inalt;
                $dret[2] = 0.0;
                $dret[3] = $dip;
            }

            // Apparent altitude cannot be below dip.
            // True altitude is only returned if apparent altitude is higher than dip.
            // Otherwise the apparent altitude is returned.
            if ($inalt >= $dip) {
                return $trualt;
            } else {
                return $inalt;
            }
        }
    }

    /**
     * Calculate astronomical refraction.
     * Port of calc_astronomical_refr() from swecl.c:3095
     *
     * Uses Sinclair formula (better for apparent altitudes < 0)
     * from Bennett, G.G. (1982), "The calculation of astronomical refraction in marine navigation",
     * Journal of Inst. Navigation, No. 35, page 255-259, especially page 256.
     *
     * @param float $inalt Apparent altitude in degrees
     * @param float $atpress Atmospheric pressure in millibars
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @return float Refraction in degrees
     */
    private static function calcAstronomicalRefr(float $inalt, float $atpress, float $attemp): float
    {
        if ($inalt > 17.904104638432) {
            // For continuous function, instead of '>15'
            $r = 0.97 / tan($inalt * Constants::DEGTORAD);
        } else {
            $r = (34.46 + 4.23 * $inalt + 0.004 * $inalt * $inalt) /
                 (1 + 0.505 * $inalt + 0.0845 * $inalt * $inalt);
        }

        $r = (($atpress - 80) / 930 / (1 + 0.00008 * ($r + 39) * ($attemp - 10)) * $r) / 60.0;

        return $r;
    }

    /**
     * Calculate dip of the horizon.
     * Port of calc_dip() from swecl.c:3120
     *
     * Formula based on A. Thom, "Megalithic lunar observations", 1973 (page 32).
     * Conversion to metric by V. Reijs, 2000,
     * http://www.archaeocosmology.org/eng/refract.htm#Sea
     *
     * @param float $geoalt Observer altitude above sea level in meters
     * @param float $atpress Atmospheric pressure in millibars
     * @param float $attemp Atmospheric temperature in degrees Celsius
     * @param float $lapse_rate Temperature lapse rate (dT/dh) in K/m
     * @return float Dip in degrees
     */
    private static function calcDip(float $geoalt, float $atpress, float $attemp, float $lapse_rate): float
    {
        $krefr = (0.0342 + $lapse_rate) / (0.154 * 0.0238);
        $d = 1 - 1.8480 * $krefr * $atpress / (273.15 + $attemp) / (273.15 + $attemp);

        return -180.0 / M_PI * acos(1 / (1 + $geoalt / Constants::EARTH_RADIUS)) * sqrt($d);
    }
}
