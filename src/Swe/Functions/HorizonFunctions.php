<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\DeltaT;
use Swisseph\Horizontal;
use Swisseph\Math;
use Swisseph\Obliquity;
use Swisseph\Coordinates;
use Swisseph\ErrorCodes;

class HorizonFunctions
{
    /**
     * Implements the logic for swe_azalt.
     */
    public static function azalt(float $jd_ut, int $mode, array $xin, array $geopos, float $atpress, float $attemp, array &$xout, ?string &$serr = null): int
    {
        $serr = null;
        $xout = array_fill(0, 3, 0.0);

        // Input coords in degrees
        $in0 = $xin[0];
        $in1 = $xin[1];

        // Geopos
        $geolon_deg = $geopos[0];
        $geolat_deg = $geopos[1];
        $geoalt_m = $geopos[2];

        // Convert geo lat to radians
        $geolat_rad = Math::degToRad($geolat_deg);

        // LST
        $lst_rad = Horizontal::lstRad($jd_ut, $geolon_deg);

        // Obliquity
        $jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
        $eps_rad = Obliquity::meanObliquityRadFromJdTT($jd_tt);

        $ra_rad = 0.0;
        $dec_rad = 0.0;

        switch ($mode) {
            case Constants::SE_ECL2HOR:
                $lon_rad = Math::degToRad($in0);
                $lat_rad = Math::degToRad($in1);
                // Assuming distance=1 for ecliptic to equatorial conversion of angles
                [$ra_rad, $dec_rad] = Coordinates::eclipticToEquatorialRad($lon_rad, $lat_rad, 1.0, $eps_rad);
                break;
            case Constants::SE_EQU2HOR:
                $ra_rad = Math::degToRad($in0);
                $dec_rad = Math::degToRad($in1);
                break;
            default:
                $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'Invalid mode for azalt');
                return Constants::SE_ERR;
        }

        // Convert equatorial to horizontal
        [$az_rad, $alt_rad] = Horizontal::equatorialToHorizontal($ra_rad, $dec_rad, $geolat_rad, $lst_rad);

        // Convert azimuth from (North=0, Eastward) to Swisseph's (South=0, Westward)
        $az_deg = Math::radToDeg($az_rad);
        $az_deg_swe = Math::normAngleDeg($az_deg + 180.0);

        $alt_true_deg = Math::radToDeg($alt_rad);

        // Refraction
        $alt_app_deg = self::refrac($alt_true_deg, $atpress, $attemp, Constants::SE_TRUE_TO_APP);

        $xout[0] = $az_deg_swe;      // Azimuth, from south clockwise
        $xout[1] = $alt_true_deg;    // True altitude
        $xout[2] = $alt_app_deg;     // Apparent altitude

        return Constants::SE_OK;
    }

    /**
     * Implements the logic for swe_azalt_rev.
     */
    public static function azalt_rev(float $jd_ut, int $mode, array $xin, array $geopos, array &$xout, ?string &$serr = null): int
    {
        $serr = null;
        $xout = array_fill(0, 2, 0.0);

        // Input coords in degrees from Swisseph convention (Azimuth from South, clockwise)
        $az_swe_deg = $xin[0];
        $alt_true_deg = $xin[1];

        // Geopos
        $geolon_deg = $geopos[0];
        $geolat_deg = $geopos[1];
        $geoalt_m = $geopos[2];

        // Convert azimuth from Swisseph's (South=0, Westward) to internal (North=0, Eastward)
        $az_rad = Math::degToRad(Math::normAngleDeg($az_swe_deg - 180.0));
        $alt_rad = Math::degToRad($alt_true_deg);

        // Convert geo lat to radians
        $geolat_rad = Math::degToRad($geolat_deg);

        // LST
        $lst_rad = Horizontal::lstRad($jd_ut, $geolon_deg);

        // Obliquity
        $jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
        $eps_rad = Obliquity::meanObliquityRadFromJdTT($jd_tt);

        // Convert horizontal to equatorial
        [$ra_rad, $dec_rad] = Horizontal::horizontalToEquatorial($az_rad, $alt_rad, $geolat_rad, $lst_rad);

        switch ($mode) {
            case Constants::SE_HOR2ECL:
                // Assuming distance=1 for equatorial to ecliptic conversion of angles
                [$lon_rad, $lat_rad] = Coordinates::equatorialToEclipticRad($ra_rad, $dec_rad, 1.0, $eps_rad);
                $xout[0] = Math::radToDeg($lon_rad);
                $xout[1] = Math::radToDeg($lat_rad);
                break;
            case Constants::SE_HOR2EQU:
                $xout[0] = Math::radToDeg($ra_rad);
                $xout[1] = Math::radToDeg($dec_rad);
                break;
            default:
                $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'Invalid mode for azalt_rev');
                return Constants::SE_ERR;
        }

        return Constants::SE_OK;
    }

    /**
     * A simple refraction model.
     * Based on Bennett, G. G. (1982). The calculation of astronomical refraction.
     * Journal of the British Astronomical Association, 92(4), 178-183.
     * This is a simplified version.
     * Returns apparent altitude from true altitude, or vice-versa.
     */
    public static function refrac(float $alt_deg, float $atpress, float $attemp, int $dir): float
    {
        if ($dir === Constants::SE_APP_TO_TRUE) {
            // Formula for apparent to true
            $R = 1.0 / tan(Math::degToRad($alt_deg + 7.31 / ($alt_deg + 4.4)));
        } else {
            // Formula for true to apparent
            $R = 1.02 / tan(Math::degToRad($alt_deg + 10.3 / ($alt_deg + 5.11)));
        }

        $R_arcmin = $R * ($atpress / 1010.0) * (283.0 / (273.0 + $attemp));

        if ($dir === Constants::SE_APP_TO_TRUE) {
            return $alt_deg - $R_arcmin / 60.0;
        } else {
            return $alt_deg + $R_arcmin / 60.0;
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
