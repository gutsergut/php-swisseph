<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Obliquity;
use Swisseph\Math;

final class ObliquityTest extends TestCase
{
    public function testJ2000MeanObliquity(): void
    {
        $eps = Obliquity::meanObliquityRadFromJdTT(2451545.0);
        $deg = Math::radToDeg($eps);
        // Expected ≈ 23.439279° for Vondrak 2011 model (default since C Swiss Ephemeris)
        // Reference: swetest64.exe -b1.1.2000 -p0 -f... returns oec2000.eps = 23.439279444166669
        $this->assertEqualsWithDelta(23.439279444166669, $deg, 1e-12);
    }
}
