<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcSunTest extends TestCase
{
    public function testSunAtJ2000(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_SUN, 0, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret, 'Sun should be supported');
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // Basic sanity: longitude in [0,360), latitude ~ 0, distance ~ 0.983..1.017 AU
        $lon = $xx[0];
        $lat = $xx[1];
        $dist = $xx[2];
        $this->assertGreaterThanOrEqual(0.0, $lon);
        $this->assertLessThan(360.0, $lon);
        $this->assertLessThan(0.01, abs($lat));
        $this->assertGreaterThan(0.95, $dist);
        $this->assertLessThan(1.1, $dist);
    }

    public function testSunRadiansEquatorial(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_RADIANS | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc(2451545.0, Constants::SE_SUN, $flags, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // RA in [0, 2pi), Dec in [-pi/2, pi/2]
        $ra = $xx[0];
        $dec = $xx[1];
        $this->assertGreaterThanOrEqual(0.0, $ra);
        $this->assertLessThan(2 * pi(), $ra);
        $this->assertGreaterThanOrEqual(-pi()/2, $dec);
        $this->assertLessThanOrEqual(pi()/2, $dec);
    }
}
