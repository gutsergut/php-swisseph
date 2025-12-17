<?php

declare(strict_types=1);

namespace SwissephTest;

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

/**
 * Test asteroid nodes/apsides calculation
 * Compares PHP swe_nod_aps() with C swetest64 reference values
 */
class AsteroidNodesTest extends TestCase
{
    protected function setUp(): void
    {
        \swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
    }

    /**
     * Test Ceres (asteroid 1 = 10001) osculating nodes at J2000.0
     * Reference: swetest64 -b01.01.2000 -ut12:00 -ps -xs1 -fNF -head
     * Output: 69.7739889  265.6015206  176.5097370  321.4372328  295.9414890
     */
    public function testCeresOsculatingNodes(): void
    {
        $jd = 2451545.0; // J2000.0
        $ceres = Constants::SE_AST_OFFSET + 1; // 10001
        
        $xnasc = [];
        $xndsc = [];
        $xperi = [];
        $xaphe = [];
        $serr = null;
        
        $ret = \swe_nod_aps_ut(
            $jd,
            $ceres,
            Constants::SEFLG_SWIEPH,
            Constants::SE_NODBIT_OSCU,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );
        
        $this->assertGreaterThanOrEqual(0, $ret, "swe_nod_aps_ut error: $serr");
        
        // Reference values from swetest64
        $refAscNode = 69.7739889;
        $refDescNode = 265.6015206;
        $refPeri = 176.5097370;
        $refAphe = 321.4372328;
        
        // Tolerance: ~30 arcsec (consistent with other osculating node tests)
        $toleranceDeg = 30.0 / 3600.0;
        
        $this->assertEqualsWithDelta($refAscNode, $xnasc[0], $toleranceDeg,
            sprintf("Asc Node: expected %.6f, got %.6f", $refAscNode, $xnasc[0]));
        $this->assertEqualsWithDelta($refDescNode, $xndsc[0], $toleranceDeg,
            sprintf("Desc Node: expected %.6f, got %.6f", $refDescNode, $xndsc[0]));
        $this->assertEqualsWithDelta($refPeri, $xperi[0], $toleranceDeg,
            sprintf("Perihelion: expected %.6f, got %.6f", $refPeri, $xperi[0]));
        $this->assertEqualsWithDelta($refAphe, $xaphe[0], $toleranceDeg,
            sprintf("Aphelion: expected %.6f, got %.6f", $refAphe, $xaphe[0]));
    }

    /**
     * Test Vesta (asteroid 4 = 10004) osculating nodes
     */
    public function testVestaOsculatingNodes(): void
    {
        $jd = 2451545.0; // J2000.0
        $vesta = Constants::SE_AST_OFFSET + 4; // 10004
        
        $xnasc = [];
        $xndsc = [];
        $xperi = [];
        $xaphe = [];
        $serr = null;
        
        $ret = \swe_nod_aps_ut(
            $jd,
            $vesta,
            Constants::SEFLG_SWIEPH,
            Constants::SE_NODBIT_OSCU,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );
        
        $this->assertGreaterThanOrEqual(0, $ret, "swe_nod_aps_ut error: $serr");
        
        // Just verify we get valid coordinates
        $this->assertGreaterThanOrEqual(0, $xnasc[0], "Asc Node longitude should be >= 0");
        $this->assertLessThan(360, $xnasc[0], "Asc Node longitude should be < 360");
        $this->assertGreaterThan(0, $xnasc[2], "Asc Node distance should be positive");
    }

    /**
     * Test Eros (asteroid 433 = 10433) osculating nodes
     */
    public function testErosOsculatingNodes(): void
    {
        $jd = 2451545.0; // J2000.0
        $eros = Constants::SE_AST_OFFSET + 433; // 10433
        
        $xnasc = [];
        $xndsc = [];
        $xperi = [];
        $xaphe = [];
        $serr = null;
        
        $ret = \swe_nod_aps_ut(
            $jd,
            $eros,
            Constants::SEFLG_SWIEPH,
            Constants::SE_NODBIT_OSCU,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );
        
        $this->assertGreaterThanOrEqual(0, $ret, "swe_nod_aps_ut error: $serr");
        
        // Just verify we get valid coordinates
        $this->assertGreaterThanOrEqual(0, $xnasc[0], "Asc Node longitude should be >= 0");
        $this->assertLessThan(360, $xnasc[0], "Asc Node longitude should be < 360");
    }
}
