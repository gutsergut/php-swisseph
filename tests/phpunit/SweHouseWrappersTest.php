<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SweHouseWrappersTest extends TestCase
{
    public function testHouseName(): void
    {
        $this->assertSame('Equal (Asc-based 30°)', swe_house_name('E'));
        $this->assertSame('axial rotation system/Meridian houses', swe_house_name('X'));
    }

    public function testHousePosEqual(): void
    {
        // Take ARMC/eps/lat and place object exactly at Asc longitude => house ~1
        $armc = 0.0; // deg
        $geolat = 51.5; // deg
        $eps = 23.4392911; // deg
        // Compute Asc from same inputs
        $ascmc = [];$cusp = [];$dummy1=null;$dummy2=null;$err=null;
        $armc_rad = \Swisseph\Math::degToRad(\Swisseph\Math::normAngleDeg($armc));
        $eps_rad = \Swisseph\Math::degToRad($eps);
        [$asc_rad] = \Swisseph\Houses::ascMcFromArmc($armc_rad, \Swisseph\Math::degToRad($geolat), $eps_rad);
        $asc_deg = \Swisseph\Math::normAngleDeg(\Swisseph\Math::radToDeg($asc_rad));
        $serr = null;
        $pos = swe_house_pos($armc, $geolat, $eps, 'E', [$asc_deg], $serr);
        $this->assertGreaterThan(0.9, $pos);
        $this->assertLessThan(1.1, $pos);
    }
}
