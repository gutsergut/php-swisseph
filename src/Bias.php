<?php

declare(strict_types=1);

namespace Swisseph;

/**
 * Frame bias transformation between ICRS and J2000.
 *
 * The frame bias represents the offset between:
 * - ICRS (International Celestial Reference System): modern fundamental reference frame
 * - J2000.0: classical equinox-based FK5 reference frame at J2000.0
 *
 * This is a small but significant correction (~0.1 arcseconds) introduced by IAU 2000/2006.
 *
 * Reference: swephlib.c swi_bias()
 *
 * Two models are available:
 * - IAU 2000: Original frame bias matrix
 * - IAU 2006: Updated frame bias matrix (default, minimal difference)
 *
 * Note: This implementation does not include JPL Horizons approximate corrections (SEFLG_JPLHOR_APPROX),
 * which are specific to JPL ephemerides and require additional time-dependent data.
 */
class Bias
{
    // Bias model constants
    public const MODEL_NONE = 1;
    public const MODEL_IAU_2000 = 2;
    public const MODEL_IAU_2006 = 3;
    public const MODEL_DEFAULT = self::MODEL_IAU_2006;

    /**
     * Apply frame bias transformation to position vector.
     *
     * Full implementation with JPL Horizons approximate corrections.
     * Port of swi_bias() from swephlib.c
     *
     * Transforms coordinates between ICRS and J2000.0 frames.
     *
     * @param array $x Position vector [x, y, z] or [x, y, z, dx, dy, dz] with velocities
     * @param float $tjd Julian day (TT) - required for JPL Horizons corrections
     * @param int $iflag Calculation flags (for SEFLG_JPLHOR_APPROX, SEFLG_SPEED)
     * @param int $model Bias model (MODEL_IAU_2000, MODEL_IAU_2006, or MODEL_NONE)
     * @param bool $backward If true, transform J2000 → ICRS; if false, transform ICRS → J2000
     * @param int $jplhoraModel JPL Horizons mode (SEMOD_JPLHORA_1/2/3)
     * @return array Transformed position vector (same format as input)
     */
    public static function apply(
        array $x,
        float $tjd,
        int $iflag,
        int $model = self::MODEL_DEFAULT,
        bool $backward = false,
        int $jplhoraModel = JplHorizonsApprox::SEMOD_JPLHORA_DEFAULT
    ): array {
        // No transformation if model is NONE
        if ($model === self::MODEL_NONE) {
            return $x;
        }

        // Early exit for certain JPL Horizons modes
        if ($iflag & Constants::SEFLG_JPLHOR_APPROX) {
            if ($jplhoraModel === JplHorizonsApprox::SEMOD_JPLHORA_2) {
                return $x;
            }
            // Mode 3 with early epochs: skip bias
            if ($jplhoraModel === JplHorizonsApprox::SEMOD_JPLHORA_3 && $tjd < 2437846.5) {
                return $x;
            }
        }

        // Get bias matrix for selected model
        $rb = self::getMatrix($model);

        // Determine if we have speed vector
        $hasSpeed = ($iflag & Constants::SEFLG_SPEED) && count($x) >= 6;

        // Apply transformation with JPL Horizons corrections
        // Initialize result with same length as input (preserve structure)
        $result = $hasSpeed ? [0.0, 0.0, 0.0, 0.0, 0.0, 0.0] : [0.0, 0.0, 0.0];

        if ($backward) {
            // J2000 → ICRS
            // Step 1: Apply JPL Horizons correction first (if enabled)
            $xx = $x;
            JplHorizonsApprox::apply($xx, $tjd, $iflag, true, $jplhoraModel);

            // Step 2: Apply bias matrix (transpose)
            for ($i = 0; $i <= 2; $i++) {
                $result[$i] =
                    ($xx[0] ?? 0.0) * $rb[$i * 3 + 0] +
                    ($xx[1] ?? 0.0) * $rb[$i * 3 + 1] +
                    ($xx[2] ?? 0.0) * $rb[$i * 3 + 2];

                if ($hasSpeed) {
                    $result[$i + 3] =
                        ($xx[3] ?? 0.0) * $rb[$i * 3 + 0] +
                        ($xx[4] ?? 0.0) * $rb[$i * 3 + 1] +
                        ($xx[5] ?? 0.0) * $rb[$i * 3 + 2];
                }
            }
        } else {
            // ICRS → J2000
            // Step 1: Apply bias matrix
            for ($i = 0; $i <= 2; $i++) {
                $result[$i] =
                    ($x[0] ?? 0.0) * $rb[0 * 3 + $i] +
                    ($x[1] ?? 0.0) * $rb[1 * 3 + $i] +
                    ($x[2] ?? 0.0) * $rb[2 * 3 + $i];

                if ($hasSpeed) {
                    $result[$i + 3] =
                        ($x[3] ?? 0.0) * $rb[0 * 3 + $i] +
                        ($x[4] ?? 0.0) * $rb[1 * 3 + $i] +
                        ($x[5] ?? 0.0) * $rb[2 * 3 + $i];
                }
            }

            // Step 2: Apply JPL Horizons correction (if enabled)
            JplHorizonsApprox::apply($result, $tjd, $iflag, false, $jplhoraModel);
        }

        return $result;
    }

