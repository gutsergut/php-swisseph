<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcInterfaceTest extends TestCase
{
    public function testSweCalcInterfaceShape(): void
    {
        $xx = [];
        $serr = null;
        // Use an invalid planet id to stay on error path
        $ret = swe_calc(2451545.0, 99, Constants::SEFLG_SPEED, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertIsArray($xx);
        $this->assertCount(6, $xx);
        $this->assertIsString($serr);
    }

    public function testSweCalcUtInterfaceShape(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc_ut(2451545.0, 99, Constants::SEFLG_SPEED | Constants::SEFLG_RADIANS, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertIsArray($xx);
        $this->assertCount(6, $xx);
        $this->assertIsString($serr);
    }
}
