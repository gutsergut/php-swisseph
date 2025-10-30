<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swisseph\Math;

final class HousesCampanusTest extends TestCase
{
    public function testCuspsOppositesAndAscMc(): void
    {
        $jd_ut = 2462502.5; // 2025-01-01 00:00 UT
        $geolat = 51.4779; // Greenwich
        $geolon = 0.0;
        $cusps = $ascmc = [];
        $rc = swe_houses($jd_ut, $geolat, $geolon, 'C', $cusps, $ascmc);
        $this->assertSame(0, $rc);
        $this->assertLessThan(2.0, abs(Math::angleDiffDeg($ascmc[0], $cusps[1])));
        $this->assertLessThan(2.0, abs(Math::angleDiffDeg($ascmc[1], $cusps[10])));
        // Opposites ~ 180°
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[1], $cusps[7]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[4], $cusps[10]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[2], $cusps[8]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[3], $cusps[9]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[5], $cusps[11]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[6], $cusps[12]))-180.0));
    }

    public function testHousePosCampanus(): void
    {
        $jd_ut = 2462502.5;
        $geolat = 34.0522; // LA
        $geolon = -118.2437;
        // ARMC and eps
        $armc_deg = Math::radToDeg(\Swisseph\Houses::armcFromSidereal($jd_ut, $geolon));
        $eps_deg = Math::radToDeg(\Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_ut + \swe_deltat($jd_ut)/86400.0));
        $serr = '';
        // Choose object near midpoint Asc-MC (from 'C')
        $cusps=$ascmc=[]; swe_houses($jd_ut, $geolat, $geolon, 'C', $cusps, $ascmc);
        $obj = Math::normAngleDeg(($ascmc[0] + $ascmc[1]) / 2.0);
        $pos = swe_house_pos($armc_deg, $geolat, $eps_deg, 'C', [$obj], $serr);
        $this->assertGreaterThan(1.0, $pos, (string)$serr);
        $this->assertLessThan(10.0, $pos);
    }
}
