<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcValidationTest extends TestCase
{
    public function testInvalidIpl(): void
    {
        // Debug: check Venus speed BEFORE invalid call
        $xxBefore = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxBefore, $serr0);
        echo "\n[testInvalidIpl] BEFORE - Venus speed: {$xxBefore[3]} deg/day\n";

        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, -123, 0, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertIsString($serr);
        $this->assertStringContainsString('ipl=-123', $serr);

        // Debug: check Venus speed AFTER invalid call
        $xxAfter = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxAfter, $serr2);
        echo "[testInvalidIpl] AFTER - Venus speed: {$xxAfter[3]} deg/day\n";
    }
}
