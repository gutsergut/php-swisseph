<?php

namespace Swisseph\Domain\Vsop87;

/**
 * VSOP87 types and small helpers (no simplifications).
 * Series format: arrays of terms grouped by power of t (L0..Ln).
 */
final class Types
{
    /** @var list<array{A: float, B: float, C: float}> */
    public array $L = [];
    /** @var list<array{A: float, B: float, C: float}> */
    public array $B = [];
    /** @var list<array{A: float, B: float, C: float}> */
    public array $R = [];

    /** Planet time scale: t in Julian millennia from J2000 (consistent with VSOP tables). */
    public static function tMillennia(float $jdTt): float
    {
        return ($jdTt - 2451545.0) / 365250.0; // VSOP87 time unit
    }
}
