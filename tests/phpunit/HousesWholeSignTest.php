<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swisseph\Math;

final class HousesWholeSignTest extends TestCase
{
    public function testCuspsEvery30FromSignStart(): void
    {
        $jd_ut = 2462502.5; // 2025-01-01 00:00 UT
        $geolat = 23.13; // random
        $geolon = 113.26; // random
        $cusps = $ascmc = [];
        $rc = swe_houses($jd_ut, $geolat, $geolon, 'W', $cusps, $ascmc);
        $this->assertSame(0, $rc);
        $asc = $ascmc[0];
        $signStart = floor($asc / 30.0) * 30.0;
        for ($i=1; $i<=12; $i++) {
            $exp = Math::normAngleDeg($signStart + ($i-1)*30.0);
            $this->assertLessThan(1e-6, abs(Math::angleDiffDeg($cusps[$i], $exp)));
        }
    }

    public function testHousePosWholeSign(): void
    {
        $jd_ut = 2462502.5;
        $geolat = 51.5;
        $geolon = -0.13;
        $armc_deg = Math::radToDeg(\Swisseph\Houses::armcFromSidereal($jd_ut, $geolon));
        $eps_deg = Math::radToDeg(\Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_ut + \swe_deltat($jd_ut)/86400.0));
        $serr = '';
        $cusp=$ascmc=[]; swe_houses($jd_ut, $geolat, $geolon, 'W', $cusp, $ascmc);
        // Возьмём объект в середине 1-го Whole Sign дома
        $obj = Math::normAngleDeg(floor($ascmc[0]/30.0)*30.0 + 15.0);
        $pos = swe_house_pos($armc_deg, $geolat, $eps_deg, 'W', [$obj], $serr);
        $this->assertGreaterThan(1.0, $pos);
        $this->assertLessThan(2.0, $pos);
    }
}
