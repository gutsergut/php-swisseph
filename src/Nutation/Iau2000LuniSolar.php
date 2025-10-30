<?php

declare(strict_types=1);

namespace Swisseph\Nutation;

use Swisseph\Data\NutationTables2000;
use Swisseph\Math;

/**
 * IAU 2000 Luni-Solar Nutation
 *
 * Calculates luni-solar nutation component using MHB2000 model.
 *
 * Supports:
 * - IAU 2000A: full 678-term series
 * - IAU 2000B: truncated 77-term series
 *
 * References:
 * - Mathews, Herring & Buffett (2002)
 * - IERS Conventions 2003
 */
final class Iau2000LuniSolar
{
    /**
     * Calculate luni-solar nutation
     *
     * @param float $jd Julian day (TT)
     * @param array{M: float, SM: float, F: float, D: float, OM: float} $args Fundamental arguments (radians)
     * @param bool $is2000B Use IAU 2000B (77 terms) instead of 2000A (678 terms)
     * @return array{0: float, 1: float} [dpsi, deps] in degrees (not radians!)
     */
    public static function calc(float $jd, array $args, bool $is2000B = false): array
    {
        $T = ($jd - 2451545.0) / 36525.0;

        $M = $args['M'];
        $SM = $args['SM'];
        $F = $args['F'];
        $D = $args['D'];
        $OM = $args['OM'];

        $dpsi = 0.0;
        $deps = 0.0;

        // Get tables
        $nls = NutationTables2000::getNls();
        $cls = NutationTables2000::getCls();

        // Determine number of terms
        $numTerms = $is2000B ? NutationTables2000::NLS_2000B : NutationTables2000::NLS;

        // Iterate in reverse order (starting with smallest terms for better accuracy)
        for ($i = $numTerms - 1; $i >= 0; $i--) {
            // Get argument multipliers
            $n0 = $nls[$i][0]; // M  coefficient
            $n1 = $nls[$i][1]; // SM coefficient
            $n2 = $nls[$i][2]; // F  coefficient
            $n3 = $nls[$i][3]; // D  coefficient
            $n4 = $nls[$i][4]; // OM coefficient

            // Compute argument: n0*M + n1*SM + n2*F + n3*D + n4*OM
            $darg = $n0 * $M + $n1 * $SM + $n2 * $F + $n3 * $D + $n4 * $OM;
            $darg = Math::normAngleRad($darg);

            $sinarg = sin($darg);
            $cosarg = cos($darg);

            // Get coefficients (6 values per term)
            // Format: [sin_psi, t*sin_psi, cos_psi, cos_eps, t*cos_eps, sin_eps]
            $c0 = $cls[$i][0]; // sin coefficient for longitude
            $c1 = $cls[$i][1]; // t*sin coefficient for longitude
            $c2 = $cls[$i][2]; // cos coefficient for longitude
            $c3 = $cls[$i][3]; // cos coefficient for obliquity
            $c4 = $cls[$i][4]; // t*cos coefficient for obliquity
            $c5 = $cls[$i][5]; // sin coefficient for obliquity

            // Accumulate nutation in longitude (dpsi)
            // dpsi += (c0 + c1*T) * sin(arg) + c2 * cos(arg)
            $dpsi += ($c0 + $c1 * $T) * $sinarg + $c2 * $cosarg;

            // Accumulate nutation in obliquity (deps)
            // deps += (c3 + c4*T) * cos(arg) + c5 * sin(arg)
            $deps += ($c3 + $c4 * $T) * $cosarg + $c5 * $sinarg;
        }

        // Convert from 0.1 microarcseconds to degrees
        $dpsi *= NutationTables2000::O1MAS2DEG;
        $deps *= NutationTables2000::O1MAS2DEG;

        return [$dpsi, $deps];
    }
}
