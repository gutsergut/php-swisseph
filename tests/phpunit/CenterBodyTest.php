<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

/**
 * Test SEFLG_CENTER_BODY functionality.
 *
 * SEFLG_CENTER_BODY returns the physical center of a planet's body
 * rather than the barycenter of the planet + moons system.
 *
 * For Jupiter, Saturn, Uranus, Neptune, Pluto the difference is measurable.
 * For Mercury-Mars the flag has no effect (center = barycenter).
 *
 * Reference values from swetest64.exe v2.10.03
 */
final class CenterBodyTest extends TestCase
{
    private const JD_J2000 = 2451545.0;
    private const EPHE_PATH = __DIR__ . '/../../../eph/ephe';

    protected function setUp(): void
    {
        if (!is_dir(self::EPHE_PATH)) {
            $this->markTestSkipped('Ephemeris directory not found: ' . self::EPHE_PATH);
        }
        \swe_set_ephe_path(self::EPHE_PATH);
    }

    /**
     * Test that Jupiter CENTER_BODY differs from barycenter.
     */
    public function testJupiterCenterBodyDiffersFromBarycenter(): void
    {
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

        // Get barycenter (standard)
        $xx_bary = [];
        $serr = '';
        $ret_bary = \swe_calc(self::JD_J2000, Constants::SE_JUPITER, $iflag, $xx_bary, $serr);
        $this->assertGreaterThanOrEqual(0, $ret_bary, "Barycenter calc failed: $serr");

        // Get center of body
        $xx_cob = [];
        $ret_cob = \swe_calc(self::JD_J2000, Constants::SE_JUPITER, $iflag | Constants::SEFLG_CENTER_BODY, $xx_cob, $serr);
        $this->assertGreaterThanOrEqual(0, $ret_cob, "CENTER_BODY calc failed: $serr");

        // They should differ for Jupiter
        $delta_lon = abs($xx_cob[0] - $xx_bary[0]);
        $delta_arcsec = $delta_lon * 3600;

        // Jupiter COB offset is about 0.018 arcsec from barycenter
        $this->assertGreaterThan(0.01, $delta_arcsec, 'Jupiter COB should differ from barycenter');
        $this->assertLessThan(0.1, $delta_arcsec, 'Jupiter COB offset should be < 0.1 arcsec');
    }

    /**
     * Test that ipl=9599 gives same result as SE_JUPITER + SEFLG_CENTER_BODY.
     */
    public function testDirectIpl9599EqualsJupiterCenterBody(): void
    {
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

        // Via flag
        $xx_flag = [];
        $serr = '';
        $ret_flag = \swe_calc(self::JD_J2000, Constants::SE_JUPITER, $iflag | Constants::SEFLG_CENTER_BODY, $xx_flag, $serr);
        $this->assertGreaterThanOrEqual(0, $ret_flag, "Flag calc failed: $serr");

        // Via direct ipl=9599
        $xx_direct = [];
        $ret_direct = \swe_calc(self::JD_J2000, 9599, $iflag, $xx_direct, $serr);
        $this->assertGreaterThanOrEqual(0, $ret_direct, "Direct 9599 calc failed: $serr");

        // Should be identical
        $this->assertEqualsWithDelta($xx_flag[0], $xx_direct[0], 1e-10, 'Longitude should match');
        $this->assertEqualsWithDelta($xx_flag[1], $xx_direct[1], 1e-10, 'Latitude should match');
        $this->assertEqualsWithDelta($xx_flag[2], $xx_direct[2], 1e-15, 'Distance should match');
    }

    /**
     * Test that Mars CENTER_BODY equals barycenter (Mars has no massive moons).
     */
    public function testMarsCenterBodyEqualsBarycenter(): void
    {
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

        $xx_bary = [];
        $xx_cob = [];
        $serr = '';

        \swe_calc(self::JD_J2000, Constants::SE_MARS, $iflag, $xx_bary, $serr);
        \swe_calc(self::JD_J2000, Constants::SE_MARS, $iflag | Constants::SEFLG_CENTER_BODY, $xx_cob, $serr);

        // Should be exactly the same for Mars
        $this->assertEquals($xx_bary[0], $xx_cob[0], 'Mars COB should equal barycenter');
    }

    /**
     * Test Jupiter CENTER_BODY accuracy vs C reference.
     *
     * C reference: swetest -b01.01.2000 -p5 -fPl -head -sid0 -center_body
     * Jupiter COB longitude at J2000: 25.2530526001°
     */
    public function testJupiterCenterBodyVsCReference(): void
    {
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
        $xx = [];
        $serr = '';

        $ret = \swe_calc(self::JD_J2000, Constants::SE_JUPITER, $iflag | Constants::SEFLG_CENTER_BODY, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $ret, "Calc failed: $serr");

        // C reference value
        $c_lon = 25.2530526001;
        $delta_arcsec = abs($xx[0] - $c_lon) * 3600;

        // Should be within 0.01 arcsec of C reference
        $this->assertLessThan(0.01, $delta_arcsec,
            sprintf('Jupiter COB accuracy: PHP=%.10f° C=%.10f° delta=%.4f"', $xx[0], $c_lon, $delta_arcsec));
    }

    /**
     * Test that SEFLG_CENTER_BODY flag is returned in output.
     */
    public function testCenterBodyFlagInOutput(): void
    {
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_CENTER_BODY;
        $xx = [];
        $serr = '';

        $ret = \swe_calc(self::JD_J2000, Constants::SE_JUPITER, $iflag, $xx, $serr);

        // Return value should have CENTER_BODY flag set
        $this->assertTrue(($ret & Constants::SEFLG_CENTER_BODY) !== 0,
            sprintf('CENTER_BODY flag should be in return: 0x%X', $ret));
    }

    /**
     * Test Saturn CENTER_BODY.
     */
    public function testSaturnCenterBody(): void
    {
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

        $xx_bary = [];
        $xx_cob = [];
        $serr = '';

        \swe_calc(self::JD_J2000, Constants::SE_SATURN, $iflag, $xx_bary, $serr);
        \swe_calc(self::JD_J2000, Constants::SE_SATURN, $iflag | Constants::SEFLG_CENTER_BODY, $xx_cob, $serr);

        // Saturn should also have measurable difference
        $delta_arcsec = abs($xx_cob[0] - $xx_bary[0]) * 3600;
        $this->assertGreaterThan(0.001, $delta_arcsec, 'Saturn COB should differ from barycenter');
    }

    /**
     * Test planet names for center of body codes.
     */
    public function testCenterBodyPlanetNames(): void
    {
        $this->assertEquals('Jupiter/COB', \swe_get_planet_name(9599));
        $this->assertEquals('Saturn/COB', \swe_get_planet_name(9699));
        $this->assertEquals('Uranus/COB', \swe_get_planet_name(9799));
        $this->assertEquals('Neptune/COB', \swe_get_planet_name(9899));
        $this->assertEquals('Pluto/COB', \swe_get_planet_name(9999));
    }
}
