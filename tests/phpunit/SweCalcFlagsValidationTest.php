<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcFlagsValidationTest extends TestCase
{
    public function testConflictingSources(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_JPLEPH | Constants::SEFLG_SWIEPH;
        $ret = swe_calc(2451545.0, Constants::SE_SUN, $flags, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertIsString($serr);
        $this->assertStringContainsString('Conflicting ephemeris source flags', $serr);
    }

    public function testEquatorialAndXyzValid(): void
    {
        // EQUATORIAL + XYZ is valid in C API - returns equatorial cartesian coordinates
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ;
        $ret = swe_calc(2451545.0, Constants::SE_MARS, $flags, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
    }
}
