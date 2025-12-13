<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

/**
 * Tests for main belt asteroids: Chiron, Pholus, Ceres, Pallas, Juno, Vesta.
 *
 * Reference values from swetest64.exe for JD=2460000.5 (25 Feb 2023 00:00 UT):
 *   swetest64.exe -b25.02.2023 -pD -fPlbr -head -edir<path>
 *   swetest64.exe -b25.02.2023 -pE -fPlbr -head -edir<path>
 *   etc.
 */
class AsteroidsTest extends TestCase
{
    private const JD = 2460000.5;  // 25 Feb 2023 00:00 UT

    /**
     * Reference values from swetest64.exe
     */
    private const REFERENCE = [
        Constants::SE_CHIRON => ['name' => 'Chiron', 'lon' => 13.64664692, 'lat' => 1.63127125, 'dist' => 19.577859095],
        Constants::SE_PHOLUS => ['name' => 'Pholus', 'lon' => 278.4755978, 'lat' => 9.9411965, 'dist' => 30.149720360],
        Constants::SE_CERES  => ['name' => 'Ceres', 'lon' => 185.3528945, 'lat' => 16.2879155, 'dist' => 1.673828157],
        Constants::SE_PALLAS => ['name' => 'Pallas', 'lon' => 100.7999382, 'lat' => -40.1074536, 'dist' => 1.507360256],
        Constants::SE_JUNO   => ['name' => 'Juno', 'lon' => 21.8129822, 'lat' => -8.1383841, 'dist' => 2.549714074],
        Constants::SE_VESTA  => ['name' => 'Vesta', 'lon' => 7.4579634, 'lat' => -5.3827112, 'dist' => 3.252325689],
    ];

    protected function setUp(): void
    {
        \swe_set_ephe_path(__DIR__ . '/../../../eph/ephe');
    }

    /**
     * @dataProvider asteroidProvider
     */
    public function testAsteroidEclipticCoordinates(int $ipl, string $name, float $expectedLon, float $expectedLat, float $expectedDist): void
    {
        $xx = [];
        $serr = null;

        $ret = \swe_calc(self::JD, $ipl, Constants::SEFLG_SPEED, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed for $name: $serr");
        $this->assertCount(6, $xx, "Expected 6 elements in result array for $name");

        // Longitude tolerance: 0.01° (36 arcsec)
        $this->assertEqualsWithDelta($expectedLon, $xx[0], 0.01,
            "$name longitude mismatch");

        // Latitude tolerance: 0.01° (36 arcsec)
        $this->assertEqualsWithDelta($expectedLat, $xx[1], 0.01,
            "$name latitude mismatch");

        // Distance tolerance: 0.001 AU (~150,000 km)
        $this->assertEqualsWithDelta($expectedDist, $xx[2], 0.001,
            "$name distance mismatch");

        // Speed should be non-zero
        $this->assertNotEquals(0.0, $xx[3], "$name longitude speed should be non-zero");
    }

    public static function asteroidProvider(): array
    {
        $data = [];
        foreach (self::REFERENCE as $ipl => $ref) {
            $data[$ref['name']] = [$ipl, $ref['name'], $ref['lon'], $ref['lat'], $ref['dist']];
        }
        return $data;
    }

    /**
     * Test that all asteroids return valid speed values.
     */
    public function testAsteroidSpeeds(): void
    {
        foreach (self::REFERENCE as $ipl => $ref) {
            $xx = [];
            $serr = null;

            $ret = \swe_calc(self::JD, $ipl, Constants::SEFLG_SPEED, $xx, $serr);

            $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed for {$ref['name']}: $serr");

            // Speed should be reasonable (not zero, not huge)
            $speed = abs($xx[3]);
            $this->assertGreaterThan(0.0, $speed, "{$ref['name']} should have non-zero speed");
            $this->assertLessThan(2.0, $speed, "{$ref['name']} speed should be < 2°/day");
        }
    }

    /**
     * Test that asteroids can be calculated without SPEED flag.
     */
    public function testAsteroidWithoutSpeed(): void
    {
        foreach (self::REFERENCE as $ipl => $ref) {
            $xx = [];
            $serr = null;

            // Calculate without SEFLG_SPEED
            $ret = \swe_calc(self::JD, $ipl, 0, $xx, $serr);

            $this->assertGreaterThanOrEqual(0, $ret, "swe_calc failed for {$ref['name']}: $serr");
            $this->assertCount(6, $xx, "Expected 6 elements for {$ref['name']}");

            // Position should still be accurate
            $this->assertEqualsWithDelta($ref['lon'], $xx[0], 0.01,
                "{$ref['name']} longitude mismatch without speed");
        }
    }

    /**
     * Test equatorial coordinates for asteroids.
     */
    public function testAsteroidEquatorialCoordinates(): void
    {
        foreach (self::REFERENCE as $ipl => $ref) {
            $xx = [];
            $serr = null;

            $ret = \swe_calc(self::JD, $ipl, Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL, $xx, $serr);

            $this->assertGreaterThanOrEqual(0, $ret, "swe_calc EQUATORIAL failed for {$ref['name']}: $serr");
            $this->assertCount(6, $xx, "Expected 6 elements for {$ref['name']}");

            // RA should be 0-360
            $this->assertGreaterThanOrEqual(0.0, $xx[0], "{$ref['name']} RA should be >= 0");
            $this->assertLessThan(360.0, $xx[0], "{$ref['name']} RA should be < 360");

            // Dec should be -90 to +90
            $this->assertGreaterThanOrEqual(-90.0, $xx[1], "{$ref['name']} Dec should be >= -90");
            $this->assertLessThanOrEqual(90.0, $xx[1], "{$ref['name']} Dec should be <= 90");

            // Distance should match ecliptic calculation
            $this->assertEqualsWithDelta($ref['dist'], $xx[2], 0.001,
                "{$ref['name']} distance mismatch in equatorial");
        }
    }

    /**
     * Test that asteroids work correctly at different dates.
     */
    public function testAsteroidDifferentDates(): void
    {
        $dates = [
            2451545.0,  // J2000.0 (1 Jan 2000)
            2459580.5,  // 1 Jan 2022
            2460310.5,  // 1 Dec 2023
        ];

        foreach ($dates as $jd) {
            foreach ([Constants::SE_CHIRON, Constants::SE_CERES, Constants::SE_VESTA] as $ipl) {
                $xx = [];
                $serr = null;

                $ret = \swe_calc($jd, $ipl, Constants::SEFLG_SPEED, $xx, $serr);

                $this->assertGreaterThanOrEqual(0, $ret,
                    "swe_calc failed for ipl=$ipl at JD=$jd: $serr");

                // Basic sanity checks
                $this->assertGreaterThanOrEqual(0.0, $xx[0], "Longitude should be >= 0");
                $this->assertLessThan(360.0, $xx[0], "Longitude should be < 360");
                $this->assertGreaterThan(0.0, $xx[2], "Distance should be positive");
            }
        }
    }
}
