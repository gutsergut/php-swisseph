<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcVenusTest extends TestCase
{
    public function testVenusDefaultSuccess(): void
    {
        // Debug: check Venus speed BEFORE any call
        $xxBefore = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxBefore, $serr0);
        echo "\n[testVenusDefaultSuccess] BEFORE - Venus speed: {$xxBefore[3]} deg/day\n";

        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_VENUS, 0, $xx, $serr);

        // Debug: check Venus speed IMMEDIATELY after the flags=0 call
        $xxAfter = [];
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xxAfter, $serrA);
        echo "[testVenusDefaultSuccess] AFTER flags=0 call - Venus speed: {$xxAfter[3]} deg/day\n";

        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // Lon in [0,360), lat within a reasonable band, distance within Venus-Earth plausible range
        $this->assertGreaterThanOrEqual(0.0, $xx[0]);
        $this->assertLessThan(360.0, $xx[0]);
        $this->assertLessThanOrEqual(10.0, abs($xx[1]));
        $this->assertGreaterThan(0.2, $xx[2]); // AU
        $this->assertLessThan(1.5, $xx[2]);
    }

    public function testVenusSpeed(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xx, $serr);

        // Debug output
        if (abs($xx[3]) > 3.5) {
            echo "\n\n=== DEBUG Venus Speed ===\n";
            echo "ret=$ret, serr=$serr\n";
            echo "xx = [" . implode(", ", $xx) . "]\n";
            echo "lon={$xx[0]}, lat={$xx[1]}, dist={$xx[2]}\n";
            echo "speed: {$xx[3]}, {$xx[4]}, {$xx[5]}\n";
            echo "=========================\n\n";
        }

        $this->assertGreaterThanOrEqual(0, $ret);
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        // dLon: должна быть в разумном диапазоне для внутренней планеты
        $this->assertGreaterThan(0.2, abs($xx[3]));
        $this->assertLessThan(3.5, abs($xx[3]));
        $this->assertGreaterThan(0.0, abs($xx[5])); // radial speed non-zero
    }
}
