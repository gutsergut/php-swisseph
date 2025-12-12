<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

/**
 * Basic utility functions for heliacal calculations
 * Port from swehel.c lines 155-1812
 */
class HeliacalUtils
{
    /**
     * Minimum of two values
     * Port from swehel.c:155-159
     */
    public static function min(float $a, float $b): float
    {
        return $a <= $b ? $a : $b;
    }

    /**
     * Maximum of two values
     * Port from swehel.c:162-167
     */
    public static function max(float $a, float $b): float
    {
        return $a >= $b ? $a : $b;
    }

    /**
     * Hyperbolic tangent
     * Port from swehel.c:170-173
     *
     * @param float $x Input value
     * @return float tanh(x) = (e^x - e^-x) / (e^x + e^-x)
     */
    public static function tanh(float $x): float
    {
        $expX = exp($x);
        $expNegX = exp(-$x);
        return ($expX - $expNegX) / ($expX + $expNegX);
    }

    /**
     * Sign of number
     * Port from swehel.c:864-878
     *
     * @param float $x Input value
     * @return int -1 if negative, +1 if positive or zero
     */
    public static function sgn(float $x): int
    {
        if ($x < 0.0) {
            return -1;
        }
        return 1;
    }

    /**
     * Convert Celsius to Kelvin
     * Port from swehel.c:589-598
     *
     * @param float $temp Temperature in Celsius
     * @return float Temperature in Kelvin
     */
    public static function kelvin(float $temp): float
    {
        // C2K = 273.15
        return $temp + HeliacalConstants::C2K;
    }

    /**
     * Solve quadratic equation and find crossing point
     * Port from swehel.c:1753-1757
     *
     * Used for finding when two parabolas cross
     *
     * @param float $a Coefficient A
     * @param float $b Coefficient B
     * @param float $c Coefficient C
     * @param float $d Coefficient D
     * @return float Crossing point
     */
    public static function crossing(float $a, float $b, float $c, float $d): float
    {
        return (-$b + sqrt($b * $b + $a * ($c + $d))) / $a;
    }

    /**
     * Find x coordinate of minimum of parabola
     * Port from swehel.c:1791-1805
     *
     * For parabola y = A*x^2 + B*x + C
     * Minimum at x = -B / (2*A)
     *
     * @param float $a Coefficient A
     * @param float $b Coefficient B
     * @param float $c Coefficient C (not used in calculation)
     * @return float X coordinate of minimum
     */
    public static function x2min(float $a, float $b, float $c): float
    {
        // C parameter is not used in the C code, kept for API compatibility
        return -$b / (2.0 * $a);
    }

    /**
     * Evaluate parabola at given x
     * Port from swehel.c:1807-1810
     *
     * Returns y = A*x^2 + B*x + C
     *
     * @param float $a Coefficient A
     * @param float $b Coefficient B
     * @param float $c Coefficient C
     * @param float $x X coordinate
     * @return float Y value at x
     */
    public static function funct2(float $a, float $b, float $c, float $x): float
    {
        return $a * $x * $x + $b * $x + $c;
    }

    /**
     * Safe string copy (VB-style)
     * Port from swehel.c:1812-1859
     *
     * Copies string ensuring no buffer overflow
     * In PHP, this is handled automatically, but we keep the function for API compatibility
     *
     * @param string $sin Input string
     * @return string Output string
     */
    public static function strcpyVBsafe(string $sin): string
    {
        // In PHP, strings are immutable and automatically sized
        // This function is mainly for C compatibility where buffer overflow is a concern
        return $sin;
    }

    /**
     * Convert star name to lowercase
     * Port from swehel.c:1446-1462
     *
     * Converts star name to lowercase for case-insensitive comparison
     * Preserves spaces and special characters
     *
     * @param string $str Star name
     * @return string Lowercase star name
     */
    public static function toLowerStringStar(string $str): string
    {
        // Simple lowercase conversion
        // In C code, this manually converts each character to lowercase
        // PHP's mb_strtolower handles Unicode properly
        return mb_strtolower($str, 'UTF-8');
    }
}
