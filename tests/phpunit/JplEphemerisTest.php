<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use Swisseph\Swe\Jpl\JplConstants;
use Swisseph\Swe\Jpl\JplEphemeris;

/**
 * Test JPL Ephemeris Reader
 */
class JplEphemerisTest extends TestCase
{
    private static string $ephePath = '';
    private static bool $hasJplFile = false;

    public static function setUpBeforeClass(): void
    {
        // Look for JPL ephemeris file in common locations
        $possiblePaths = [
            __DIR__ . '/../../eph/ephe',
            __DIR__ . '/../../../eph/ephe',
            'C:/sweph/ephe',
        ];

        $jplFiles = ['de441.eph', 'de440.eph', 'de431.eph', 'de430.eph', 'de421.eph', 'de406.eph', 'de405.eph'];

        foreach ($possiblePaths as $path) {
            foreach ($jplFiles as $file) {
                $fullPath = $path . '/' . $file;
                if (file_exists($fullPath)) {
                    self::$ephePath = $path;
                    self::$hasJplFile = true;
                    break 2;
                }
            }
        }
    }

    protected function setUp(): void
    {
        JplEphemeris::resetInstance();
    }

    public function testConstantsAreDefined(): void
    {
        $this->assertSame(0, JplConstants::J_MERCURY);
        $this->assertSame(9, JplConstants::J_MOON);
        $this->assertSame(10, JplConstants::J_SUN);
        $this->assertSame(11, JplConstants::J_SBARY);
        $this->assertSame(13, JplConstants::J_NUT);
        $this->assertSame(14, JplConstants::J_LIB);
    }

    public function testSeToJplMapping(): void
    {
        // Test SE to JPL mapping
        $this->assertArrayHasKey(0, JplConstants::SE_TO_JPL); // SE_SUN
        $this->assertSame(JplConstants::J_SUN, JplConstants::SE_TO_JPL[0]);
        $this->assertSame(JplConstants::J_MOON, JplConstants::SE_TO_JPL[1]);
        $this->assertSame(JplConstants::J_MERCURY, JplConstants::SE_TO_JPL[2]);
    }

    public function testJplToSeMapping(): void
    {
        // Test JPL to SE mapping
        $this->assertArrayHasKey(JplConstants::J_SUN, JplConstants::JPL_TO_SE);
        $this->assertSame(0, JplConstants::JPL_TO_SE[JplConstants::J_SUN]); // SE_SUN
        $this->assertSame(1, JplConstants::JPL_TO_SE[JplConstants::J_MOON]); // SE_MOON
    }

