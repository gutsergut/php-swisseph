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

    public function testEquatorialAndXyzMutuallyExclusive(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ;
        $ret = swe_calc(2451545.0, Constants::SE_MARS, $flags, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertIsString($serr);
        $this->assertStringContainsString('mutually exclusive', $serr);
    }
}
