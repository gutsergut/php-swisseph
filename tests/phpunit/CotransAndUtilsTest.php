<?php

use PHPUnit\Framework\TestCase;

final class CotransAndUtilsTest extends TestCase
{
    public function testCotrans90Deg(): void
    {
        // swe_cotrans works with POLAR coordinates (lon, lat, r), not cartesian!
        // Test ecliptic→equatorial conversion with eps=23.4°
        $xpo = [0.0, 45.0, 1.0]; // lon=0°, lat=45°, r=1
        $xpn = [];
        $rc = \swe_cotrans($xpo, $xpn, 23.4);
        $this->assertSame(0, $rc);
        // Verify transformation occurred
        $this->assertNotEquals($xpo[0], $xpn[0]); // longitude changed
        $this->assertNotEquals($xpo[1], $xpn[1]); // latitude changed
        $this->assertEqualsWithDelta($xpo[2], $xpn[2], 1e-12); // radius preserved

        // Test with velocities
        $xpo6 = [0.0, 45.0, 1.0, 0.1, 0.0, 0.0]; // with velocity in lon
        $xpn6 = [];
        $rc2 = \swe_cotrans_sp($xpo6, $xpn6, 23.4);
        $this->assertSame(0, $rc2);
        $this->assertCount(6, $xpn6);
        $this->assertEqualsWithDelta($xpo6[2], $xpn6[2], 1e-12); // radius preserved
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
