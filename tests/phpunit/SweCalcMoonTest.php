<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcMoonTest extends TestCase
{
    public function testMoonDefaultSuccess(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_MOON, 0, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // Lon in [0,360), lat within a reasonable band, distance ~0.00257 AU
        $this->assertGreaterThanOrEqual(0.0, $xx[0]);
        $this->assertLessThan(360.0, $xx[0]);
        $this->assertLessThanOrEqual(10.0, abs($xx[1]));
        $this->assertGreaterThan(0.001, $xx[2]);
        $this->assertLessThan(0.01, $xx[2]);
    }

    public function testMoonEquatorialRadians(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_RADIANS | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc(2451545.0, Constants::SE_MOON, $flags, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $ra = $xx[0];
        $dec = $xx[1];
        $this->assertGreaterThanOrEqual(0.0, $ra);
        $this->assertLessThan(2 * pi(), $ra);
        $this->assertGreaterThanOrEqual(-pi()/2, $dec);
        $this->assertLessThanOrEqual(pi()/2, $dec);
    }

    public function testMoonSpeed(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_MOON, Constants::SEFLG_SPEED, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // dLon ~ 13 deg/day; ensure it's in a wide band
        $this->assertGreaterThan(5.0, abs($xx[3]));
        $this->assertGreaterThan(0.0, abs($xx[5])); // radial speed non-zero
    }
}
