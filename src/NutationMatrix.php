<?php

declare(strict_types=1);

namespace Swisseph;

/**
 * Constructs nutation rotation matrix.
 *
 * This matrix transforms coordinates from mean equator and equinox of date
 * to true equator and equinox of date (i.e., applies nutation correction).
 *
 * Reference: sweph.c nut_matrix()
 *
 * The matrix implements the rotation:
 *   R_nut = R_x(-eps_mean) * R_z(-dpsi) * R_x(eps_mean + deps)
 *
 * where:
 *   dpsi = nutation in longitude
 *   deps = nutation in obliquity
 *   eps_mean = mean obliquity of ecliptic
 *   eps = eps_mean + deps = true obliquity
 */
class NutationMatrix
{
    /**
     * Build nutation rotation matrix.
     *
     * @param float $dpsi Nutation in longitude (radians)
     * @param float $deps Nutation in obliquity (radians)
     * @param float $epsMean Mean obliquity of ecliptic (radians)
     * @param float $sinEpsMean sin(mean obliquity) - optional, calculated if null
     * @param float $cosEpsMean cos(mean obliquity) - optional, calculated if null
     * @return array 3x3 rotation matrix as flat array [m00, m01, m02, m10, m11, m12, m20, m21, m22]
     */
    public static function build(
        float $dpsi,
        float $deps,
        float $epsMean,
        ?float $sinEpsMean = null,
        ?float $cosEpsMean = null
    ): array {
        // Calculate true obliquity
        $eps = $epsMean + $deps;

        // Precompute trigonometric functions
        $sinpsi = sin($dpsi);
        $cospsi = cos($dpsi);

        // If sin/cos of mean obliquity not provided, calculate them
        if ($sinEpsMean === null || $cosEpsMean === null) {
            $sineps0 = sin($epsMean);
            $coseps0 = cos($epsMean);
        } else {
            $sineps0 = $sinEpsMean;
            $coseps0 = $cosEpsMean;
        }

        $sineps = sin($eps);
        $coseps = cos($eps);

        // Build nutation matrix following C implementation
        // matrix[row][col] in C becomes flat array: row*3 + col
        $matrix = [];

        // Row 0
        $matrix[0] = $cospsi;
        $matrix[1] = $sinpsi * $coseps;
        $matrix[2] = $sinpsi * $sineps;

        // Row 1
        $matrix[3] = -$sinpsi * $coseps0;
        $matrix[4] = $cospsi * $coseps * $coseps0 + $sineps * $sineps0;
        $matrix[5] = $cospsi * $sineps * $coseps0 - $coseps * $sineps0;

        // Row 2
        $matrix[6] = -$sinpsi * $sineps0;
        $matrix[7] = $cospsi * $coseps * $sineps0 - $sineps * $coseps0;
        $matrix[8] = $cospsi * $sineps * $sineps0 + $coseps * $coseps0;

        return $matrix;
    }

    /**
     * Apply nutation matrix to a position vector.
     *
     * Transforms from mean equator/equinox of date to true equator/equinox of date.
     *
     * @param array $matrix 3x3 nutation matrix (flat array of 9 elements)
     * @param array $pos Position vector [x, y, z] in mean equator coordinates
     * @return array Position vector [x, y, z] in true equator coordinates
     */
    public static function apply(array $matrix, array $pos): array
    {
        $x = $pos[0] ?? 0.0;
        $y = $pos[1] ?? 0.0;
        $z = $pos[2] ?? 0.0;

        return [
            $matrix[0] * $x + $matrix[1] * $y + $matrix[2] * $z,
            $matrix[3] * $x + $matrix[4] * $y + $matrix[5] * $z,
            $matrix[6] * $x + $matrix[7] * $y + $matrix[8] * $z,
        ];
    }
}