    /**
     * Get frame bias matrix for specified model.
     *
     * Returns 3x3 rotation matrix as flat array of 9 elements.
     *
     * @param int $model Bias model (MODEL_IAU_2000 or MODEL_IAU_2006)
     * @return array Bias matrix [m00, m01, m02, m10, m11, m12, m20, m21, m22]
     */
    public static function getMatrix(int $model = self::MODEL_DEFAULT): array
    {
        if ($model === self::MODEL_IAU_2006) {
            // Frame bias matrix IAU 2006
            // From swephlib.c lines 2229-2237
            return [
                +0.99999999999999412,  // [0][0]
                +0.00000007078368695,  // [0][1]
                -0.00000008056214212,  // [0][2]
                -0.00000007078368961,  // [1][0]
                +0.99999999999999700,  // [1][1]
                -0.00000003306427981,  // [1][2]
                +0.00000008056213978,  // [2][0]
                +0.00000003306428553,  // [2][1]
                +0.99999999999999634,  // [2][2]
            ];
        } else {
            // Frame bias matrix IAU 2000 (default fallback)
            // From swephlib.c lines 2239-2247
            return [
                +0.9999999999999942,  // [0][0]
                +0.0000000707827948,  // [0][1]
                -0.0000000805621738,  // [0][2]
                -0.0000000707827974,  // [1][0]
                +0.9999999999999969,  // [1][1]
                -0.0000000330604088,  // [1][2]
                +0.0000000805621715,  // [2][0]
                +0.0000000330604145,  // [2][1]
                +0.9999999999999962,  // [2][2]
            ];
        }
    }

    /**
     * Get model name as string.
    /**
     * @param int $model Bias model constant
     * @return string Model name
     */
    public static function getModelName(int $model): string
    {
        return match ($model) {
            self::MODEL_NONE => 'None',
            self::MODEL_IAU_2000 => 'IAU 2000',
            self::MODEL_IAU_2006 => 'IAU 2006',
            default => 'Unknown',
        };
    }

    /**
     * Apply frame bias transformation (simplified wrapper).
     *
     * Compatibility wrapper for swi_bias() C function.
     * Port of: swephlib.c swi_bias()
     *
     * @param array &$x Position vector [x, y, z] or [x, y, z, dx, dy, dz], modified in place
     * @param float $tjd Julian day (TT)
     * @param int $iflag Calculation flags
     * @param bool $backward If true, transform J2000 → ICRS; if false, transform ICRS → J2000
     */
    public static function bias(array &$x, float $tjd, int $iflag, bool $backward): void
    {
        $x = self::apply($x, $tjd, $iflag, self::MODEL_DEFAULT, $backward);
    }
}
