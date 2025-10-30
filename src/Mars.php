<?php

namespace Swisseph;

/**
 * Low-precision heliocentric ecliptic rectangular coordinates of Mars (J2000) in AU.
 * Based on simplified secular elements around J2000 (Meeus-like).
 */
final class Mars
{
    /**
     * Compute heliocentric ecliptic rectangular coordinates (x,y,z) in AU.
     * @param float $jd_tt Julian Day (TT)
     * @return array{0: float, 1: float, 2: float}
     */
    public static function heliocentricRectEclAU(float $jd_tt): array
    {
        $T = ($jd_tt - 2451545.0) / 36525.0; // centuries from J2000.0

        // Approximate orbital elements near J2000 (degrees, AU)
        $a = 1.523679; // semi-major axis, AU
        $e = 0.09340062 + (-0.00009048) * $T;
        $I = 1.849726 - 0.0081479 * $T; // inclination
        $L = 355.433275 + 19140.2993313 * $T; // mean longitude
        $long_peri = 336.04084 + 0.4439 * $T; // longitude of perihelion ϖ
        $Omega = 49.558093 - 0.292573 * $T; // ascending node Ω

        $M = Math::normAngleDeg($L - $long_peri); // mean anomaly
        $omega = Math::normAngleDeg($long_peri - $Omega); // argument of perihelion

        // Radians
        $Mr = Math::degToRad($M);
        $Ir = Math::degToRad($I);
        $Omegar = Math::degToRad($Omega);
        $omegar = Math::degToRad($omega);

        // Kepler's equation solver (Newton-Raphson)
        $E = $Mr;
        for ($k = 0; $k < 15; $k++) {
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
        $sqrt1me2 = sqrt(1 - $e*$e);
        $v = atan2($sqrt1me2 * $sinE, $cosE - $e);
        $r = $a * (1 - $e * $cosE);

        $u = $omegar + $v; // argument of latitude
        $cu = cos($u);
        $su = sin($u);
        $cO = cos($Omegar);
        $sO = sin($Omegar);
        $cI = cos($Ir);
        $sI = sin($Ir);

        // Rectangular heliocentric ecliptic (J2000)
        $x = $r * ($cO * $cu - $sO * $su * $cI);
        $y = $r * ($sO * $cu + $cO * $su * $cI);
        $z = $r * ($su * $sI);

        return [$x, $y, $z];
    }
}
