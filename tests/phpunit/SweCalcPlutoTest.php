<?php
use PHPUnit\Framework\TestCase;

final class SweCalcPlutoTest extends TestCase
{
    public function testPlutoDistanceRange(): void
    {
        require_once __DIR__ . '/../../src/functions.php';
        $xx = [];
        $rc = swe_calc(2451545.0, \Swisseph\Constants::SE_PLUTO, 0, $xx, $err);
        $this->assertSame(0, $rc);
        $this->assertGreaterThan(20.0, $xx[2]);
        $this->assertLessThan(60.0, $xx[2]);
    }
}
