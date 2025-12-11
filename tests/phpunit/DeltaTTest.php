<?php

use PHPUnit\Framework\TestCase;
use Swisseph\DeltaT;

final class DeltaTTest extends TestCase
{
    public function testDeltaTModernEra(): void
    {
        // 2000-01-01 UT noon ~ 2451545.0; expected ~64s order of magnitude
        $dt = DeltaT::deltaTSecondsFromJd(2451545.0);
        $this->assertGreaterThan(40.0, $dt);
        $this->assertLessThan(90.0, $dt);
    }

    public function testDeltaT1900(): void
    {
        // Around 1900 expect ~-2..10s range per simple approximation
        $dt = DeltaT::estimateSecondsByYear(1900.0);
        $this->assertGreaterThan(-10.0, $dt);
        $this->assertLessThan(20.0, $dt);
    }
}
