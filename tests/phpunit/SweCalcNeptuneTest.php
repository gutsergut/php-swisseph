<?php
use PHPUnit\Framework\TestCase;

final class SweCalcNeptuneTest extends TestCase
{
    public function testNeptuneEclipticDistanceInRange(): void
    {
        $jd_tt = 2451545.0;
        $xx = [];
        $err = null;
        $rc = swe_calc($jd_tt, \Swisseph\Constants::SE_NEPTUNE, 0, $xx, $err);
        $this->assertGreaterThanOrEqual(25.0, $xx[2]);
        $this->assertLessThanOrEqual(35.0, $xx[2]);
    }
}
