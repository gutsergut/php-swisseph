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
}
