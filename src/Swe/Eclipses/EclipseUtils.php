<?php

declare(strict_types=1);

namespace Swisseph\Swe\Eclipses;

use Swisseph\Constants;

/**
 * Eclipse utility functions
 * Ported from swecl.c (Swiss Ephemeris C library)
 *
 * Contains mathematical utilities used by all eclipse calculations:
 * - find_maximum: parabolic interpolation to find maximum
 * - find_zero: quadratic formula to find two zeros
 * - squareSum: sum of squares for vector magnitude
 */
class EclipseUtils
{
    // Physical constants from swecl.c:75-87
    // Note: AUNIT is defined in Constants.php as AU in meters
    public const DSUN = 1392000000.0 / Constants::AUNIT;  // Sun diameter in AU
    public const DMOON = 3476300.0 / Constants::AUNIT;     // Moon diameter in AU
    public const DEARTH = (6378140.0 * 2) / Constants::AUNIT;  // Earth diameter in AU
    public const RSUN = self::DSUN / 2.0;   // Sun radius in AU
    public const RMOON = self::DMOON / 2.0; // Moon radius in AU
    public const REARTH = self::DEARTH / 2.0; // Earth radius in AU

    // Geocentric altitude limits for eclipse calculations (from swetest.c and internal code)
    public const SEI_ECL_GEOALT_MIN = -500000.0;  // -500 km
    public const SEI_ECL_GEOALT_MAX = 50000000.0; // 50000 km

    /**
     * Find maximum of parabola through 3 points
     * Ported from swecl.c:4133-4147
     *
     * Uses parabolic interpolation to find the maximum of a function
     * sampled at three equally-spaced points.
     *
     * Given y0, y1, y2 at x = -dx, 0, +dx:
     * Fit parabola: y = a*x^2 + b*x + c
     * Find maximum at x = -b/(2a)
     *
     * @param float $y00 Function value at x = -dx
     * @param float $y11 Function value at x = 0
     * @param float $y2  Function value at x = +dx
     * @param float $dx  Step size
     * @param float &$dxret Output: x offset of maximum from center point
     * @param float|null &$yret Output: y value at maximum (optional)
     * @return int OK (Constants::OK)
     */
    public static function findMaximum(
        float $y00,
        float $y11,
        float $y2,
        float $dx,
        float &$dxret,
        ?float &$yret = null
    ): int {
        // Fit parabola y = a*x^2 + b*x + c to three points
        $c = $y11;
        $b = ($y2 - $y00) / 2.0;
        $a = ($y2 + $y00) / 2.0 - $c;

        // Maximum at x = -b / (2a)
        $x = -$b / 2.0 / $a;
        $y = (4.0 * $a * $c - $b * $b) / 4.0 / $a;

        // Convert to offset from center point
        $dxret = ($x - 1.0) * $dx;

        if ($yret !== null) {
            $yret = $y;
        }

        return Constants::OK;
    }

    /**
     * Find two zeros of parabola through 3 points
     * Ported from swecl.c:4149-4163
     *
     * Uses quadratic formula to find where parabola crosses zero.
     * Given y0, y1, y2 at x = -dx, 0, +dx:
     * Fit parabola: y = a*x^2 + b*x + c
     * Solve a*x^2 + b*x + c = 0
     *
     * @param float $y00 Function value at x = -dx
     * @param float $y11 Function value at x = 0
     * @param float $y2  Function value at x = +dx
     * @param float $dx  Step size
     * @param float &$dxret Output: first zero offset
     * @param float &$dxret2 Output: second zero offset
     * @return int OK or ERR if no real roots
     */
    public static function findZero(
        float $y00,
        float $y11,
        float $y2,
        float $dx,
        float &$dxret,
        float &$dxret2
    ): int {
        // Fit parabola
        $c = $y11;
        $b = ($y2 - $y00) / 2.0;
        $a = ($y2 + $y00) / 2.0 - $c;

        // Check discriminant
        $discriminant = $b * $b - 4.0 * $a * $c;
        if ($discriminant < 0) {
            return Constants::ERR;
        }

        // Quadratic formula
        $sqrtDisc = sqrt($discriminant);
        $x1 = (-$b + $sqrtDisc) / 2.0 / $a;
        $x2 = (-$b - $sqrtDisc) / 2.0 / $a;

        // Convert to offsets
        $dxret = ($x1 - 1.0) * $dx;
        $dxret2 = ($x2 - 1.0) * $dx;

        return Constants::OK;
    }

    /**
     * Calculate sum of squares for 3D vector
     * Ported from sweph.h:308 (macro: square_sum(x))
     *
     * Used to compute squared magnitude of position vectors:
     * r^2 = x^2 + y^2 + z^2
     *
     * @param array $x 3D vector [x, y, z]
     * @return float Sum of squares
     */
    public static function squareSum(array $x): float
    {
        return $x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2];
    }

    /**
     * Normalize angle to [0, 360) degrees
     * Wrapper around swe_degnorm for convenience
     *
     * @param float $deg Angle in degrees
     * @return float Normalized angle
     */
    public static function degNorm(float $deg): float
    {
        return \swe_degnorm($deg);
    }
}
