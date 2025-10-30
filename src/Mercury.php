<?php

namespace Swisseph;

/**
 * Low-precision heliocentric ecliptic coordinates of Mercury (J2000 frame) in AU.
 * Based on simplified secular elements around J2000.
 */
final class Mercury
{
    /**
     * Compute heliocentric ecliptic rectangular coordinates (x,y,z) in AU.
     * @param float $jd_tt Julian Day (TT)
     * @return array{0: float, 1: float, 2: float}
     */
    public static function heliocentricRectEclAU(float $jd_tt): array
    {
        // Julian centuries from J2000.0
        $T = ($jd_tt - 2451545.0) / 36525.0;

        // Orbital elements (deg) with linear rates per century (approximate)
        $a = 0.38709893; // AU
        $e = 0.20563069 + 0.00002527 * $T;
        $I = 7.00497902 - 0.00594749 * $T; // inclination
        $L = 252.25032350 + 149472.67411175 * $T; // mean longitude
        $long_peri = 77.45779628 + 0.16047689 * $T; // longitude of perihelion ϖ
        $Omega = 48.33076593 - 0.12534081 * $T; // longitude of ascending node Ω

        $M = Math::normAngleDeg($L - $long_peri); // mean anomaly
        $omega = Math::normAngleDeg($long_peri - $Omega); // argument of perihelion

        // Convert to radians
        $Mr = Math::degToRad($M);
        $Ir = Math::degToRad($I);
        $Omegar = Math::degToRad($Omega);
        $omegar = Math::degToRad($omega);

        // Solve Kepler's equation for eccentric anomaly E via Newton-Raphson
        $E = $Mr; // initial guess
        for ($k = 0; $k < 10; $k++) {
            $f = $E - $e * sin($E) - $Mr;
            $fp = 1 - $e * cos($E);
            $dE = -$f / $fp;
            $E += $dE;
            if (abs($dE) < 1e-12) {
                break;
            }
        }

        // True anomaly and radius
        $cosE = cos($E);
        $sinE = sin($E);
        $sqrt1me2 = sqrt(1 - $e*$e);
        $v = atan2($sqrt1me2 * $sinE, $cosE - $e);
        $r = $a * (1 - $e * $cosE);

        // Argument of latitude u = ω + v
        $u = $omegar + $v;

        // Heliocentric rectangular coordinates in ecliptic frame (J2000)
        $cu = cos($u);
        $su = sin($u);
        $cO = cos($Omegar);
        $sO = sin($Omegar);
        $cI = cos($Ir);
        $sI = sin($Ir);

        $x = $r * ($cO * $cu - $sO * $su * $cI);
        $y = $r * ($sO * $cu + $cO * $su * $cI);
        $z = $r * ($su * $sI);

        return [$x, $y, $z];
    }
}
