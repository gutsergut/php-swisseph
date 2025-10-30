<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcVenusTest extends TestCase
{
    public function testVenusDefaultSuccess(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_VENUS, 0, $xx, $serr);
        $this->assertSame(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // Lon in [0,360), lat within a reasonable band, distance within Venus-Earth plausible range
        $this->assertGreaterThanOrEqual(0.0, $xx[0]);
        $this->assertLessThan(360.0, $xx[0]);
        $this->assertLessThanOrEqual(10.0, abs($xx[1]));
        $this->assertGreaterThan(0.2, $xx[2]); // AU
        $this->assertLessThan(1.5, $xx[2]);
    }

    public function testVenusEquatorialRadians(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_RADIANS | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc(2451545.0, Constants::SE_VENUS, $flags, $xx, $serr);
        $this->assertSame(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $ra = $xx[0];
        $dec = $xx[1];
        $this->assertGreaterThanOrEqual(0.0, $ra);
        $this->assertLessThan(2 * pi(), $ra);
        $this->assertGreaterThanOrEqual(-pi()/2, $dec);
        $this->assertLessThanOrEqual(pi()/2, $dec);
    }

    public function testVenusSpeed(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xx, $serr);
        $this->assertSame(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // dLon: должна быть в разумном диапазоне для внутренней планеты
        $this->assertGreaterThan(0.2, abs($xx[3]));
        $this->assertLessThan(3.5, abs($xx[3]));
        $this->assertGreaterThan(0.0, abs($xx[5])); // radial speed non-zero
    }
}
