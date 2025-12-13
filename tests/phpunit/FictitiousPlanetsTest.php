<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Swe\Planets\FictitiousPlanets;
use Swisseph\SwephFile\SwedState;

/**
 * Tests for Uranian/Fictitious planets support
 *
 * Reference values from swetest64.exe:
 * cmd /c ""swetest64.exe" -b1.1.2020 -ut12:00:00 -pJKLMNOPQ -fPlbsR -head -eswe -edir..."
 */
class FictitiousPlanetsTest extends TestCase
{
    private static bool $pathSet = false;

    protected function setUp(): void
    {
        if (!self::$pathSet) {
            $ephePath = realpath(__DIR__ . '/../../eph/ephe');
            if ($ephePath !== false) {
                swe_set_ephe_path($ephePath);
                self::$pathSet = true;
            }
        }
    }

    /**
     * Test data for Uranian planets
     * Reference: swetest64.exe -b1.1.2020 -ut12:00:00 -pJKLMNOPQ -fPlbsR -head -eswe
     *
     * Format: [ipl, name, expected_lon, expected_lat, expected_speed, expected_dist]
     */
    public static function uranianPlanetsProvider(): array
    {
        return [
            'Cupido'   => [Constants::SE_CUPIDO,   'Cupido',   271.1825036,  0.6725756,  0.0273932, 41.808028742],
            'Hades'    => [Constants::SE_HADES,    'Hades',     98.7386306, -0.9487789, -0.0173279, 49.789460312],
            'Zeus'     => [Constants::SE_ZEUS,     'Zeus',     201.4412605, -0.0066473,  0.0054560, 59.405222671],
            'Kronos'   => [Constants::SE_KRONOS,   'Kronos',   102.0454189,  0.0152448, -0.0137408, 63.898958264],
            'Apollon'  => [Constants::SE_APOLLON,  'Apollon',  213.7486259, -0.0095290,  0.0073579, 70.681443799],
            'Admetos'  => [Constants::SE_ADMETOS,  'Admetos',   60.8997749,  0.0144730, -0.0090495, 72.867674087],
            'Vulkanus' => [Constants::SE_VULKANUS, 'Vulkanus', 121.4528557,  0.0128571, -0.0107405, 76.336431325],
            'Poseidon' => [Constants::SE_POSEIDON, 'Poseidon', 224.1896045, -0.0116272,  0.0079754, 84.210173144],
        ];
    }

    /**
     * Test that isFictitious correctly identifies fictitious planets
     */
    #[DataProvider('uranianPlanetsProvider')]
    public function testIsFictitious(int $ipl, string $name): void
    {
        $this->assertTrue(
            FictitiousPlanets::isFictitious($ipl),
            "SE_$name ($ipl) should be identified as fictitious"
        );
    }

    /**
     * Test that isFictitious returns false for regular planets
     */
    public function testIsFictitiousReturnsFalseForRegularPlanets(): void
    {
        $this->assertFalse(FictitiousPlanets::isFictitious(Constants::SE_SUN));
        $this->assertFalse(FictitiousPlanets::isFictitious(Constants::SE_MOON));
        $this->assertFalse(FictitiousPlanets::isFictitious(Constants::SE_MARS));
        $this->assertFalse(FictitiousPlanets::isFictitious(Constants::SE_JUPITER));
        $this->assertFalse(FictitiousPlanets::isFictitious(Constants::SE_PLUTO));
    }

    /**
     * Test getName returns correct names
     */
    #[DataProvider('uranianPlanetsProvider')]
    public function testGetName(int $ipl, string $expectedName): void
    {
        $this->assertEquals(
            $expectedName,
            FictitiousPlanets::getName($ipl),
            "getName($ipl) should return '$expectedName'"
        );
    }

    /**
     * Test geocentric positions match swetest64 reference values
     * Tolerance: 0.02° for longitude, 0.05° for latitude
     */
    #[DataProvider('uranianPlanetsProvider')]
    public function testGeocentricPositions(
        int $ipl,
        string $name,
        float $expectedLon,
        float $expectedLat,
        float $expectedSpeed,
        float $expectedDist
    ): void {
        // JD for 2020-01-01 12:00:00 UT
        $jd_ut = 2458850.0;
        $deltaT = 69.184 / 86400.0;  // approx delta-T for 2020
        $jd_tt = $jd_ut + $deltaT;

        $xx = [];
        $serr = null;
        $ret = PlanetsFunctions::calc($jd_tt, $ipl, 0, $xx, $serr);

        $this->assertGreaterThanOrEqual(
            0,
            $ret,
            "swe_calc for $name failed: $serr"
        );

        // Longitude tolerance: 0.02°
        $this->assertEqualsWithDelta(
            $expectedLon,
            $xx[0],
            0.02,
            "$name longitude differs from swetest64 reference"
        );

        // Latitude tolerance: 0.05° (slightly larger due to small absolute values)
        $this->assertEqualsWithDelta(
            $expectedLat,
            $xx[1],
            0.05,
            "$name latitude differs from swetest64 reference"
        );

        // Distance tolerance: 0.1 AU (for objects at 40-85 AU, this is ~0.1-0.2%)
        $this->assertEqualsWithDelta(
            $expectedDist,
            $xx[2],
            0.1,
            "$name distance differs from swetest64 reference"
        );
    }

    /**
     * Test heliocentric flag returns heliocentric positions
     */
    public function testHeliocentricFlag(): void
    {
        $jd_tt = 2458850.0 + 69.184 / 86400.0;

        $xxGeo = [];
        $xxHel = [];
        $serr = null;

        PlanetsFunctions::calc($jd_tt, Constants::SE_CUPIDO, 0, $xxGeo, $serr);
        PlanetsFunctions::calc($jd_tt, Constants::SE_CUPIDO, Constants::SEFLG_HELCTR, $xxHel, $serr);

        // Heliocentric distance should be different from geocentric
        // (geocentric adds parallax effect)
        $this->assertNotEquals(
            $xxGeo[2],
            $xxHel[2],
            "Heliocentric and geocentric distances should differ"
        );
    }

    /**
     * Test that constants are defined correctly
     */
    public function testFictitiousConstants(): void
    {
        $this->assertEquals(40, Constants::SE_FICT_OFFSET);
        $this->assertEquals(40, Constants::SE_CUPIDO);
        $this->assertEquals(41, Constants::SE_HADES);
        $this->assertEquals(42, Constants::SE_ZEUS);
        $this->assertEquals(43, Constants::SE_KRONOS);
        $this->assertEquals(44, Constants::SE_APOLLON);
        $this->assertEquals(45, Constants::SE_ADMETOS);
        $this->assertEquals(46, Constants::SE_VULKANUS);
        $this->assertEquals(47, Constants::SE_POSEIDON);
        $this->assertEquals(48, Constants::SE_ISIS);
        $this->assertEquals(55, Constants::SE_VULCAN);
        $this->assertEquals(56, Constants::SE_WHITE_MOON);
        $this->assertEquals(999, Constants::SE_FICT_MAX);
        $this->assertEquals(15, Constants::SE_NFICT_ELEM);
    }
}
