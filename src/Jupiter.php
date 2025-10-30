<?php

namespace Swisseph;

/**
 * Low-precision heliocentric ecliptic rectangular coordinates of Jupiter (J2000) in AU.
 * Based on simplified secular elements around J2000 (Meeus-like approximation).
 */
final class Jupiter
{
    /**
     * Compute heliocentric ecliptic rectangular coordinates (x,y,z) in AU.
     * @param float $jd_tt Julian Day (TT)
     * @return array{0: float, 1: float, 2: float}
     */
    public static function heliocentricRectEclAU(float $jd_tt): array
    {
        $T = ($jd_tt - 2451545.0) / 36525.0; // Julian centuries from J2000.0

        // Approximate orbital elements near J2000 (degrees, AU)
        $a = 5.202603191; // AU
        $e = 0.04849485 - 0.000163244 * $T; // eccentricity
        $I = 1.303270 - 0.0019872 * $T; // inclination
        $L = 34.351484 + 3034.9056746 * $T; // mean longitude
        $long_peri = 14.331309 + 0.2155525 * $T; // longitude of perihelion ϖ
        $Omega = 100.464441 + 0.1766828 * $T; // ascending node Ω

        $M = Math::normAngleDeg($L - $long_peri); // mean anomaly
        $omega = Math::normAngleDeg($long_peri - $Omega); // argument of perihelion

        // Radians
        $Mr = Math::degToRad($M);
        $Ir = Math::degToRad($I);
        $Omegar = Math::degToRad($Omega);
        $omegar = Math::degToRad($omega);

        // Solve Kepler's equation for E (eccentric anomaly) via Newton-Raphson
        $E = $Mr;
        for ($k = 0; $k < 20; $k++) {
            $f = $E - $e * sin($E) - $Mr;
            $fp = 1 - $e * cos($E);
            $dE = -$f / $fp;
            $E += $dE;
            if (abs($dE) < 1e-12) {
                break;
            }
        }

        $cosE = cos($E);
        $sinE = sin($E);
        $sqrt1me2 = sqrt(1 - $e * $e);
        $v = atan2($sqrt1me2 * $sinE, $cosE - $e); // true anomaly
        $r = $a * (1 - $e * $cosE); // radius vector in AU

        $u = $omegar + $v; // argument of latitude
        $cu = cos($u);
        $su = sin($u);
        $cO = cos($Omegar);
        $sO = sin($Omegar);
        $cI = cos($Ir);
        $sI = sin($Ir);

        // Heliocentric rectangular ecliptic (J2000)
        $x = $r * ($cO * $cu - $sO * $su * $cI);
        $y = $r * ($sO * $cu + $cO * $su * $cI);
        $z = $r * ($su * $sI);

        return [$x, $y, $z];
    }
}
