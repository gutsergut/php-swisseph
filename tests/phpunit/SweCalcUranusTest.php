<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcUranusTest extends TestCase
{
    public function testUranusDefaultSuccess(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_URANUS, 0, $xx, $serr);
        $this->assertSame(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $this->assertGreaterThanOrEqual(0.0, $xx[0]);
        $this->assertLessThan(360.0, $xx[0]);
        $this->assertLessThanOrEqual(10.0, abs($xx[1]));
        $this->assertGreaterThan(16.0, $xx[2]);
        $this->assertLessThan(22.5, $xx[2]);
    }

    public function testUranusEquatorialRadians(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_RADIANS | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc(2451545.0, Constants::SE_URANUS, $flags, $xx, $serr);
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

    public function testUranusSpeed(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_URANUS, Constants::SEFLG_SPEED, $xx, $serr);
        $this->assertSame(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $this->assertGreaterThan(0.001, abs($xx[3]));
        $this->assertLessThan(0.1, abs($xx[3]));
        $this->assertGreaterThan(0.0, abs($xx[5]));
    }
}
