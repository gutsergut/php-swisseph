<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

/**
 * Test JplEphemeris reading and interpolation
 * Reference values from swetest64.exe with -j2000 -bary flags
 * (raw ICRF coordinates without precession)
 */
class JplEphemerisTest extends TestCase
{
    private const EPH_DIR = __DIR__ . '/../../eph/data/ephemerides/jpl';

    /**
     * Tolerance in AU (~10,000 km)
     * JPL ephemeris has intrinsic accuracy of ~few km, but our interpolation
     * has small numerical differences
     */
    private const TOLERANCE_AU = 0.0001;

    public static function setUpBeforeClass(): void
    {
        JplEphemeris::resetInstance();
    }

    public static function tearDownAfterClass(): void
    {
        $jpl = JplEphemeris::getInstance();
        $jpl->close();
    }

    /**
     * Test opening DE200 ephemeris (little-endian)
     */
    public function testOpenDE200(): void
    {
        $jpl = JplEphemeris::getInstance();
        $ss = [];
        $serr = '';

        $ret = $jpl->open($ss, 'de200.eph', self::EPH_DIR, $serr);

        $this->assertEquals(JplConstants::OK, $ret, "Failed to open de200.eph: $serr");
        $this->assertEqualsWithDelta(2305424.5, $ss[0], 0.1, 'Start epoch');
        $this->assertEqualsWithDelta(2513392.5, $ss[1], 0.1, 'End epoch');
        $this->assertEqualsWithDelta(32.0, $ss[2], 0.1, 'Segment size');
        $this->assertEquals(200, $jpl->getDenum(), 'DE number');
    }

    /**
     * Test Mercury barycentric coordinates at J2000
     * Reference: swetest -ejplde200.eph -p2 -bj2451545.0 -fPx -bary -j2000
     */
    public function testMercuryJ2000(): void
    {
        $jpl = JplEphemeris::getInstance();
        $pv = [];
        $serr = '';

        $ret = $jpl->pleph(
            2451545.0,  // J2000
            JplConstants::J_MERCURY,
            JplConstants::J_SBARY,
            $pv,
            $serr
        );

        $this->assertEquals(JplConstants::OK, $ret, "pleph failed: $serr");

        // swetest reference: -0.137290981   -0.403222600   -0.201397090
        $this->assertEqualsWithDelta(-0.137290981, $pv[0], self::TOLERANCE_AU, 'X coordinate');
        $this->assertEqualsWithDelta(-0.403222600, $pv[1], self::TOLERANCE_AU, 'Y coordinate');
        $this->assertEqualsWithDelta(-0.201397090, $pv[2], self::TOLERANCE_AU, 'Z coordinate');
    }

    /**
     * Test Mercury at early date in DE200
     * Reference: swetest -ejplde200.eph -p2 -bj2305500.0 -fPx -bary -j2000
     */
    public function testMercuryEarlyDate(): void
    {
        $jpl = JplEphemeris::getInstance();
        $pv = [];
        $serr = '';

        $ret = $jpl->pleph(
            2305500.0,
            JplConstants::J_MERCURY,
            JplConstants::J_SBARY,
            $pv,
            $serr
        );

        $this->assertEquals(JplConstants::OK, $ret, "pleph failed: $serr");

        // swetest reference: -0.373557180   -0.186509746   -0.059546932
        $this->assertEqualsWithDelta(-0.373557180, $pv[0], self::TOLERANCE_AU, 'X coordinate');
        $this->assertEqualsWithDelta(-0.186509746, $pv[1], self::TOLERANCE_AU, 'Y coordinate');
        $this->assertEqualsWithDelta(-0.059546932, $pv[2], self::TOLERANCE_AU, 'Z coordinate');
    }

    /**
     * Test Venus barycentric coordinates
     * Reference: swetest -ejplde200.eph -p3 -bj2451545.0 -fPx -bary -j2000
     */
    public function testVenusJ2000(): void
    {
        $jpl = JplEphemeris::getInstance();
        $pv = [];
        $serr = '';

        $ret = $jpl->pleph(
            2451545.0,
            JplConstants::J_VENUS,
            JplConstants::J_SBARY,
            $pv,
            $serr
        );

        $this->assertEquals(JplConstants::OK, $ret, "pleph failed: $serr");

        // Just verify we get reasonable values (Venus ~0.7 AU from Sun)
        $dist = sqrt($pv[0]**2 + $pv[1]**2 + $pv[2]**2);
        $this->assertGreaterThan(0.5, $dist, 'Distance too small');
        $this->assertLessThan(1.0, $dist, 'Distance too large');
    }

