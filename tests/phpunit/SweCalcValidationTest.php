<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcValidationTest extends TestCase
{
    public function testInvalidIpl(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, -123, 0, $xx, $serr);
        $this->assertSame(Constants::SE_ERR, $ret);
        $this->assertIsString($serr);
        $this->assertStringContainsString('ipl=-123', $serr);
    }
}
