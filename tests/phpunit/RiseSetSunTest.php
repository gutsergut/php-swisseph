<?php

use PHPUnit\Framework\TestCase;

final class RiseSetSunTest extends TestCase
{
    public function testSunRiseSetTransitEquinoxEquator(): void
    {
        // 2000-03-20 00:00 UT (близко к мартовскому равноденствию)
        $jd0 = \Swisseph\Julian::toJulianDay(2000, 3, 20, 0.0, 1);
        $geopos = [0.0, 0.0, 0.0]; // Greenwich, экватор
        $atpress = 1013.25; $attemp = 15.0;

        $t_rise = null; $err = null;
        $rc1 = \swe_rise_trans($jd0, \Swisseph\Constants::SE_SUN, null, 0, \Swisseph\Constants::SE_CALC_RISE, $geopos, $atpress, $attemp, null, $t_rise, $err);
        $this->assertSame(0, $rc1, 'rise rc');
        $this->assertGreaterThanOrEqual($jd0, $t_rise);
        $this->assertLessThan($jd0 + 1.0, $t_rise);

        $t_set = null; $err = null;
        $rc2 = \swe_rise_trans($jd0, \Swisseph\Constants::SE_SUN, null, 0, \Swisseph\Constants::SE_CALC_SET, $geopos, $atpress, $attemp, null, $t_set, $err);
        $this->assertSame(0, $rc2, 'set rc');
        $this->assertGreaterThanOrEqual($jd0, $t_set);
        $this->assertLessThan($jd0 + 1.0, $t_set);

    $t_tr = null; $err = null;
    $rc3 = \swe_rise_trans($jd0, \Swisseph\Constants::SE_SUN, null, 0, \Swisseph\Constants::SE_CALC_MTRANSIT, $geopos, $atpress, $attemp, null, $t_tr, $err);
    $this->assertSame(0, $rc3, 'mtransit rc');
    $this->assertGreaterThanOrEqual($jd0, $t_tr);
    $this->assertLessThan($jd0 + 1.0, $t_tr);

        // Длина дня близка к 12 часам (0.5 суток) на экваторе в равноденствие, допускаем ~±2 часа
        $daylen = $t_set - $t_rise;
        $this->assertEqualsWithDelta(0.5, $daylen, 2.0/24.0);
    }

    public function testSunNoRiseInPolarDay(): void
    {
        // Летнее солнцестояние, 2000-06-21, широта 70N — возможен полярный день (нет восхода/захода)
        $jd0 = \Swisseph\Julian::toJulianDay(2000, 6, 21, 0.0, 1);
        $geopos = [0.0, 70.0, 0.0];
        $atpress = 1013.25; $attemp = 5.0;

        $t_rise = null; $err = null;
        $rc = \swe_rise_trans($jd0, \Swisseph\Constants::SE_SUN, null, 0, \Swisseph\Constants::SE_CALC_RISE, $geopos, $atpress, $attemp, null, $t_rise, $err);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $rc);
        $this->assertIsString($err);
    }
}
