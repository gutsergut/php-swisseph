<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcFlagsShapeTest extends TestCase
{
    public function testDefaultShape(): void
    {
        $xx = [];
        $serr = null;
        // Use invalid IPL to stay on error path
        $ret = swe_calc(2451545.0, 99, 0, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertCount(6, $xx);
    }

    public function testEquatorialShape(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, 99, Constants::SEFLG_EQUATORIAL, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertCount(6, $xx);
    }

    public function testXyzShape(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, 99, Constants::SEFLG_XYZ, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertCount(6, $xx);
    }
}
