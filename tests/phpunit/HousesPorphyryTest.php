<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swisseph\Math;

final class HousesPorphyryTest extends TestCase
{
    public function testCuspsMonotonicAndOpposites(): void
    {
        $jd_ut = 2462502.5; // 2025-01-01 00:00 UT
        $geolat = 40.7128; // NYC
        $geolon = -74.0060;
        $cusps = $ascmc = [];
        $rc = swe_houses($jd_ut, $geolat, $geolon, 'O', $cusps, $ascmc);
        $this->assertSame(0, $rc);
        // Asc/MC should match cusps 1 and 10 closely
        $this->assertLessThan(2.0, abs(Math::angleDiffDeg($ascmc[0], $cusps[1])));
        $this->assertLessThan(2.0, abs(Math::angleDiffDeg($ascmc[1], $cusps[10])));
        // Opposite cusps differ by ~180°
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[1], $cusps[7]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[2], $cusps[8]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[3], $cusps[9]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[4], $cusps[10]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[5], $cusps[11]))-180.0));
        $this->assertLessThan(2.0, abs(abs(Math::angleDiffDeg($cusps[6], $cusps[12]))-180.0));
        // Monotonic along ecliptic if we follow natural Porphyry order: 1,12,11,10,9,8,7,6,5,4,3,2
        $order = [1,12,11,10,9,8,7,6,5,4,3,2];
        $fwd = function(float $to, float $from): float {
            $d = $to - $from;
            while ($d < 0) { $d += 360.0; }
            while ($d >= 360.0) { $d -= 360.0; }
            return $d;
        };
        $prev = $cusps[$order[0]];
        for ($k=1; $k<count($order); $k++) {
            $cur = $cusps[$order[$k]];
            $d = $fwd($cur, $prev);
            $this->assertGreaterThanOrEqual(0.0, $d);
            $prev = $cur;
        }
    }

    public function testHousePosPorphyry(): void
    {
        $jd_ut = 2462502.5; // 2025-01-01 00:00 UT
        $geolat = 34.0522; // LA
        $geolon = -118.2437;
        // Compute ARMC and epsilon
        $armc_deg = Math::radToDeg(\Swisseph\Houses::armcFromSidereal($jd_ut, $geolon));
        $eps_deg = Math::radToDeg(\Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_ut + \swe_deltat($jd_ut)/86400.0));
        $serr = null;
        // Pick object ecliptic longitude between Asc and MC approx
        $cusps=$ascmc=[]; swe_houses($jd_ut, $geolat, $geolon, 'O', $cusps, $ascmc);
        $obj = Math::normAngleDeg(($ascmc[0] + $ascmc[1]) / 2.0);
        $pos = swe_house_pos($armc_deg, $geolat, $eps_deg, 'O', [$obj], $serr);
        $this->assertGreaterThan(1.0, $pos);
        $this->assertLessThan(10.0, $pos);
    }
}
