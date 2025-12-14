<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcUranusTest extends TestCase
{
    public function testUranusDefaultSuccess(): void
    {
        // Debug: check Venus speed BEFORE call
        $xxBefore = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxBefore, $serr0);
        echo "\n[testUranusDefaultSuccess] BEFORE - Venus speed: {$xxBefore[3]} deg/day\n";

        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_URANUS, 0, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $this->assertGreaterThanOrEqual(0.0, $xx[0]);
        $this->assertLessThan(360.0, $xx[0]);
        $this->assertLessThanOrEqual(10.0, abs($xx[1]));
        $this->assertGreaterThan(16.0, $xx[2]);
        $this->assertLessThan(22.5, $xx[2]);

        // Debug: check Venus speed AFTER call
        $xxAfter = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxAfter, $serr2);
        echo "[testUranusDefaultSuccess] AFTER - Venus speed: {$xxAfter[3]} deg/day\n";
    }

    public function testUranusEquatorialRadians(): void
    {
        // Debug: check Venus speed BEFORE call
        $xxBefore = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxBefore, $serr0);
        echo "\n[testUranusEquatorialRadians] BEFORE - Venus speed: {$xxBefore[3]} deg/day\n";

        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_RADIANS | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc(2451545.0, Constants::SE_URANUS, $flags, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $ra = $xx[0];
        $dec = $xx[1];
        $this->assertGreaterThanOrEqual(0.0, $ra);
        $this->assertLessThan(2 * pi(), $ra);
        $this->assertGreaterThanOrEqual(-pi()/2, $dec);
        $this->assertLessThanOrEqual(pi()/2, $dec);

        // Debug: check Venus speed AFTER call
        $xxAfter = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxAfter, $serr2);
        echo "[testUranusEquatorialRadians] AFTER - Venus speed: {$xxAfter[3]} deg/day\n";
    }

    public function testUranusSpeed(): void
    {
        // Debug: check Venus speed BEFORE Uranus speed call
        $xxBefore = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxBefore, $serr0);
        echo "\n[testUranusSpeed] BEFORE - Venus speed: {$xxBefore[3]} deg/day\n";

        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_URANUS, Constants::SEFLG_SPEED, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $this->assertGreaterThan(0.001, abs($xx[3]));
        $this->assertLessThan(0.1, abs($xx[3]));
        $this->assertGreaterThan(0.0, abs($xx[5]));

        // Debug: check Venus speed AFTER Uranus speed call
        $xxAfter = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxAfter, $serr2);
        echo "[testUranusSpeed] AFTER - Venus speed: {$xxAfter[3]} deg/day\n";
    }
}
