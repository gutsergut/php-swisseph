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
}
