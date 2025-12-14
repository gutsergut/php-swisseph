<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

/**
 * Tests for SEFLG_BARYCTR (barycentric coordinates) support.
 * Reference values from swetest64.exe with SWIEPH files.
 */
final class BarycentricCoordinatesTest extends TestCase
{
    private const TOLERANCE_AU = 1e-4;  // ~15000 km (light-time approximation)
    private const TOLERANCE_SUN = 1e-6; // ~150 m for Sun (no light-time iteration needed)

    protected function setUp(): void
    {
        swe_set_ephe_path(__DIR__ . '/../../../eph/ephe');

        // Debug: check Venus speed
        $xx = [];
        $serr = '';
        swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xx, $serr);
        echo "\n[BarycentricTest setUp] Venus speed: {$xx[3]} deg/day\n";
    }

    /**
     * Test Sun barycentric coordinates.
     * Reference: swetest64.exe -j2460000.5 -p0 -fX -bary
     */
    #[Test]
    public function sunBarycentricXYZ(): void
    {
        $tjd = 2460000.5;
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_XYZ;
        $xx = [];
        $serr = '';

        $ret = PlanetsFunctions::calc($tjd, Constants::SE_SUN, $iflag, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "calc() should return iflag, got error: $serr");

        // Reference from swetest64 with SWIEPH
        $this->assertEqualsWithDelta(-0.008981057, $xx[0], self::TOLERANCE_SUN, 'Sun X coordinate');
        $this->assertEqualsWithDelta(-0.000445411, $xx[1], self::TOLERANCE_SUN, 'Sun Y coordinate');
        $this->assertEqualsWithDelta(0.000212362, $xx[2], self::TOLERANCE_SUN, 'Sun Z coordinate');
    }

    /**
     * Test Mercury barycentric coordinates with TRUEPOS (no light-time).
     * Reference: swetest64.exe -j2460000.5 -p2 -fX -bary -true
     */
    #[Test]
    public function mercuryBarycentricTrueposXYZ(): void
    {
        $tjd = 2460000.5;
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS;
        $xx = [];
        $serr = '';

        $ret = PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "calc() should return iflag, got error: $serr");

        // Reference from swetest64 with SWIEPH, TRUEPOS
        $this->assertEqualsWithDelta(0.095251399, $xx[0], self::TOLERANCE_AU, 'Mercury X coordinate');
        $this->assertEqualsWithDelta(-0.441060814, $xx[1], self::TOLERANCE_AU, 'Mercury Y coordinate');
        $this->assertEqualsWithDelta(-0.045199093, $xx[2], self::TOLERANCE_AU, 'Mercury Z coordinate');
    }

    /**
     * Test Mercury barycentric coordinates (apparent, with light-time).
     * Note: PHP uses simplified light-time correction (velocity * dt),
     * while C re-computes ephemeris for t - dt. This causes ~30 km difference.
     * Reference: swetest64.exe -j2460000.5 -p2 -fX -bary
     */
    #[Test]
    public function mercuryBarycentricApparentXYZ(): void
    {
        $tjd = 2460000.5;
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_XYZ;
        $xx = [];
        $serr = '';

        $ret = PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "calc() should return iflag, got error: $serr");

        // Reference from swetest64 with SWIEPH
        // Wider tolerance due to light-time approximation
        $this->assertEqualsWithDelta(0.095194482, $xx[0], self::TOLERANCE_AU, 'Mercury X coordinate');
        $this->assertEqualsWithDelta(-0.441081431, $xx[1], self::TOLERANCE_AU, 'Mercury Y coordinate');
        $this->assertEqualsWithDelta(-0.045195524, $xx[2], self::TOLERANCE_AU, 'Mercury Z coordinate');
    }

    /**
     * Test Jupiter barycentric coordinates.
     * Reference: swetest64.exe -j2460000.5 -p5 -fX -bary -true
     */
    #[Test]
    public function jupiterBarycentricTrueposXYZ(): void
    {
        $tjd = 2460000.5;
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS;
        $xx = [];
        $serr = '';

        $ret = PlanetsFunctions::calc($tjd, Constants::SE_JUPITER, $iflag, $xx, $serr);

        $this->assertGreaterThanOrEqual(0, $ret, "calc() should return iflag, got error: $serr");

        // Just check that coordinates are reasonable (outer planet, far from Sun)
        $dist = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
        $this->assertGreaterThan(4.0, $dist, 'Jupiter should be > 4 AU from SSB');
        $this->assertLessThan(6.5, $dist, 'Jupiter should be < 6.5 AU from SSB');
    }

    /**
     * Test that barycentric coordinates differ from heliocentric by Sun position.
     */
    #[Test]
    public function barycentricMinusHeliocentricEqualsSunPosition(): void
    {
        $tjd = 2460000.5;
        $iflag_base = Constants::SEFLG_SWIEPH | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS;
        $serr = '';

        // Get barycentric Mercury
        $xx_bary = [];
        PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag_base | Constants::SEFLG_BARYCTR, $xx_bary, $serr);

        // Get heliocentric Mercury
        $xx_helio = [];
        PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag_base | Constants::SEFLG_HELCTR, $xx_helio, $serr);

        // Get barycentric Sun
        $xx_sun = [];
        PlanetsFunctions::calc($tjd, Constants::SE_SUN, $iflag_base | Constants::SEFLG_BARYCTR, $xx_sun, $serr);

        // barycentric = heliocentric + sun_position (for TRUEPOS)
        for ($i = 0; $i < 3; $i++) {
            $expected = $xx_helio[$i] + $xx_sun[$i];
            $this->assertEqualsWithDelta($expected, $xx_bary[$i], 1e-8,
                "Barycentric[$i] should equal heliocentric[$i] + sun[$i]");
        }
    }
}
