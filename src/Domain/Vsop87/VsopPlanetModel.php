<?php

namespace Swisseph\Domain\Vsop87;

/** Complete VSOP model for a planet: L,B,R series. */
class VsopPlanetModel
{
    public VsopSeries $L;
    public VsopSeries $B;
    public VsopSeries $R;

    public function __construct()
    {
        $this->L = new VsopSeries();
        $this->B = new VsopSeries();
        $this->R = new VsopSeries();
    }
}
