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
        // Expected ≈ 23.439291° near J2000 (Meeus)
        $this->assertEqualsWithDelta(23.439291, $deg, 5e-6);
    }
}
