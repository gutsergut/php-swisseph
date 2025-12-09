<?php

namespace Swisseph\Domain\Vsop87;

class Calculator
{
    /**
     * Compute value for one coordinate (sum over series by power)
     * @param array<int,Series> $seriesByPower
     * @param float $T time in Julian millennia from J2000 (T = (JD_TT - 2451545.0)/365250)
     */
    private function sum(array $seriesByPower, float $T): float
    {
        ksort($seriesByPower);
        $acc = 0.0;
        foreach ($seriesByPower as $p => $series) {
            $inner = 0.0;
            foreach ($series->terms as $t) {
                $inner += $t->A * cos($t->B + $t->C * $T);
            }
            if ($p === 0) $acc += $inner; else $acc += $inner * pow($T, $p);
        }
        return $acc;
    }

    /**
     * Compute spherical heliocentric ecliptic coordinates (VSOP87): L, B, R
     * Returns [L, B, R] in radians for L,B and AU for R.
     */
    public function compute(PlanetModel $model, float $jdTT): array
    {
        $T = ($jdTT - 2451545.0) / 365250.0;
        $L = $this->sum($model->L, $T);
        $B = $this->sum($model->B, $T);
        $R = $this->sum($model->R, $T);
        return [$L, $B, $R];
    }
}
