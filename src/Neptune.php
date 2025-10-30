<?php

namespace Swisseph;

/**
 * Low-precision heliocentric ecliptic rectangular coordinates of Neptune (J2000) in AU.
 * Based on simplified secular elements around J2000 (Meeus-like approximation).
 */
final class Neptune
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
        $a = 30.068963; // AU (semi-major axis)
        $e = 0.00858587 + 0.0000250 * $T; // eccentricity (linearized)
        $I = 1.769953 - 0.0093082 * $T; // inclination (deg)
        $L = 304.88003 + 218.45945325 * $T; // mean longitude (deg)
        $long_peri = 46.681587 + 0.0100998 * $T; // longitude of perihelion (deg)
        $Omega = 131.784057 - 0.0061651 * $T; // ascending node (deg)

        $M = Math::normAngleDeg($L - $long_peri); // mean anomaly (deg)
        $omega = Math::normAngleDeg($long_peri - $Omega); // argument of perihelion

        // Radians
        $Mr = Math::degToRad($M);
        $Ir = Math::degToRad($I);
        $Omegar = Math::degToRad($Omega);
        $omegar = Math::degToRad($omega);

        // Solve Kepler's equation for E (eccentric anomaly) via Newton-Raphson
        $E = $Mr;
        for ($k = 0; $k < 30; $k++) {
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
