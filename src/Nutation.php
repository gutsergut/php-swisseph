<?php

declare(strict_types=1);

namespace Swisseph;

use Swisseph\Nutation\Iau1980;
use Swisseph\Nutation\Iau2000;
use Swisseph\EopData;

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

    // JPLHORA mode constants from sweodef.h
    public const SEMOD_JPLHORA_1 = 1;  // IERS 1996 conventions (deprecated)
    public const SEMOD_JPLHORA_2 = 2;  // IERS 2006 conventions
    public const SEMOD_JPLHORA_3 = 3;  // Current Horizons method
    public const SEMOD_JPLHORA_DEFAULT = self::SEMOD_JPLHORA_3;

    // TJD limit for JPLHOR_APPROX mode 3 to use IAU 1980
    public const HORIZONS_TJD0_DPSI_DEPS_IAU1980 = 2437684.5;  // 1962-01-01

    // IAU2000 corrections for JPLHORA_2 mode (milliarcseconds)
    private const IAU2000_DPSI_CORRECTION = -41.7750 / 3600.0 / 1000.0;  // rad
    private const IAU2000_DEPS_CORRECTION = -6.8192 / 3600.0 / 1000.0;   // rad

    /**
     * Calculate nutation with iflag support for SEFLG_JPLHOR
     * Port of calc_nutation() from swephlib.c:2067-2120
     *
     * @param float $jd Julian day (TT)
     * @param int $iflag Calculation flags (SEFLG_JPLHOR, SEFLG_JPLHOR_APPROX)
     * @return array{0: float, 1: float} [dpsi, deps] in radians
     */
    public static function calcWithFlags(float $jd, int $iflag): array
    {
        $jplhoraModel = self::SEMOD_JPLHORA_DEFAULT;
        $isJplhor = false;

        // Determine if we should use JPL Horizons mode
        if ($iflag & Constants::SEFLG_JPLHOR) {
            $isJplhor = true;
        }
        if (($iflag & Constants::SEFLG_JPLHOR_APPROX)
            && $jplhoraModel === self::SEMOD_JPLHORA_3
            && $jd <= self::HORIZONS_TJD0_DPSI_DEPS_IAU1980) {
            $isJplhor = true;
        }

        if ($isJplhor) {
            // Use IAU 1980 nutation
            [$dpsi, $deps] = Iau1980::calc($jd, false);

            if ($iflag & Constants::SEFLG_JPLHOR) {
                // Apply EOP corrections from IERS data files
                $eop = EopData::getInstance();
                if ($eop->load() > 0) {
                    // Adjust date for data range
                    $j2 = $jd;
                    if ($j2 < EopData::TJD0_HORIZONS) {
                        $j2 = EopData::TJD0_HORIZONS;
                    }

                    // Add corrections (arcsec -> radians)
                    $dpsi += $eop->getDpsi($j2) / 3600.0 * (M_PI / 180.0);
                    $deps += $eop->getDeps($j2) / 3600.0 * (M_PI / 180.0);
                } else {
                    // Fallback to JPLHOR_APPROX: use fixed corrections at TJD0
                    $dpsi += EopData::DPSI_IAU1980_TJD0 / 3600.0 * (M_PI / 180.0);
                    $deps += EopData::DEPS_IAU1980_TJD0 / 3600.0 * (M_PI / 180.0);
                }
            } else {
                // JPLHOR_APPROX without EOP files: use fixed corrections
                $dpsi += EopData::DPSI_IAU1980_TJD0 / 3600.0 * (M_PI / 180.0);
                $deps += EopData::DEPS_IAU1980_TJD0 / 3600.0 * (M_PI / 180.0);
            }

            return [$dpsi, $deps];
        }

        // Check for JPLHOR_APPROX with mode 2 (IAU 2000 + corrections)
        if (($iflag & Constants::SEFLG_JPLHOR_APPROX) && $jplhoraModel === self::SEMOD_JPLHORA_2) {
            [$dpsi, $deps] = Iau2000::calc($jd, false, true);
            $dpsi += self::IAU2000_DPSI_CORRECTION * (M_PI / 180.0);
            $deps += self::IAU2000_DEPS_CORRECTION * (M_PI / 180.0);
            return [$dpsi, $deps];
        }

        // Default: use standard model
        return self::calc($jd, self::MODEL_DEFAULT, true);
    }

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
     * For SEFLG_JPLHOR/SEFLG_JPLHOR_APPROX, returns IAU 1980 model.
     * The actual EOP corrections are applied separately in calcWithFlags().
     *
     * @param int $iflag Swiss Ephemeris calculation flags
     * @return int Nutation model constant
     */
    public static function selectModelFromFlags(int $iflag): int
    {
        // JPLHOR modes use IAU 1980 nutation
        if ($iflag & (Constants::SEFLG_JPLHOR | Constants::SEFLG_JPLHOR_APPROX)) {
            return self::MODEL_IAU_1980;
        }

        // Default: IAU 2000A
        return self::MODEL_DEFAULT;
    }

    /**
     * Check if JPLHOR mode is active based on flags
     *
     * @param int $iflag Calculation flags
     * @return bool True if JPLHOR or JPLHOR_APPROX mode is active
     */
    public static function isJplhorMode(int $iflag): bool
    {
        return (bool)($iflag & (Constants::SEFLG_JPLHOR | Constants::SEFLG_JPLHOR_APPROX));
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

    /**
     * Alias for calcIau1980() for compatibility
     *
     * @param float $jdTT Julian Day TT
     * @return array [nutation_longitude_rad, nutation_obliquity_rad]
     */
    public static function nutationIau1980(float $jdTT): array
    {
        return self::calcIau1980($jdTT, false);
    }
}
