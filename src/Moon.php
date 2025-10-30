<?php

namespace Swisseph;

/**
 * Low-precision Moon geocentric ecliptic position based on simplified Meeus-like series.
 * Returns spherical ecliptic longitude, latitude (radians) and distance (AU).
 */
final class Moon
{
    private const AU_KM = 149597870.7;

    /**
     * @param float $jd_tt Julian Day (TT)
     * @return array{0: float, 1: float, 2: float} [lonRad, latRad, distAu]
     */
    public static function eclipticLonLatDist(float $jd_tt): array
    {
        $T = ($jd_tt - 2451545.0) / 36525.0;

        // Mean elements in degrees
        $Lprime = 218.3164477 + 481267.88123421 * $T - 0.0015786 * $T*$T + $T*$T*$T / 538841.0 - $T*$T*$T*$T / 65194000.0;
        $D = 297.8501921 + 445267.1114034 * $T - 0.0018819 * $T*$T + $T*$T*$T / 545868.0 - $T*$T*$T*$T / 113065000.0;
        $M = 357.5291092 + 35999.0502909 * $T - 0.0001536 * $T*$T + $T*$T*$T / 24490000.0;
        $Mprime = 134.9633964 + 477198.8675055 * $T + 0.0087414 * $T*$T + $T*$T*$T / 69699.0 - $T*$T*$T*$T / 14712000.0;
        $F = 93.2720950 + 483202.0175233 * $T - 0.0036539 * $T*$T - $T*$T*$T / 3526000.0 + $T*$T*$T*$T / 863310000.0;

        // Eccentricity of Earth's orbit
        $E = 1.0 - 0.002516 * $T - 0.0000074 * $T * $T;
        $E2 = $E * $E;

        // Convert to radians for trig
        $Dr = Math::degToRad(Math::normAngleDeg($D));
        $Mr = Math::degToRad(Math::normAngleDeg($M));
        $Mpr = Math::degToRad(Math::normAngleDeg($Mprime));
        $Fr = Math::degToRad(Math::normAngleDeg($F));

        // Longitude correction Δλ in 1e-6 degrees (include dominant terms)
        $lon_u6 = 0.0;
        $lon_u6 += 6288774 * sin($Mpr);
        $lon_u6 += 1274027 * sin(2*$Dr - $Mpr);
        $lon_u6 += 658314 * sin(2*$Dr);
        $lon_u6 += 213618 * sin(2*$Mpr);
        $lon_u6 -= 185116 * $E * sin($Mr);
        $lon_u6 -= 114332 * sin(2*$Fr);
        $lon_u6 += 58793 * sin(2*$Dr - 2*$Mpr);
        $lon_u6 += 57066 * $E * sin(2*$Dr - $Mr - $Mpr);
        $lon_u6 += 53322 * sin(2*$Dr + $Mpr);
        $lon_u6 += 45758 * $E * sin(2*$Dr - $Mr);
        $lon_u6 -= 40923 * $E * sin($Mr - $Mpr);
        $lon_u6 -= 34720 * sin($Dr);
        $lon_u6 -= 30383 * $E * sin($Mr + $Mpr);
        $lon_u6 += 15327 * sin(2*$Dr - 2*$Fr);

        // Latitude correction β in 1e-6 degrees (dominant terms)
        $lat_u6 = 0.0;
        $lat_u6 += 5128122 * sin($Fr);
        $lat_u6 += 280602 * sin($Mpr + $Fr);
        $lat_u6 += 277693 * sin($Mpr - $Fr);
        $lat_u6 += 173237 * sin(2*$Dr - $Fr);
        $lat_u6 += 55413 * sin(2*$Dr + $Fr - $Mpr);
        $lat_u6 += 46271 * sin(2*$Dr - $Fr - $Mpr);
        $lat_u6 += 32573 * sin(2*$Dr + $Fr);
        $lat_u6 += 17198 * sin(2*$Dr + $Mpr - $Fr);

        // Distance correction ΔR in km (dominant terms)
        $deltaR_km = 0.0;
        $deltaR_km -= 20905355 * cos($Mpr);
        $deltaR_km -= 3699111 * cos(2*$Dr - $Mpr);
        $deltaR_km -= 2955968 * cos(2*$Dr);
        $deltaR_km -= 569925 * cos(2*$Mpr);
        $deltaR_km += 48888 * $E * cos($Mr);
        $deltaR_km -= 3149 * cos(2*$Fr);
        $deltaR_km /= 1000.0; // convert to km

        $lambda_deg = Math::normAngleDeg($Lprime + $lon_u6 / 1e6);
        $beta_deg = ($lat_u6 / 1e6);

        $lambda = Math::degToRad($lambda_deg);
        $beta = Math::degToRad($beta_deg);

        $R_km = 385000.56 + $deltaR_km;
        $R_au = $R_km / self::AU_KM;

        return [$lambda, $beta, $R_au];
    }
}
