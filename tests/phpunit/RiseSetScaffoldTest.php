<?php

use PHPUnit\Framework\TestCase;

final class RiseSetScaffoldTest extends TestCase
{
    public function testScaffoldExists()
    {
        $this->assertTrue(function_exists('swe_rise_trans'));
        $this->assertTrue(function_exists('swe_rise_trans_true_hor'));
    }

    public function testSunRiseSetTransitBasic()
    {
        // Date near equinox for predictable behavior: 2024-03-20 00:00 UT
        $jd_ut = swe_julday(2024, 3, 20, 0.0, 1);
        $geo = [0.0, 51.48, 0.0]; // Greenwich lat ≈ 51.48°N
        $press = 1013.25; $temp = 15.0; $h0 = -0.833;

        // Rise
        $tRise = null; $err=null;
        $rc = swe_rise_trans($jd_ut, 0, null, 0, \Swisseph\Constants::SE_CALC_RISE, $geo, $press, $temp, $h0, $tRise, $err);
        $this->assertSame(0, $rc, $err ?? '');
        $this->assertGreaterThanOrEqual($jd_ut, $tRise);
        $this->assertLessThan($jd_ut + 1.0, $tRise);

        // Set
        $tSet = null; $err=null;
        $rc = swe_rise_trans($jd_ut, 0, null, 0, \Swisseph\Constants::SE_CALC_SET, $geo, $press, $temp, $h0, $tSet, $err);
        $this->assertSame(0, $rc, $err ?? '');
        $this->assertGreaterThanOrEqual($jd_ut, $tSet);
        $this->assertLessThan($jd_ut + 1.0, $tSet);

        // Meridian transit (upper culmination)
        $tTr = null; $err=null;
        $rc = swe_rise_trans($jd_ut, 0, null, 0, \Swisseph\Constants::SE_CALC_MTRANSIT, $geo, $press, $temp, $h0, $tTr, $err);
        $this->assertSame(0, $rc, $err ?? '');
        $this->assertGreaterThanOrEqual($jd_ut, $tTr);
        $this->assertLessThan($jd_ut + 1.0, $tTr);
    }

    public function testMoonRiseSetBasic()
    {
        // 2024-03-20 00:00 UT, Greenwich
        $jd_ut = \swe_julday(2024, 3, 20, 0.0, 1);
        $geo = [0.0, 51.48, 0.0];
        $press = 1013.25; $temp = 15.0; $h0 = null; // default for Moon

        $tRise = null; $err = null;
        $rc = \swe_rise_trans($jd_ut, \Swisseph\Constants::SE_MOON, null, 0, \Swisseph\Constants::SE_CALC_RISE, $geo, $press, $temp, $h0, $tRise, $err);
        $this->assertSame(0, $rc, $err ?? '');
        $this->assertGreaterThanOrEqual($jd_ut, $tRise);
        $this->assertLessThan($jd_ut + 1.0, $tRise);

        $tSet = null; $err = null;
        $rc = \swe_rise_trans($jd_ut, \Swisseph\Constants::SE_MOON, null, 0, \Swisseph\Constants::SE_CALC_SET, $geo, $press, $temp, $h0, $tSet, $err);
        $this->assertSame(0, $rc, $err ?? '');
        $this->assertGreaterThanOrEqual($jd_ut, $tSet);
        $this->assertLessThan($jd_ut + 1.0, $tSet);
    }

    public function testMoonRiseDiffersApparentVsTrueHor()
    {
        $jd_ut = \swe_julday(2024, 3, 20, 0.0, 1);
        $geo = [0.0, 51.48, 0.0];
        $press = 1013.25; $temp = 15.0;

        $tApp = null; $err=null;
        $rc = \swe_rise_trans($jd_ut, \Swisseph\Constants::SE_MOON, null, 0, \Swisseph\Constants::SE_CALC_RISE, $geo, $press, $temp, null, $tApp, $err);
        $this->assertSame(0, $rc, $err ?? '');

        $tTrue = null; $err=null;
        $rc = \swe_rise_trans_true_hor($jd_ut, \Swisseph\Constants::SE_MOON, null, 0, \Swisseph\Constants::SE_CALC_RISE, $geo, $press, $temp, 0.0, $tTrue, $err);
        $this->assertSame(0, $rc, $err ?? '');

        // Времена должны отличаться (хотя бы на секунды/минуты)
        $this->assertNotEquals($tApp, $tTrue);
    }

    public function testMoonLowerTransitInDay()
    {
        $jd_ut = \swe_julday(2024, 3, 20, 0.0, 1);
        $geo = [0.0, 51.48, 0.0];
        $press = 1013.25; $temp = 15.0; $h0 = null;

        $tUpper = null; $err = null;
        $rc = \swe_rise_trans($jd_ut, \Swisseph\Constants::SE_MOON, null, 0, \Swisseph\Constants::SE_CALC_MTRANSIT, $geo, $press, $temp, $h0, $tUpper, $err);
        $this->assertSame(0, $rc, $err ?? '');

        $tLower = null; $err = null;
        $rc = \swe_rise_trans($jd_ut, \Swisseph\Constants::SE_MOON, null, 0, \Swisseph\Constants::SE_CALC_ITRANSIT, $geo, $press, $temp, $h0, $tLower, $err);
        $this->assertSame(0, $rc, $err ?? '');
        $this->assertGreaterThanOrEqual($jd_ut, $tLower);
        $this->assertLessThan($jd_ut + 1.0, $tLower);
        $this->assertNotEquals($tUpper, $tLower);
    }
}
