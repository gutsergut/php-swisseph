<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swisseph\Math;

final class HousesRegiomontanusTest extends TestCase
{
    public function testCuspsAndOpposites(): void
    {
        $jd_ut = 2462502.5; // 2025-01-01 00:00 UT
        $geolat = 52.52; // Berlin
        $geolon = 13.4050;
        $cusps = $ascmc = [];
        $rc = swe_houses($jd_ut, $geolat, $geolon, 'R', $cusps, $ascmc);
        $this->assertSame(0, $rc);
        // Asc and MC
        $this->assertLessThan(2.0, abs(Math::angleDiffDeg($ascmc[0], $cusps[1])));
        $this->assertLessThan(2.0, abs(Math::angleDiffDeg($ascmc[1], $cusps[10])));
        // Opposites ~ 180
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[1], $cusps[7]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[2], $cusps[8]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[3], $cusps[9]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[4], $cusps[10]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[5], $cusps[11]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[6], $cusps[12]))-180.0));
    }

    public function testHousePosR(): void
    {
        $jd_ut = 2462502.5;
        $geolat = 34.0522; // LA
        $geolon = -118.2437;
        $armc_deg = Math::radToDeg(\Swisseph\Houses::armcFromSidereal($jd_ut, $geolon));
        $eps_deg = Math::radToDeg(\Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_ut + \swe_deltat($jd_ut)/86400.0));
        $serr = '';
        $cusps=$ascmc=[]; swe_houses($jd_ut, $geolat, $geolon, 'R', $cusps, $ascmc);
        $obj = Math::normAngleDeg(($ascmc[0] + $ascmc[1]) / 2.0);
        $pos = swe_house_pos($armc_deg, $geolat, $eps_deg, 'R', [$obj], $serr);
        $this->assertGreaterThan(1.0, $pos, (string)$serr);
        $this->assertLessThan(10.0, $pos);
    }
}
