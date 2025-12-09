<?php

namespace Swisseph\Domain\Vsop87;

class Term
{
    public float $A;
    public float $B;
    public float $C;

    public function __construct(float $A, float $B, float $C)
    {
        $this->A = $A;
        $this->B = $B;
        $this->C = $C;
    }
}
