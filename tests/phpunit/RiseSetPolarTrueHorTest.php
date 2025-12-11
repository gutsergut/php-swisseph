<?php

use PHPUnit\Framework\TestCase;

final class RiseSetPolarTrueHorTest extends TestCase
{
    public function testPolarDayNotFound()
    {
        // Tromsø (~69.65N, 18.96E) around summer solstice: Sun does not set
        $jd_ut = \swe_julday(2024, 6, 21, 0.0, 1);
        $geo = [18.96, 69.65, 0.0];
        $press = 1013.25; $temp = 10.0; $h0 = -0.833; // apparent horizon

        $t = null; $err = null;
        $rc = \swe_rise_trans($jd_ut, \Swisseph\Constants::SE_SUN, null, 0, \Swisseph\Constants::SE_CALC_SET, $geo, $press, $temp, $h0, $t, $err);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $rc);
        $this->assertNotNull($err);
        $this->assertStringContainsString('NOT_FOUND', $err);
    }

    public function testTrueHorizonUsesZeroAltitudeByDefault()
    {
        // Use Greenwich; we just verify that wrapper returns a valid time in day and does not crash
        $jd_ut = \swe_julday(2024, 3, 20, 0.0, 1);
        $geo = [0.0, 51.48, 0.0];
        $press = 1013.25; $temp = 15.0; // ignored by true_hor

        $t = null; $err = null;
        $rc = \swe_rise_trans_true_hor($jd_ut, \Swisseph\Constants::SE_SUN, null, 0, \Swisseph\Constants::SE_CALC_RISE, $geo, $press, $temp, 0.0, $t, $err);
        $this->assertSame(0, $rc, $err ?? '');
        $this->assertGreaterThanOrEqual($jd_ut, $t);
        $this->assertLessThan($jd_ut + 1.0, $t);
    }
}
