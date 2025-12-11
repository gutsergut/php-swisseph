<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Math;

final class MathTest extends TestCase
{
    public function testDegRadRoundTrip(): void
    {
        $values = [0.0, 123.456, -45.0, 360.0];
        foreach ($values as $deg) {
            $rad = Math::degToRad($deg);
            $deg2 = Math::radToDeg($rad);
            $this->assertEqualsWithDelta($deg, $deg2, 1e-12);
        }
    }

    public function testNormAngleDeg(): void
    {
        $this->assertEqualsWithDelta(350.0, Math::normAngleDeg(-10.0), 1e-12);
        $this->assertEqualsWithDelta(0.0, Math::normAngleDeg(360.0), 1e-12);
        $this->assertEqualsWithDelta(10.0, Math::normAngleDeg(370.0), 1e-12);
    }

    public function testNormAngleRad(): void
    {
        $twoPi = Math::TWO_PI;
        $this->assertEqualsWithDelta($twoPi - 0.1, Math::normAngleRad(-0.1), 1e-12);
        $this->assertEqualsWithDelta(0.0, Math::normAngleRad($twoPi), 1e-12);
    }
}
