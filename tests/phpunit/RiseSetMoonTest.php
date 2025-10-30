<?php

use PHPUnit\Framework\TestCase;

final class RiseSetMoonTest extends TestCase
{
    public function testMoonRiseSetMidLat(): void
    {
        // Дата произвольная: 2000-01-15 00:00 UT; широта 52N (Москва)
        $jd0 = \Swisseph\Julian::toJulianDay(2000, 1, 15, 0.0, 1);
        $geopos = [37.6173, 55.7558, 200.0];
        $atpress = 1013.25; $attemp = -5.0;

        $t_rise = null; $err = null;
        $rc1 = \swe_rise_trans($jd0, \Swisseph\Constants::SE_MOON, null, 0, \Swisseph\Constants::SE_CALC_RISE, $geopos, $atpress, $attemp, null, $t_rise, $err);
        $this->assertContains($rc1, [0, \Swisseph\Constants::SE_ERR]);

        $t_set = null; $err = null;
        $rc2 = \swe_rise_trans($jd0, \Swisseph\Constants::SE_MOON, null, 0, \Swisseph\Constants::SE_CALC_SET, $geopos, $atpress, $attemp, null, $t_set, $err);
        $this->assertContains($rc2, [0, \Swisseph\Constants::SE_ERR]);

        // Хотя бы одно событие обычно происходит в сутки
        $this->assertTrue(($rc1 === 0) || ($rc2 === 0));
    }
}
