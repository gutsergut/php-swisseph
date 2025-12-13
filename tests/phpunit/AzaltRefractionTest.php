<?php

use PHPUnit\Framework\TestCase;

final class AzaltRefractionTest extends TestCase
{
    public function testEquatorialHorizontalRoundtrip(): void
    {
        $jd_ut = \Swisseph\Julian::toJulianDay(2025, 10, 26, 0.0, \Swisseph\Constants::SE_GREG_CAL);
        $geopos = [37.6173, 55.7558, 200.0]; // Moscow approx
        $atpress = 1013.25;
        $attemp = 15.0;

        // Pick some RA/Dec (e.g., 40° RA = 2h40m, Dec=+20°)
        $xin = [40.0, 20.0];
        $hor = [];
        $err = null;
        $rc = \swe_azalt($jd_ut, \Swisseph\Constants::SE_EQU2HOR, $geopos, $atpress, $attemp, $xin, $hor, $err);
        $this->assertSame(0, $rc, $err ?? '');

        $eq2 = [];
        $err2 = null;
        $rc2 = \swe_azalt_rev($jd_ut, \Swisseph\Constants::SE_HOR2EQU, $geopos, $hor, $eq2, $err2);
        $this->assertSame(0, $rc2, $err2 ?? '');

        // RA wraps 0..360, compare modulo 360 with small tolerance
        $this->assertEqualsWithDelta($xin[1], $eq2[1], 1e-6, 'Dec roundtrip');
        $dRA = fmod(($eq2[0] - $xin[0] + 540.0), 360.0) - 180.0; // shortest diff
        $this->assertLessThan(1e-6, abs($dRA), 'RA roundtrip');
    }

    public function testRefractionNearHorizon(): void
    {
        // At standard atmosphere, refraction at 0° true alt should be about +0.5667° apparent
        $alt_true = 0.0;
        $app = \swe_refrac($alt_true, 1013.25, 10.0, \Swisseph\Constants::SE_TRUE_TO_APP);
        $this->assertEqualsWithDelta(0.5667, $app, 0.1, 'Refraction magnitude near horizon');

        // Inverse direction should return close to true
        $back = \swe_refrac($app, 1013.25, 10.0, \Swisseph\Constants::SE_APP_TO_TRUE);
        $this->assertEqualsWithDelta($alt_true, $back, 0.15);
    }
}
