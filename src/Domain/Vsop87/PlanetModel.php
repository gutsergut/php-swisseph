<?php

namespace Swisseph\Domain\Vsop87;

class PlanetModel
{
    /** @var Series[] keyed by power */
    public array $L = [];
    /** @var Series[] keyed by power */
    public array $B = [];
    /** @var Series[] keyed by power */
    public array $R = [];
}
