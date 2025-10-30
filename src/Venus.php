<?php

namespace Swisseph;

/**
 * Low-precision heliocentric ecliptic coordinates of Venus (J2000 frame) in AU.
 */
final class Venus
{
    /**
     * Compute heliocentric ecliptic rectangular coordinates (x,y,z) in AU.
     * @param float $jd_tt Julian Day (TT)
     * @return array{0: float, 1: float, 2: float}
     */
    public static function heliocentricRectEclAU(float $jd_tt): array
    {
        $T = ($jd_tt - 2451545.0) / 36525.0;

        // Approximate orbital elements near J2000 (deg and AU)
        $a = 0.72333199; // AU
        $e = 0.00677672 - 0.00004107 * $T;
        $I = 3.394662 - 0.0008568 * $T;
        $L = 181.97909950 + 58517.81538729 * $T; // mean longitude
        $long_peri = 131.60246718 + 0.00268329 * $T; // longitude of perihelion ϖ
        $Omega = 76.67984255 - 0.27769418 * $T; // ascending node Ω

        $M = Math::normAngleDeg($L - $long_peri);
        $omega = Math::normAngleDeg($long_peri - $Omega);

        $Mr = Math::degToRad($M);
        $Ir = Math::degToRad($I);
        $Omegar = Math::degToRad($Omega);
        $omegar = Math::degToRad($omega);

        // Kepler equation solve for E
        $E = $Mr;
        for ($k = 0; $k < 10; $k++) {
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

        $u = $omegar + $v;
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
