<?php

namespace Swisseph\SwephFile;

/**
 * Chebyshev polynomial interpolation
 *
 * Port of swi_echeb() and swi_edcheb() from swephlib.c
 *
 * Evaluates Chebyshev series and its derivative for ephemeris interpolation
 */
final class ChebyshevInterpolation
{
    /**
     * Evaluate Chebyshev series
     *
     * Port of swi_echeb() from swephlib.c:171
     *
     * @param float $x Normalized time in range [-1, 1]
     * @param array $coef Chebyshev coefficients
     * @param int $ncf Number of coefficients to use
     * @return float Interpolated value
     */
    public static function evaluate(float $x, array $coef, int $ncf): float
    {
        $x2 = $x * 2.0;
        $br = 0.0;
        $brp2 = 0.0;
        $brpp = 0.0;

        for ($j = $ncf - 1; $j >= 0; $j--) {
            $brp2 = $brpp;
            $brpp = $br;
            $br = $x2 * $brpp - $brp2 + $coef[$j];
        }

        return ($br - $brp2) * 0.5;
    }

    /**
     * Evaluate derivative of Chebyshev series
     *
     * Port of swi_edcheb() from swephlib.c:190
     *
     * @param float $x Normalized time in range [-1, 1]
     * @param array $coef Chebyshev coefficients
     * @param int $ncf Number of coefficients to use
     * @return float Interpolated derivative value
     */
    public static function evaluateDerivative(float $x, array $coef, int $ncf): float
    {
        $x2 = $x * 2.0;
        $bf = 0.0;
        $bj = 0.0;
        $xjp2 = 0.0;
        $xjpl = 0.0;
        $bjp2 = 0.0;
        $bjpl = 0.0;

        for ($j = $ncf - 1; $j >= 1; $j--) {
            $dj = (float)($j + $j);
            $xj = $coef[$j] * $dj + $xjp2;
            $bj = $x2 * $bjpl - $bjp2 + $xj;
            $bf = $bjp2;
            $bjp2 = $bjpl;
            $bjpl = $bj;
            $xjp2 = $xjpl;
            $xjpl = $xj;
        }

        return ($bj - $bf) * 0.5;
    }
}
