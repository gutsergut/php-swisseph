<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Math utility functions (swe_degnorm, swe_radnorm, etc.)
 */
final class MathUtilsTest extends TestCase
{
    public function testDegnorm(): void
    {
        $this->assertEqualsWithDelta(0.0, swe_degnorm(0.0), 1e-10);
        $this->assertEqualsWithDelta(180.0, swe_degnorm(180.0), 1e-10);
        $this->assertEqualsWithDelta(359.0, swe_degnorm(359.0), 1e-10);
        $this->assertEqualsWithDelta(0.0, swe_degnorm(360.0), 1e-10);
        $this->assertEqualsWithDelta(1.0, swe_degnorm(361.0), 1e-10);
        $this->assertEqualsWithDelta(359.0, swe_degnorm(-1.0), 1e-10);
        $this->assertEqualsWithDelta(270.0, swe_degnorm(-90.0), 1e-10);
        $this->assertEqualsWithDelta(45.0, swe_degnorm(405.0), 1e-10);
    }

    public function testRadnorm(): void
    {
        $this->assertEqualsWithDelta(0.0, swe_radnorm(0.0), 1e-10);
        $this->assertEqualsWithDelta(M_PI, swe_radnorm(M_PI), 1e-10);
        $this->assertEqualsWithDelta(0.0, swe_radnorm(2 * M_PI), 1e-10);
        $this->assertEqualsWithDelta(M_PI / 2, swe_radnorm(M_PI / 2), 1e-10);
        $this->assertEqualsWithDelta(3 * M_PI / 2, swe_radnorm(-M_PI / 2), 1e-10);
        $this->assertEqualsWithDelta(M_PI / 4, swe_radnorm(9 * M_PI / 4), 1e-10);
    }

    public function testDegMidpoint(): void
    {
        // Simple cases
        $this->assertEqualsWithDelta(45.0, swe_deg_midp(90.0, 0.0), 1e-10);
        $this->assertEqualsWithDelta(90.0, swe_deg_midp(180.0, 0.0), 1e-10);

        // Across 0° meridian (shortest arc)
        $this->assertEqualsWithDelta(0.0, swe_deg_midp(10.0, 350.0), 1e-10);
        $this->assertEqualsWithDelta(180.0, swe_deg_midp(170.0, 190.0), 1e-10);

        // Same angle
        $this->assertEqualsWithDelta(45.0, swe_deg_midp(45.0, 45.0), 1e-10);
    }

    public function testRadMidpoint(): void
    {
        // Simple cases
        $this->assertEqualsWithDelta(M_PI / 4, swe_rad_midp(M_PI / 2, 0.0), 1e-10);
        $this->assertEqualsWithDelta(M_PI / 2, swe_rad_midp(M_PI, 0.0), 1e-10);

        // Across 0 meridian
        $this->assertEqualsWithDelta(0.0, swe_rad_midp(0.1, 2 * M_PI - 0.1), 1e-10);

        // Same angle
        $this->assertEqualsWithDelta(M_PI / 4, swe_rad_midp(M_PI / 4, M_PI / 4), 1e-10);
    }

    public function testSplitDegSimple(): void
    {
        // Test 45.5° with no rounding
        $d = $m = $s = $sgn = 0;
        $f = 0.0;
        swe_split_deg(45.5, 0, $d, $m, $s, $f, $sgn);
        $this->assertSame(45, $d);
        $this->assertSame(30, $m);
        $this->assertSame(0, $s);
        $this->assertEqualsWithDelta(0.0, $f, 1e-10);
        $this->assertSame(1, $sgn);

        // Test -30.25°
        $d = $m = $s = $sgn = 0;
        $f = 0.0;
        swe_split_deg(-30.25, 0, $d, $m, $s, $f, $sgn);
        $this->assertSame(30, $d);
        $this->assertSame(15, $m);
        $this->assertSame(0, $s);
        $this->assertEqualsWithDelta(0.0, $f, 1e-10);
        $this->assertSame(-1, $sgn);

        // Test 123.456789°
        $d = $m = $s = $sgn = 0;
        $f = 0.0;
        swe_split_deg(123.456789, 0, $d, $m, $s, $f, $sgn);
        $this->assertSame(123, $d);
        $this->assertSame(27, $m);
        $this->assertSame(24, $s);
        $this->assertEqualsWithDelta(0.4404, $f, 0.001); // ~24.4404"
        $this->assertSame(1, $sgn);
    }

    public function testSplitDegRoundSec(): void
    {
        // Round to seconds
        $d = $m = $s = $sgn = 0;
        $f = 0.0;
        swe_split_deg(123.456789, \Swisseph\Constants::SE_SPLIT_DEG_ROUND_SEC, $d, $m, $s, $f, $sgn);
        $this->assertSame(123, $d);
        $this->assertSame(27, $m);
        $this->assertSame(24, $s); // rounded
        $this->assertEqualsWithDelta(0.0, $f, 1e-10);
        $this->assertSame(1, $sgn);
    }

    public function testSplitDegRoundMin(): void
    {
        // Round to minutes
        $d = $m = $s = $sgn = 0;
        $f = 0.0;
        swe_split_deg(123.456789, \Swisseph\Constants::SE_SPLIT_DEG_ROUND_MIN, $d, $m, $s, $f, $sgn);
        $this->assertSame(123, $d);
        $this->assertSame(27, $m);
        $this->assertSame(0, $s);
        $this->assertEqualsWithDelta(0.0, $f, 1e-10);
        $this->assertSame(1, $sgn);
    }

    public function testSplitDegRoundDeg(): void
    {
        // Round to degrees
        $d = $m = $s = $sgn = 0;
        $f = 0.0;
        swe_split_deg(123.456789, \Swisseph\Constants::SE_SPLIT_DEG_ROUND_DEG, $d, $m, $s, $f, $sgn);
        $this->assertSame(123, $d);
        $this->assertSame(0, $m);
        $this->assertSame(0, $s);
        $this->assertEqualsWithDelta(0.0, $f, 1e-10);
        $this->assertSame(1, $sgn);
    }

    public function testSplitDegZodiacal(): void
    {
        // Zodiacal mode: 123.5° -> 3° Cnc 30'
        // 123.5° = 4*30 + 3.5 = 3°30' in Cancer (4th sign)
        $d = $m = $s = $sgn = 0;
        $f = 0.0;
        swe_split_deg(123.5, \Swisseph\Constants::SE_SPLIT_DEG_ZODIACAL, $d, $m, $s, $f, $sgn);
        $this->assertSame(3, $d);  // 3° within sign
        $this->assertSame(30, $m);
        $this->assertSame(0, $s);
        $this->assertEqualsWithDelta(0.0, $f, 1e-10);
        $this->assertSame(1, $sgn);
    }
}
