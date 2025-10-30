<?php

namespace Swisseph;

/**
 * Low-precision heliocentric ecliptic rectangular coordinates of Pluto (J2000) in AU.
 * Uses simplified Keplerian elements linearized near J2000; sufficient for smoke tests.
 */
final class Pluto
{
    /**
     * Compute heliocentric ecliptic rectangular coordinates (x,y,z) in AU.
     * @param float $jd_tt Julian Day (TT)
     * @return array{0: float, 1: float, 2: float}
     */
    public static function heliocentricRectEclAU(float $jd_tt): array
    {
        $T = ($jd_tt - 2451545.0) / 36525.0; // centuries from J2000

        // Approximate orbital elements (deg, AU), linear with T
        $a = 39.48211675; // AU
        $e = 0.24882730 + 0.0000517 * $T;
        $I = 17.14001206 - 0.00004818 * $T; // inclination
        $L = 238.92903833 + 145.20780515 * $T; // mean longitude
        $long_peri = 224.06891629 - 0.04062942 * $T; // longitude of perihelion
        $Omega = 110.30393684 - 0.01183482 * $T; // ascending node

        $M = Math::normAngleDeg($L - $long_peri); // mean anomaly
        $omega = Math::normAngleDeg($long_peri - $Omega); // arg of perihelion

        // Radians
        $Mr = Math::degToRad($M);
        $Ir = Math::degToRad($I);
        $Omegar = Math::degToRad($Omega);
        $omegar = Math::degToRad($omega);

        // Solve Kepler's equation for E via Newton-Raphson
        $E = $Mr;
        for ($k = 0; $k < 40; $k++) {
            $f = $E - $e * sin($E) - $Mr;
            $fp = 1 - $e * cos($E);
            $dE = -$f / $fp;
            $E += $dE;
            if (abs($dE) < 1e-13) {
                break;
            }
        }

        $cosE = cos($E);
        $sinE = sin($E);
        $sqrt1me2 = sqrt(1 - $e * $e);
        $v = atan2($sqrt1me2 * $sinE, $cosE - $e); // true anomaly
        $r = $a * (1 - $e * $cosE); // radius vector

        $u = $omegar + $v; // argument of latitude
        $cu = cos($u);
        $su = sin($u);
        $cO = cos($Omegar);
        $sO = sin($Omegar);
        $cI = cos($Ir);
        $sI = sin($Ir);

        // Heliocentric rectangular ecliptic coordinates (J2000)
        $x = $r * ($cO * $cu - $sO * $su * $cI);
        $y = $r * ($sO * $cu + $cO * $su * $cI);
        $z = $r * ($su * $sI);

        return [$x, $y, $z];
    }
}