    /**
     * Test Earth-Moon barycenter
     * Reference: swetest -ejplde200.eph -p14 -bj2451545.0 -fPx -bary -j2000
     */
    public function testEarthJ2000(): void
    {
        $jpl = JplEphemeris::getInstance();
        $pv = [];
        $serr = '';

        $ret = $jpl->pleph(
            2451545.0,
            JplConstants::J_EARTH,
            JplConstants::J_SBARY,
            $pv,
            $serr
        );

        $this->assertEquals(JplConstants::OK, $ret, "pleph failed: $serr");

        // Earth ~1 AU from SSB
        $dist = sqrt($pv[0]**2 + $pv[1]**2 + $pv[2]**2);
        $this->assertGreaterThan(0.9, $dist, 'Distance too small');
        $this->assertLessThan(1.1, $dist, 'Distance too large');
    }

    /**
     * Test Sun barycentric position (Sun-SSB offset)
     */
    public function testSunJ2000(): void
    {
        $jpl = JplEphemeris::getInstance();
        $pv = [];
        $serr = '';

        $ret = $jpl->pleph(
            2451545.0,
            JplConstants::J_SUN,
            JplConstants::J_SBARY,
            $pv,
            $serr
        );

        $this->assertEquals(JplConstants::OK, $ret, "pleph failed: $serr");

        // Sun should be very close to SSB (within ~0.02 AU typically)
        $dist = sqrt($pv[0]**2 + $pv[1]**2 + $pv[2]**2);
        $this->assertLessThan(0.03, $dist, 'Sun too far from SSB');
    }

    /**
     * Test date out of range
     */
    public function testDateOutOfRange(): void
    {
        $jpl = JplEphemeris::getInstance();
        $pv = [];
        $serr = '';

        $ret = $jpl->pleph(
            1000000.0,  // Way before DE200 start
            JplConstants::J_MERCURY,
            JplConstants::J_SBARY,
            $pv,
            $serr
        );

        $this->assertEquals(JplConstants::BEYOND_EPH_LIMITS, $ret);
    }

    /**
     * Test opening DE406e ephemeris (big-endian)
     */
    public function testOpenDE406e(): void
    {
        // Close DE200 first
        $jpl = JplEphemeris::getInstance();
        $jpl->close();
        JplEphemeris::resetInstance();

        $jpl = JplEphemeris::getInstance();
        $ss = [];
        $serr = '';

        $ret = $jpl->open($ss, 'de406e.eph', self::EPH_DIR, $serr);

        $this->assertEquals(JplConstants::OK, $ret, "Failed to open de406e.eph: $serr");
        $this->assertEqualsWithDelta(-254895.5, $ss[0], 0.1, 'Start epoch');
        $this->assertEqualsWithDelta(3696976.5, $ss[1], 0.1, 'End epoch');
        $this->assertEqualsWithDelta(64.0, $ss[2], 0.1, 'Segment size');
        $this->assertEquals(406, $jpl->getDenum(), 'DE number');
    }

    /**
     * Test Mercury coordinates from DE406e (big-endian)
     * Reference: swetest -ejplde406e.eph -p2 -bj2451545.0 -fPx -bary -j2000
     */
    public function testMercuryDE406e(): void
    {
        $jpl = JplEphemeris::getInstance();
        $pv = [];
        $serr = '';

        $ret = $jpl->pleph(
            2451545.0,  // J2000
            JplConstants::J_MERCURY,
            JplConstants::J_SBARY,
            $pv,
            $serr
        );

        $this->assertEquals(JplConstants::OK, $ret, "pleph failed: $serr");

        // swetest reference: -0.137288206   -0.403227332   -0.201399029
        $this->assertEqualsWithDelta(-0.137288206, $pv[0], self::TOLERANCE_AU, 'X coordinate');
        $this->assertEqualsWithDelta(-0.403227332, $pv[1], self::TOLERANCE_AU, 'Y coordinate');
        $this->assertEqualsWithDelta(-0.201399029, $pv[2], self::TOLERANCE_AU, 'Z coordinate');
    }

    /**
     * Test early date in DE406e (big-endian)
     * Reference: swetest -ejplde406e.eph -p2 -bj-254863.5 -fPx -bary -j2000
     */
    public function testMercuryDE406eEarlyDate(): void
    {
        $jpl = JplEphemeris::getInstance();
        $pv = [];
        $serr = '';

        $ret = $jpl->pleph(
            -254863.5,  // Near start of DE406e
            JplConstants::J_MERCURY,
            JplConstants::J_SBARY,
            $pv,
            $serr
        );

        $this->assertEquals(JplConstants::OK, $ret, "pleph failed: $serr");

        // swetest reference: -0.219466143    0.217997287    0.140809041
        $this->assertEqualsWithDelta(-0.219466143, $pv[0], self::TOLERANCE_AU, 'X coordinate');
        $this->assertEqualsWithDelta(0.217997287, $pv[1], self::TOLERANCE_AU, 'Y coordinate');
        $this->assertEqualsWithDelta(0.140809041, $pv[2], self::TOLERANCE_AU, 'Z coordinate');
    }}
