<?php

namespace Swisseph;

/**
 * Horizontal coordinate utilities (scaffold). Will be used for rise/set/transit.
 */
final class Horizontal
{
    /**
     * Compute Local Sidereal Time (radians) from jd_ut and longitude (degrees).
     */
    public static function lstRad(float $jd_ut, float $geolon_deg): float
    {
        $gmst_h = Sidereal::gmstHoursFromJdUt($jd_ut);
        $lst_deg = $gmst_h * 15.0 + $geolon_deg;
        return Math::degToRad(Math::normAngleDeg($lst_deg));
    }

    /**
     * Convert equatorial (ra, dec) in radians to horizontal (az, alt) in radians for given LST and latitude.
     * Returns [az, alt] with azimuth measured eastward from North (0..2π), altitude (−π/2..+π/2).
     */
    public static function equatorialToHorizontal(float $ra, float $dec, float $lat_rad, float $lst_rad): array
    {
        $H = Math::normAngleRad($lst_rad - $ra); // hour angle
        $sin_alt = sin($dec) * sin($lat_rad) + cos($dec) * cos($lat_rad) * cos($H);
        $alt = asin($sin_alt);
        $cos_alt = cos($alt);
        if ($cos_alt < 1e-12) {
            // Near zenith/nadir; azimuth undefined - return 0
            return [0.0, $alt];
        }
        $sin_az = -cos($dec) * sin($H) / $cos_alt;
        $cos_az = (sin($dec) - sin($lat_rad) * $sin_alt) / (cos($lat_rad) * $cos_alt);
        $az = atan2($sin_az, $cos_az);
        if ($az < 0) { $az += Math::TWO_PI; }
        return [$az, $alt];
    }

    /**
     * Convert horizontal (az, alt) in radians to equatorial (ra, dec) in radians for given LST and latitude.
     * Azimuth is measured eastward from North (0..2π).
     * Returns [ra, dec].
     */
    public static function horizontalToEquatorial(float $az, float $alt, float $lat_rad, float $lst_rad): array
    {
        $sin_dec = sin($alt) * sin($lat_rad) + cos($alt) * cos($lat_rad) * cos($az);
        $dec = asin($sin_dec);
        $cos_dec = cos($dec);

        if ($cos_dec < 1e-12) {
            // Near celestial pole; RA is undefined, but hour angle is conventionally LST.
            // So RA would be 0. Let's return LST as RA to indicate the object is on the meridian.
            return [$lst_rad, $dec];
        }

        $sin_h = -sin($az) * cos($alt) / $cos_dec;
        $cos_h = (sin($alt) - sin($lat_rad) * $sin_dec) / (cos($lat_rad) * $cos_dec);
        $H = atan2($sin_h, $cos_h); // Hour Angle

        $ra = Math::normAngleRad($lst_rad - $H);

        return [$ra, $dec];
    }
}
