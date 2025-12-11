<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

final class SweCalcSaturnTest extends TestCase
{
    protected function setUp(): void
    {
        // Устанавливаем путь к эфемеридам (относительно корня проекта)
        $ephePath = realpath(__DIR__ . '/../../../с-swisseph/swisseph/ephe');
        if ($ephePath !== false) {
            swe_set_ephe_path($ephePath);
        }
    }

    public function testSaturnDefaultSuccess(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_SATURN, 0, $xx, $serr);
        // swe_calc возвращает iflag при успехе (>=0), SE_ERR (-1) при ошибке
        $this->assertGreaterThanOrEqual(0, $ret, $serr ?? 'swe_calc failed');
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $this->assertGreaterThanOrEqual(0.0, $xx[0]);
        $this->assertLessThan(360.0, $xx[0]);
        $this->assertLessThanOrEqual(10.0, abs($xx[1]));
        $this->assertGreaterThan(7.0, $xx[2]);
        $this->assertLessThan(11.5, $xx[2]);
    }

    public function testSaturnEquatorialRadians(): void
    {
        $xx = [];
        $serr = null;
        $flags = Constants::SEFLG_RADIANS | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc(2451545.0, Constants::SE_SATURN, $flags, $xx, $serr);
        // swe_calc возвращает iflag при успехе (>=0), SE_ERR (-1) при ошибке
        $this->assertGreaterThanOrEqual(0, $ret, $serr ?? 'swe_calc failed');
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $ra = $xx[0];
        $dec = $xx[1];
        $this->assertGreaterThanOrEqual(0.0, $ra);
        $this->assertLessThan(2 * pi(), $ra);
        $this->assertGreaterThanOrEqual(-pi()/2, $dec);
        $this->assertLessThanOrEqual(pi()/2, $dec);
    }

    public function testSaturnSpeed(): void
    {
        $xx = [];
        $serr = null;
        $ret = swe_calc(2451545.0, Constants::SE_SATURN, Constants::SEFLG_SPEED, $xx, $serr);
        // swe_calc возвращает iflag при успехе (>=0), SE_ERR (-1) при ошибке
        $this->assertGreaterThanOrEqual(0, $ret, $serr ?? 'swe_calc failed');
        $this->assertNull($serr);
        $this->assertCount(6, $xx);
        $this->assertGreaterThan(0.002, abs($xx[3]));
        $this->assertLessThan(0.2, abs($xx[3]));
        $this->assertGreaterThan(0.0, abs($xx[5]));
    }
}
