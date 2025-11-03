<?php

declare(strict_types=1);

namespace Swisseph;

/**
 * Vector mathematics utilities for 3D operations
 *
 * Port of vector functions from Swiss Ephemeris swephlib.c
 */
class VectorMath
{
    /**
     * Cross product of two 3D vectors: result = v1 × v2
     *
     * Port of swi_cross_prod() from swephlib.c
     *
     * @param array $v1 First vector [x, y, z]
     * @param array $v2 Second vector [x, y, z]
     * @param array &$result Output vector [x, y, z]
     */
    public static function crossProduct(array $v1, array $v2, array &$result): void
    {
        $result = [
            $v1[1] * $v2[2] - $v1[2] * $v2[1],
            $v1[2] * $v2[0] - $v1[0] * $v2[2],
            $v1[0] * $v2[1] - $v1[1] * $v2[0],
        ];
    }

    /**
     * Dot product of two 3D vectors: result = v1 · v2
     *
     * Port of dot_prod() from swecl.c
     *
     * @param array $v1 First vector [x, y, z]
     * @param array $v2 Second vector [x, y, z]
     * @return float Dot product scalar
     */
    public static function dotProduct(array $v1, array $v2): float
    {
        return $v1[0] * $v2[0] + $v1[1] * $v2[1] + $v1[2] * $v2[2];
    }

    /**
     * Sum of squares of vector components
     *
     * Port of square_sum() from swecl.c
     *
     * @param array $v Vector [x, y, z]
     * @return float Sum of squares (x² + y² + z²)
     */
    public static function squareSum(array $v): float
    {
        return $v[0] * $v[0] + $v[1] * $v[1] + $v[2] * $v[2];
    }

    /**
     * Magnitude (length) of a 3D vector
     *
     * @param array $v Vector [x, y, z]
     * @return float Magnitude √(x² + y² + z²)
     */
    public static function magnitude(array $v): float
    {
        return sqrt(self::squareSum($v));
    }

    /**
     * Normalize a 3D vector to unit length
     *
     * @param array $v Input vector [x, y, z]
     * @param array &$result Output normalized vector
     * @return bool True if successful, false if magnitude is too small
     */
    public static function normalize(array $v, array &$result): bool
    {
        $mag = self::magnitude($v);

        if ($mag < 1e-20) {
            $result = [0.0, 0.0, 0.0];
            return false;
        }

        $result = [
            $v[0] / $mag,
            $v[1] / $mag,
            $v[2] / $mag,
        ];

        return true;
    }

    /**
     * Scale a vector by a scalar factor
     *
     * @param array $v Input vector [x, y, z]
     * @param float $scalar Scaling factor
     * @param array &$result Output scaled vector
     */
    public static function scale(array $v, float $scalar, array &$result): void
    {
        $result = [
            $v[0] * $scalar,
            $v[1] * $scalar,
            $v[2] * $scalar,
        ];
    }

    /**
     * Add two 3D vectors
     *
     * @param array $v1 First vector [x, y, z]
     * @param array $v2 Second vector [x, y, z]
     * @param array &$result Output vector v1 + v2
     */
    public static function add(array $v1, array $v2, array &$result): void
    {
        $result = [
            $v1[0] + $v2[0],
            $v1[1] + $v2[1],
            $v1[2] + $v2[2],
        ];
    }

    /**
     * Subtract two 3D vectors
     *
     * @param array $v1 First vector [x, y, z]
     * @param array $v2 Second vector [x, y, z]
     * @param array &$result Output vector v1 - v2
     */
    public static function subtract(array $v1, array $v2, array &$result): void
    {
        $result = [
            $v1[0] - $v2[0],
            $v1[1] - $v2[1],
            $v1[2] - $v2[2],
        ];
    }

    /**
     * Dot product of two unit (normalized) vectors
     * Ported from swephlib.c:453-462 (swi_dot_prod_unit)
     *
     * Computes dot product of normalized vectors:
     * 1. Calculate dot product: dop = x·y
     * 2. Normalize by magnitudes: dop / |x| / |y|
     * 3. Clamp to [-1, 1] to avoid numerical issues with acos()
     *
     * Used for calculating angular distances between position vectors.
     *
     * @param array $x First vector [x, y, z]
     * @param array $y Second vector [x, y, z]
     * @return float Dot product of unit vectors (range [-1, 1])
     */
    public static function dotProductUnit(array $x, array $y): float
    {
        // Calculate dot product
        $dop = $x[0] * $y[0] + $x[1] * $y[1] + $x[2] * $y[2];

        // Calculate magnitudes
        $e1 = sqrt($x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2]);
        $e2 = sqrt($y[0] * $y[0] + $y[1] * $y[1] + $y[2] * $y[2]);

        // Normalize
        $dop /= $e1;
        $dop /= $e2;

        // Clamp to [-1, 1] for numerical stability with acos()
        if ($dop > 1.0) {
            $dop = 1.0;
        }
        if ($dop < -1.0) {
            $dop = -1.0;
        }

        return $dop;
    }
}
