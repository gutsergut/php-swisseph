<?php

use PHPUnit\Framework\TestCase;

final class CotransAndUtilsTest extends TestCase
{
    public function testCotrans90Deg(): void
    {
    $xpo = [0.0, 1.0, 0.0]; // unit along Y
    $xpn = [];
    $rc = \swe_cotrans($xpo, 90.0, $xpn);
        $this->assertSame(0, $rc);
        $this->assertEqualsWithDelta(0.0, $xpn[0], 1e-12);
        $this->assertEqualsWithDelta(0.0, $xpn[1], 1e-12);
        $this->assertEqualsWithDelta(1.0, $xpn[2], 1e-12);

    $xpo6 = [0.0, 1.0, 0.0, 0.0, 0.0, 1.0];
    $xpn6 = [];
    $rc2 = \swe_cotrans_sp($xpo6, 90.0, $xpn6);
        $this->assertSame(0, $rc2);
        $this->assertEqualsWithDelta(0.0, $xpn6[0], 1e-12);
        $this->assertEqualsWithDelta(0.0, $xpn6[1], 1e-12);
        $this->assertEqualsWithDelta(1.0, $xpn6[2], 1e-12);
        $this->assertEqualsWithDelta(0.0, $xpn6[3], 1e-12);
        $this->assertEqualsWithDelta(-1.0, $xpn6[4], 1e-12);
        $this->assertEqualsWithDelta(0.0, $xpn6[5], 1e-12);
    }

    public function testPlanetNameVersionClose(): void
    {
        $this->assertSame('Sun', \swe_get_planet_name(\Swisseph\Constants::SE_SUN));
        $this->assertSame('Moon', \swe_get_planet_name(\Swisseph\Constants::SE_MOON));
        $ver = \swe_version();
        $this->assertIsString($ver);
        \swe_close();
        $this->assertTrue(true);
    }
}
