<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\Houses;
use Swisseph\Math;

final class SweHousesEx2SpeedsTest extends TestCase
{
    public function testAscmc2AndSpeedsForRegularSystem(): void
    {
        $jd_ut = 2460680.75; // arbitrary
        $geolat = 40.7128;
        $geolon = -74.0060;
        $cusp = $ascmc = $cusp_speed = $ascmc_speed = [];
        $serr = null;
        $rc = HousesFunctions::housesEx2($jd_ut, 0, $geolat, $geolon, 'O', $cusp, $ascmc, $cusp_speed, $ascmc_speed, $serr);
        $this->assertSame(0, $rc);
        // ARMC degrees should be set at index 2
        $armc_deg = Math::radToDeg(Houses::armcFromSidereal($jd_ut, $geolon));
        $this->assertEqualsWithDelta(Math::normAngleDeg($armc_deg), $ascmc[2], 1e-9);
        // Speeds arrays initialized to zeros with correct lengths
        $this->assertCount(13, $cusp_speed);
        $this->assertCount(10, $ascmc_speed);
        foreach ($cusp_speed as $v) { $this->assertSame(0.0, $v); }
        foreach ($ascmc_speed as $v) { $this->assertSame(0.0, $v); }
    }

    public function testAscmc2AndSpeedsForGauquelin(): void
    {
        $jd_ut = 2460680.5; // arbitrary
        $geolat = 48.8566;
        $geolon = 2.3522;
        $cusp = $ascmc = $cusp_speed = $ascmc_speed = [];
        $serr = null;
        $rc = HousesFunctions::housesEx2($jd_ut, 0, $geolat, $geolon, 'G', $cusp, $ascmc, $cusp_speed, $ascmc_speed, $serr);
        $this->assertSame(0, $rc);
        // ARMC degrees should be set at index 2
        $armc_deg = Math::radToDeg(Houses::armcFromSidereal($jd_ut, $geolon));
        $this->assertEqualsWithDelta(Math::normAngleDeg($armc_deg), $ascmc[2], 1e-9);
        // For 'G' cusp array has 37 entries (1..36 used)
        $this->assertCount(37, $cusp);
        $this->assertCount(37, $cusp_speed);
        $this->assertCount(10, $ascmc_speed);
        foreach ($cusp_speed as $v) { $this->assertSame(0.0, $v); }
        foreach ($ascmc_speed as $v) { $this->assertSame(0.0, $v); }
    }
}
