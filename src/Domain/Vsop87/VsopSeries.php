<?php

namespace Swisseph\Domain\Vsop87;

/**
 * Represents one VSOP series (e.g. L, B, or R) grouped by polynomial degree n.
 * data[n] = list of terms A*cos(B + C*t).
 */
class VsopSeries
{
    /** @var array<int, list<array{A: float, B: float, C: float}>> */
    private array $data = [];

    public function addTerm(int $power, float $A, float $B, float $C): void
    {
        $this->data[$power] ??= [];
        $this->data[$power][] = ['A' => $A, 'B' => $B, 'C' => $C];
    }

    /**
     * Evaluate full power series sum: sum_n ( t^n * sum_i A_i * cos(B_i + C_i * t) ).
     */
    public function evaluate(float $t): float
    {
        $sum = 0.0;
        foreach ($this->data as $n => $terms) {
            $inner = 0.0;
            foreach ($terms as $term) {
                $inner += $term['A'] * cos($term['B'] + $term['C'] * $t);
            }
            $sum += $inner * ($n === 0 ? 1.0 : pow($t, $n));
        }
        return $sum;
    }

    public function powers(): array
    {
        $keys = array_keys($this->data);
        sort($keys);
        return $keys;
    }
}
