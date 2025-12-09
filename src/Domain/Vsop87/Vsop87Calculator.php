<?php

namespace Swisseph\Domain\Vsop87;

use Swisseph\Math; // assume Math has angle normalization helpers already

/**
 * VSOP87 evaluation (no truncation, no simplification).
 * Returns heliocentric ecliptic longitude (deg), latitude (deg), radius (AU).
 */
class Vsop87Calculator
{
    public function compute(VsopPlanetModel $model, float $jdTt): array
    {
        $t = Types::tMillennia($jdTt);
        $L = $model->L->evaluate($t);
        $B = $model->B->evaluate($t);
        $R = $model->R->evaluate($t);
        // Original VSOP coefficients produce angles in radians.
    $Ldeg = Math::normAngleDeg(Math::radToDeg($L));
    $Bdeg = Math::radToDeg($B); // latitude can be negative; no wrap
        return [$Ldeg, $Bdeg, $R];
    }
}
