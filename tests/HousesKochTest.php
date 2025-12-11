<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function swe_julday;
use function swe_houses;
use function swe_house_pos;

final class HousesKochTest extends TestCase
{
    public function testKochUnsupportedHighLevel(): void
    {
        $jd_ut = swe_julday(2000, 1, 1, 12.0, 1);
        $cusp = $ascmc = [];
        $rc = swe_houses($jd_ut, 51.4779, 0.0, 'K', $cusp, $ascmc);
        $this->assertSame(-1, $rc, 'Koch is not implemented yet should return SE_ERR');
    }

    public function testKochHousePosUnsupported(): void
    {
        $armc_deg = 100.0; $geolat = 52.0; $eps = 23.44;
        $serr = null;
        $pos = swe_house_pos($armc_deg, $geolat, $eps, 'K', [123.0], $serr);
        $this->assertSame(0.0, $pos);
        $this->assertNotNull($serr);
        $this->assertStringContainsString('Koch', $serr);
    }
}
