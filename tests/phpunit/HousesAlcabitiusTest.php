<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swisseph\Math;

final class HousesAlcabitiusTest extends TestCase
{
    public function testCuspsAndOpposites(): void
    {
        $jd_ut = 2462502.5; // 2025-01-01 00:00 UT
        $geolat = 35.6895; // Tokyo
        $geolon = 139.6917;
        $cusps = $ascmc = [];
        $rc = swe_houses($jd_ut, $geolat, $geolon, 'B', $cusps, $ascmc);
        $this->assertSame(0, $rc);
        $this->assertLessThan(2.0, abs(Math::angleDiffDeg($ascmc[0], $cusps[1])));
        $this->assertLessThan(2.0, abs(Math::angleDiffDeg($ascmc[1], $cusps[10])));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[1], $cusps[7]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[4], $cusps[10]))-180.0));
    }

    public function testHousePosAlcabitius(): void
    {
        $jd_ut = 2462502.5;
        $geolat = 34.0522; // LA
        $geolon = -118.2437;
        $armc_deg = Math::radToDeg(\Swisseph\Houses::armcFromSidereal($jd_ut, $geolon));
        $eps_deg = Math::radToDeg(\Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_ut + \swe_deltat($jd_ut)/86400.0));
        $serr = '';
        $cusps=$ascmc=[]; swe_houses($jd_ut, $geolat, $geolon, 'B', $cusps, $ascmc);
        $obj = Math::normAngleDeg(($ascmc[0] + $ascmc[1]) / 2.0);
        $pos = swe_house_pos($armc_deg, $geolat, $eps_deg, 'B', [$obj], $serr);
        $this->assertGreaterThan(1.0, $pos, (string)$serr);
        $this->assertLessThan(10.0, $pos);
    }
}