    public function testSingletonInstance(): void
    {
        $instance1 = JplEphemeris::getInstance();
        $instance2 = JplEphemeris::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testOpenNonExistentFile(): void
    {
        $jpl = JplEphemeris::getInstance();

        $ss = [];
        $serr = null;
        $ret = $jpl->open($ss, 'nonexistent.eph', '/nonexistent/path', $serr);

        $this->assertSame(JplConstants::NOT_AVAILABLE, $ret);
        $this->assertNotNull($serr);
        $this->assertStringContainsString('not found', $serr);
    }

    /**
     * @group requires-jpl
     */
    public function testOpenRealFile(): void
    {
        if (!self::$hasJplFile) {
            $this->markTestSkipped('No JPL ephemeris file available');
        }

        $jpl = JplEphemeris::getInstance();

        // Find available file
        $jplFiles = ['de441.eph', 'de440.eph', 'de431.eph', 'de430.eph', 'de421.eph', 'de406.eph', 'de405.eph'];
        $foundFile = null;
        foreach ($jplFiles as $file) {
            if (file_exists(self::$ephePath . '/' . $file)) {
                $foundFile = $file;
                break;
            }
        }

        $this->assertNotNull($foundFile, 'Should find a JPL file');

        $ss = [];
        $serr = null;
        $ret = $jpl->open($ss, $foundFile, self::$ephePath, $serr);

        $this->assertSame(JplConstants::OK, $ret, 'Should open file successfully: ' . ($serr ?? ''));
        $this->assertCount(3, $ss);
        $this->assertGreaterThan(0, $ss[2], 'Segment size should be positive');

        $denum = $jpl->getDenum();
        $this->assertGreaterThan(100, $denum, 'DE number should be valid');

        $jpl->close();
    }

    /**
     * @group requires-jpl
     */
    public function testPlephSunPosition(): void
    {
        if (!self::$hasJplFile) {
            $this->markTestSkipped('No JPL ephemeris file available');
        }

        $jpl = JplEphemeris::getInstance();

        // Find available file
        $jplFiles = ['de441.eph', 'de440.eph', 'de431.eph', 'de430.eph', 'de421.eph', 'de406.eph', 'de405.eph'];
        foreach ($jplFiles as $file) {
            if (file_exists(self::$ephePath . '/' . $file)) {
                $ss = [];
                $jpl->open($ss, $file, self::$ephePath, $serr);
                break;
            }
        }

        // J2000.0 = JD 2451545.0
        $jd = 2451545.0;

        $rrd = [];
        $serr = null;
        $ret = $jpl->pleph($jd, JplConstants::J_SUN, JplConstants::J_SBARY, $rrd, $serr);

        $this->assertSame(JplConstants::OK, $ret, 'Should compute Sun position');
        $this->assertCount(6, $rrd);

        // Sun should be close to barycenter but not exactly at it
        $dist = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
        $this->assertLessThan(0.02, $dist, 'Sun should be within 0.02 AU of barycenter');
        $this->assertGreaterThan(0.0, $dist, 'Sun should not be exactly at barycenter');

        $jpl->close();
    }

    /**
     * @group requires-jpl
     */
    public function testPlephMoonPosition(): void
    {
        if (!self::$hasJplFile) {
            $this->markTestSkipped('No JPL ephemeris file available');
        }

        $jpl = JplEphemeris::getInstance();

        // Find available file
        $jplFiles = ['de441.eph', 'de440.eph', 'de431.eph', 'de430.eph', 'de421.eph', 'de406.eph', 'de405.eph'];
        foreach ($jplFiles as $file) {
            if (file_exists(self::$ephePath . '/' . $file)) {
                $ss = [];
                $jpl->open($ss, $file, self::$ephePath, $serr);
                break;
            }
        }

        $jd = 2451545.0;  // J2000.0

        $rrd = [];
        $serr = null;
        $ret = $jpl->pleph($jd, JplConstants::J_MOON, JplConstants::J_EARTH, $rrd, $serr);

        $this->assertSame(JplConstants::OK, $ret, 'Should compute Moon position');
        $this->assertCount(6, $rrd);

        // Moon should be about 0.00257 AU from Earth (average)
        $dist = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
        $this->assertGreaterThan(0.002, $dist, 'Moon distance too small');
        $this->assertLessThan(0.003, $dist, 'Moon distance too large');

        $jpl->close();
    }

    /**
     * @group requires-jpl
     */
    public function testPlephPlanetPositions(): void
    {
        if (!self::$hasJplFile) {
            $this->markTestSkipped('No JPL ephemeris file available');
        }

        $jpl = JplEphemeris::getInstance();

        // Find available file
        $jplFiles = ['de441.eph', 'de440.eph', 'de431.eph', 'de430.eph', 'de421.eph', 'de406.eph', 'de405.eph'];
        foreach ($jplFiles as $file) {
            if (file_exists(self::$ephePath . '/' . $file)) {
                $ss = [];
                $jpl->open($ss, $file, self::$ephePath, $serr);
                break;
            }
        }

        $jd = 2451545.0;  // J2000.0

        // Expected approximate heliocentric distances at J2000 (AU)
        $expectedDistances = [
            JplConstants::J_MERCURY => [0.3, 0.5],
            JplConstants::J_VENUS => [0.7, 0.8],
            JplConstants::J_EARTH => [0.98, 1.02],
            JplConstants::J_MARS => [1.3, 1.7],
            JplConstants::J_JUPITER => [4.9, 5.5],
            JplConstants::J_SATURN => [9.0, 10.5],
            JplConstants::J_URANUS => [18.0, 20.5],
            JplConstants::J_NEPTUNE => [29.0, 31.0],
            JplConstants::J_PLUTO => [29.0, 50.0],
        ];

        foreach ($expectedDistances as $body => $range) {
            $rrd = [];
            $serr = null;
            $ret = $jpl->pleph($jd, $body, JplConstants::J_SUN, $rrd, $serr);

            $this->assertSame(JplConstants::OK, $ret, "Should compute body $body position");

            $dist = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
            $this->assertGreaterThan($range[0], $dist, "Body $body too close to Sun");
            $this->assertLessThan($range[1], $dist, "Body $body too far from Sun");
        }

        $jpl->close();
    }

    /**
     * @group requires-jpl
     */
    public function testPlephNutations(): void
    {
        if (!self::$hasJplFile) {
            $this->markTestSkipped('No JPL ephemeris file available');
        }

        $jpl = JplEphemeris::getInstance();

        // Find available file
        $jplFiles = ['de441.eph', 'de440.eph', 'de431.eph', 'de430.eph', 'de421.eph', 'de406.eph', 'de405.eph'];
        foreach ($jplFiles as $file) {
            if (file_exists(self::$ephePath . '/' . $file)) {
                $ss = [];
                $jpl->open($ss, $file, self::$ephePath, $serr);
                break;
            }
        }

        $jd = 2451545.0;  // J2000.0

        $rrd = [];
        $serr = null;
        $ret = $jpl->pleph($jd, JplConstants::J_NUT, 0, $rrd, $serr);

        // Some DE files don't have nutations
        if ($ret === JplConstants::OK) {
            $this->assertCount(6, $rrd);
            // Nutation values should be small (radians)
            $this->assertLessThan(0.001, abs($rrd[0]), 'Nutation dpsi too large');
            $this->assertLessThan(0.001, abs($rrd[1]), 'Nutation deps too large');
        } else {
            // NOT_AVAILABLE is OK - some files don't have nutations
            $this->assertSame(JplConstants::NOT_AVAILABLE, $ret);
        }

        $jpl->close();
    }
}
