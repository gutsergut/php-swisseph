<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swisseph\Math;

final class HousesKochTest extends TestCase
{
    public function testCuspsAndAscmcConsistency(): void
    {
        $jd_ut = 2462502.5; // 2025-01-01 00:00 UT
        $geolat = 48.8566; // Paris
        $geolon = 2.3522;  // Paris
    $cusps = $ascmc = [];
    $cuspSpeed = $ascmcSpeed = [];
    $serr = '';
    $rc = swe_houses_ex2($jd_ut, 0, $geolat, $geolon, 'K', $cusps, $ascmc, $cuspSpeed, $ascmcSpeed, $serr);
    $this->assertSame(0, $rc, (string)$serr);
    // Asc ~ cusp 1, MC ~ cusp 10
    $asc = $ascmc[0];
    $mc  = $ascmc[1];
    $this->assertLessThan(2.0, abs(Math::angleDiffDeg($asc, $cusps[1])));
    $this->assertLessThan(2.0, abs(Math::angleDiffDeg($mc,  $cusps[10])));
    // And opposite cusps 7/4 are ~180° from 1/10
    $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[7], $cusps[1]))-180.0));
    $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[4], $cusps[10]))-180.0));
        // Opposite cusps differ by ~180°
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[1], $cusps[7]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[2], $cusps[8]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[3], $cusps[9]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[4], $cusps[10]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[5], $cusps[11]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[6], $cusps[12]))-180.0));
    }

    public function testHousePosition(): void
    {
        $jd_ut = 2462502.5; // 2025-01-01 00:00 UT
        $geolat = 34.0522; // Los Angeles
        $geolon = -118.2437;
    $xpin = [100.0, 0.0, 0.0]; // object at 100° ecliptic long
    $serr = '';
    // Compute ARMC and mean obliquity for this jd/longitude
    $armc_rad = \Swisseph\Houses::armcFromSidereal($jd_ut, $geolon);
    $armc_deg = \Swisseph\Math::radToDeg($armc_rad);
    $eps_rad = \Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_ut + \swe_deltat($jd_ut) / 86400.0);
    $eps_deg = \Swisseph\Math::radToDeg($eps_rad);
    $pos = swe_house_pos($armc_deg, $geolat, $eps_deg, 'K', $xpin, $serr);
    $this->assertGreaterThan(0.0, $pos, (string)$serr);
        $this->assertLessThan(13.0, $pos);
    }

    public function testHighLatitudeGuard(): void
    {
        $jd_ut = 2462502.5;
        $geolat = 70.0; // beyond Koch definition
        $geolon = 19.0;
    $cusps = $ascmc = [];
    $cuspSpeed = $ascmcSpeed = [];
    $serr = '';
    $rc = swe_houses_ex2($jd_ut, 0, $geolat, $geolon, 'K', $cusps, $ascmc, $cuspSpeed, $ascmcSpeed, $serr);
    $this->assertSame(\Swisseph\Constants::SE_ERR, $rc);
        $this->assertNotSame('', $serr);
    }
}
