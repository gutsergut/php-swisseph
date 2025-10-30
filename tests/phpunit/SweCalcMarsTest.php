<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcMarsTest extends TestCase
{
    public function testMarsDefaultSuccess(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_MARS, 0, $xx, $serr);
        $this->assertSame(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // Lon in [0,360), lat within a reasonable band, distance within Mars-Earth plausible range
        $this->assertGreaterThanOrEqual(0.0, $xx[0]);
        $this->assertLessThan(360.0, $xx[0]);
        $this->assertLessThanOrEqual(15.0, abs($xx[1]));
        $this->assertGreaterThan(0.3, $xx[2]); // AU
        $this->assertLessThan(3.0, $xx[2]);
    }

    public function testMarsEquatorialRadians(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_RADIANS | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc(2451545.0, Constants::SE_MARS, $flags, $xx, $serr);
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

    public function testMarsSpeed(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_MARS, Constants::SEFLG_SPEED, $xx, $serr);
        $this->assertSame(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // dLon: в разумном диапазоне для внешней планеты
        $this->assertGreaterThan(0.05, abs($xx[3]));
        $this->assertLessThan(1.5, abs($xx[3]));
        $this->assertGreaterThan(0.0, abs($xx[5])); // radial speed non-zero
    }
}
