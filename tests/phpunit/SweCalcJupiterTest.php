<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcJupiterTest extends TestCase
{
    public function testJupiterDefaultSuccess(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_JUPITER, 0, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // Lon in [0,360), lat reasonable, distance in 3..7.5 AU range
        $this->assertGreaterThanOrEqual(0.0, $xx[0]);
        $this->assertLessThan(360.0, $xx[0]);
        $this->assertLessThanOrEqual(10.0, abs($xx[1]));
        $this->assertGreaterThan(3.0, $xx[2]);
        $this->assertLessThan(7.5, $xx[2]);
    }

    public function testJupiterEquatorialRadians(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_RADIANS | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc(2451545.0, Constants::SE_JUPITER, $flags, $xx, $serr);
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

    public function testJupiterSpeed(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_JUPITER, Constants::SEFLG_SPEED, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // dLon: reasonable for outer planet
        $this->assertGreaterThan(0.005, abs($xx[3]));
        $this->assertLessThan(0.3, abs($xx[3]));
        $this->assertGreaterThan(0.0, abs($xx[5])); // radial speed non-zero
    }
}
