<?php

declare(strict_types=1);

namespace Swisseph;

use Swisseph\Nutation\Iau1980;
use Swisseph\Nutation\Iau2000;

/**
 * Nutation Calculator
 *
 * Main facade for all nutation models.
 * Provides automatic model selection based on flags and convenience methods.
 *
 * Supported models:
 * - IAU 1980 (Wahr): ~106 terms, ~0.001" accuracy
 * - IAU 2000A (MHB2000): 678 luni-solar + 687 planetary terms, ~0.0001" accuracy
 * - IAU 2000B: Truncated version with 77 luni-solar terms
 *
 * References:
 * - Wahr (1981) for IAU 1980
 * - Mathews, Herring & Buffett (2002) for IAU 2000
 * - IERS Conventions 2003, 2010
 */
final class Nutation
{
    // Nutation model constants
    public const MODEL_IAU_1980 = 1;
    public const MODEL_IAU_2000A = 2;
    public const MODEL_IAU_2000B = 3;

    // Default model
    public const MODEL_DEFAULT = self::MODEL_IAU_2000A;

    /**
     * Calculate nutation with automatic model selection
     *
     * @param float $jd Julian day (TT)
     * @param int $model Nutation model (MODEL_*)
     * @param bool $applyP03 Apply IAU 2006 P03 corrections (only for 2000A)
     * @return array{0: float, 1: float} [dpsi, deps] in radians
     */
    public static function calc(
        float $jd,
        int $model = self::MODEL_DEFAULT,
        bool $applyP03 = true
    ): array {
        switch ($model) {
            case self::MODEL_IAU_1980:
                return Iau1980::calc($jd, false);

            case self::MODEL_IAU_2000B:
                return Iau2000::calc($jd, true, false);

            case self::MODEL_IAU_2000A:
            default:
                return Iau2000::calc($jd, false, $applyP03);
        }
    }

    /**
     * Calculate nutation using IAU 1980 model
     *
     * @param float $jd Julian day (TT)
     * @param bool $useHerring1987 Apply Herring 1987 corrections
     * @return array{0: float, 1: float} [dpsi, deps] in radians
     */
    public static function calcIau1980(float $jd, bool $useHerring1987 = false): array
    {
        return Iau1980::calc($jd, $useHerring1987);
    }

    /**
     * Calculate nutation using IAU 2000A model
     *
     * @param float $jd Julian day (TT)
     * @param bool $applyP03 Apply IAU 2006 P03 corrections
     * @return array{0: float, 1: float} [dpsi, deps] in radians
     */
    public static function calcIau2000A(float $jd, bool $applyP03 = true): array
    {
        return Iau2000::calc($jd, false, $applyP03);
    }

    /**
     * Calculate nutation using IAU 2000B model (truncated)
     *
     * @param float $jd Julian day (TT)
     * @return array{0: float, 1: float} [dpsi, deps] in radians
     */
    public static function calcIau2000B(float $jd): array
    {
        return Iau2000::calc($jd, true, false);
    }

    /**
     * Select nutation model based on Swiss Ephemeris flags
     *
     * @param int $iflag Swiss Ephemeris calculation flags
     * @return int Nutation model constant
     */
    public static function selectModelFromFlags(int $iflag): int
    {
        // Check for explicit nutation model flag
        // Note: These constants should be defined in Constants.php
        // For now, return default

        // Future: check for SEFLG_SWIEPH (use 2000A) vs SEFLG_JPLEPH (use 1980)
        // Future: check astro_models[SE_MODEL_NUT] setting

        return self::MODEL_DEFAULT;
    }

    /**
     * Get nutation model name
     *
     * @param int $model Nutation model constant
     * @return string Model name
     */
    public static function getModelName(int $model): string
    {
        switch ($model) {
            case self::MODEL_IAU_1980:
                return 'IAU 1980 (Wahr)';
            case self::MODEL_IAU_2000A:
                return 'IAU 2000A (MHB2000)';
            case self::MODEL_IAU_2000B:
                return 'IAU 2000B (truncated)';
            default:
                return 'Unknown';
        }
    }
}
