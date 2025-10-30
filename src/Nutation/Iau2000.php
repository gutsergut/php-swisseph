<?php

declare(strict_types=1);

namespace Swisseph\Nutation;

/**
 * IAU 2000 Nutation Calculator
 *
 * Main entry point for IAU 2000 nutation calculations.
 * Combines luni-solar and planetary components.
 *
 * Supports:
 * - IAU 2000A: Full model (678 luni-solar + 687 planetary terms)
 * - IAU 2000B: Truncated model (77 luni-solar terms only)
 *
 * References:
 * - Mathews, Herring & Buffett (2002) "Modeling of nutation and precession"
 * - IERS Conventions 2003
 * - Capitaine et al. (2005) IAU 2006/2000A precession-nutation
 */
final class Iau2000
{
    /**
     * Calculate IAU 2000 nutation
     *
     * @param float $jd Julian day (TT)
     * @param bool $is2000B Use IAU 2000B (truncated) instead of 2000A (full)
     * @param bool $applyP03 Apply IAU 2006 P03 corrections (only for 2000A)
     * @return array{0: float, 1: float} [dpsi, deps] in radians
     */
    public static function calc(
        float $jd,
        bool $is2000B = false,
        bool $applyP03 = true
    ): array {
        // Calculate fundamental arguments
        $args = FundamentalArguments::calcSimon1994($jd);

        // Calculate luni-solar nutation (degrees)
        [$dpsi, $deps] = Iau2000LuniSolar::calc($jd, $args, $is2000B);

        // Add planetary nutation (only for 2000A)
        if (!$is2000B) {
            [$dpsi_pl, $deps_pl] = Iau2000Planetary::calc($jd);
            $dpsi += $dpsi_pl;
            $deps += $deps_pl;

            // Apply IAU 2006 P03 corrections (if requested)
            if ($applyP03) {
                [$dpsi_p03, $deps_p03] = Iau2000Planetary::calcP03Corrections($jd, $args);
                $dpsi += $dpsi_p03;
                $deps += $deps_p03;
            }
        }

        // Convert from degrees to radians
        $dpsi *= M_PI / 180.0;
        $deps *= M_PI / 180.0;

        return [$dpsi, $deps];
    }
}
