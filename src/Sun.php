<?php

namespace Swisseph;

/**
 * Approximate apparent ecliptic longitude/latitude/distance of the Sun.
 * Based on common low-precision formulas (Meeus-like). No nutation/aberration.
 * Returns geocentric ecliptic spherical coordinates (lon, lat, dist[AU]).
 */
final class Sun
{
    /**
     * Compute Sun apparent ecliptic spherical coordinates (lonRad, latRad, distAu).
     * @param float $jd_tt Julian Day (TT)
     * @return array{0: float, 1: float, 2: float} [lonRad, latRad, distAu]
     */
    public static function eclipticLonLatDist(float $jd_tt): array
    {
        // Julian centuries from J2000.0 (TT)
        $T = ($jd_tt - 2451545.0) / 36525.0;

        // Mean anomaly of the Sun (deg)
        $M = 357.52911 + 35999.05029 * $T - 0.0001537 * $T * $T;
        // Mean longitude (deg)
        $L0 = 280.46646 + 36000.76983 * $T + 0.0003032 * $T * $T;
        // Eccentricity of Earth's orbit
        $e = 0.016708634 - 0.000042037 * $T - 0.0000001267 * $T * $T;

        // Equation of center (deg)
        $Mr = Math::degToRad($M);
        $C = (1.914602 - 0.004817 * $T - 0.000014 * $T * $T) * sin($Mr)
           + (0.019993 - 0.000101 * $T) * sin(2.0 * $Mr)
           + 0.000289 * sin(3.0 * $Mr);

        // True longitude (deg)
        $theta = $L0 + $C;

        // True anomaly v ~ M + C (deg)
        $v = $M + $C;
        $vr = Math::degToRad($v);

        // Distance in AU: R = (1.000001018 * (1 - e^2)) / (1 + e * cos(v))
        $R = (1.000001018 * (1.0 - $e * $e)) / (1.0 + $e * cos($vr));

        // Geocentric ecliptic longitude of the Sun is true longitude (no nutation/aberration here)
        $lon = Math::degToRad(Math::normAngleDeg($theta));
        $lat = 0.0; // Sun's geocentric ecliptic latitude is ~0 (neglect small terms)

        return [$lon, $lat, $R];
    }
}
