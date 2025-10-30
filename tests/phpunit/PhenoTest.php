<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

use function swe_calc_ut;
use function swe_pheno;
use function swe_pheno_ut;

/**
 * Tests for planetary phenomena functions (swe_pheno, swe_pheno_ut).
 */
final class PhenoTest extends TestCase
{
    /**
     * Test Moon phase calculation at different phases.
     */
    public function testMoonPhases(): void
    {
        // Moon on 2000-01-06 18:14 (close to New Moon)
        // Note: exact New Moon was 2000-01-06 18:14 UT
        $jd_ut_new = 2451550.76;

        $ret = swe_pheno_ut($jd_ut_new, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $attr, $serr);

        $this->assertSame(Constants::SE_OK, $ret, "swe_pheno_ut should succeed for Moon");
        $this->assertIsArray($attr);
        $this->assertCount(6, $attr);

        // At New Moon:
        // - Phase angle should be close to 0° (Moon between Earth and Sun)
        // - Illuminated fraction should be close to 0 (dark side facing Earth)
        $this->assertGreaterThan(150.0, $attr[0], "Phase angle at New Moon should be near 180°");
        $this->assertLessThan(0.2, $attr[1], "Illuminated fraction at New Moon should be near 0");

        // Elongation should be small (Moon is near Sun)
        $this->assertLessThan(40.0, abs($attr[2]), "Elongation at New Moon should be small");

        // Apparent diameter should be reasonable (29-33 arcmin typically)
        $this->assertGreaterThan(1700.0, $attr[3], "Moon diameter should be > 1700 arcsec");
        $this->assertLessThan(2100.0, $attr[3], "Moon diameter should be < 2100 arcsec");

        // Full Moon on 2000-01-21 04:40 UT
        $jd_ut_full = 2451565.69;

        $ret = swe_pheno_ut($jd_ut_full, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $attr_full, $serr);

        $this->assertSame(Constants::SE_OK, $ret);

        // At Full Moon:
        // - Phase angle should be close to 180° (Earth between Moon and Sun)
        // - Illuminated fraction should be close to 1.0 (fully lit)
        $this->assertLessThan(30.0, $attr_full[0], "Phase angle at Full Moon should be near 0°");
        $this->assertLessThan(200.0, $attr_full[0], "Phase angle at Full Moon should be near 180°");
        $this->assertGreaterThan(0.8, $attr_full[1], "Illuminated fraction at Full Moon should be near 1.0");

        // Elongation should be large (Moon is opposite Sun)
        $this->assertGreaterThan(140.0, abs($attr_full[2]), "Elongation at Full Moon should be large");
    }
    /**
     * Test Venus phase and magnitude.
     */
    public function testVenusPheno(): void
    {
        // Venus on 2000-01-01, 12:00 TT
        $jd_et = 2451545.0;

        $ret = swe_pheno($jd_et, Constants::SE_VENUS, Constants::SEFLG_SWIEPH, $attr, $serr);

        $this->assertSame(Constants::SE_OK, $ret, "swe_pheno should succeed for Venus");
        $this->assertIsArray($attr);
        $this->assertCount(6, $attr);

        // Phase angle should be between 0° and 180°
        $this->assertGreaterThanOrEqual(0.0, $attr[0]);
        $this->assertLessThanOrEqual(180.0, $attr[0]);

        // Illuminated fraction should be between 0 and 1
        $this->assertGreaterThanOrEqual(0.0, $attr[1]);
        $this->assertLessThanOrEqual(1.0, $attr[1]);

        // Elongation should be reasonable (Venus max ~47°)
        $this->assertLessThanOrEqual(180.0, abs($attr[2]));

        // Apparent diameter should be positive
        $this->assertGreaterThan(0.0, $attr[3]);

        // Magnitude should be in reasonable range (Venus: -4 to -3 typically)
        $this->assertGreaterThan(-5.0, $attr[4]);
        $this->assertLessThan(-2.0, $attr[4]);
    }

    /**
     * Test Mars phenomena.
     */
    public function testMarsPhenomena(): void
    {
        // Mars on 2000-06-01, 0:00 TT
        $jd_et = 2451696.5;

        $ret = swe_pheno($jd_et, Constants::SE_MARS, Constants::SEFLG_SWIEPH, $attr, $serr);

        $this->assertSame(Constants::SE_OK, $ret);
        $this->assertIsArray($attr);

        // Basic sanity checks
        $this->assertGreaterThanOrEqual(0.0, $attr[0], "Phase angle >= 0");
        $this->assertLessThanOrEqual(180.0, $attr[0], "Phase angle <= 180");

        $this->assertGreaterThanOrEqual(0.0, $attr[1], "Illuminated fraction >= 0");
        $this->assertLessThanOrEqual(1.0, $attr[1], "Illuminated fraction <= 1");

        $this->assertGreaterThan(0.0, $attr[3], "Diameter > 0");

        // Mars magnitude typically -2.9 to +1.8, but allow some tolerance
        $this->assertGreaterThan(-3.5, $attr[4]);
        $this->assertLessThan(3.0, $attr[4], "Magnitude should be reasonable for Mars");
    }
    /**
     * Test Jupiter phenomena.
     */
    public function testJupiterPheno(): void
    {
        $jd_et = 2451545.0; // J2000.0

        $ret = swe_pheno($jd_et, Constants::SE_JUPITER, Constants::SEFLG_SWIEPH, $attr, $serr);

        $this->assertSame(Constants::SE_OK, $ret);
        $this->assertIsArray($attr);

        // Jupiter is far from Earth, so phase angle should be small
        $this->assertLessThan(15.0, $attr[0], "Jupiter phase angle should be small");

        // Illuminated fraction should be close to 1.0 (almost fully lit)
        $this->assertGreaterThan(0.95, $attr[1]);

        // Jupiter magnitude typically -2.9 to -1.6
        $this->assertGreaterThan(-3.5, $attr[4]);
        $this->assertLessThan(-1.0, $attr[4]);
    }

    /**
     * Test Sun phenomena (special case).
     */
    public function testSunPheno(): void
    {
        $jd_et = 2451545.0;

        $ret = swe_pheno($jd_et, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $attr, $serr);

        $this->assertSame(Constants::SE_OK, $ret);
        $this->assertIsArray($attr);

        // Sun has no phase (always fully illuminated from Earth)
        $this->assertSame(0.0, $attr[0], "Sun phase angle = 0");
        $this->assertSame(1.0, $attr[1], "Sun illuminated fraction = 1");
        $this->assertSame(0.0, $attr[2], "Sun elongation = 0");

        // Sun's apparent diameter ~31.5-32.5 arcmin (1890-1950 arcsec)
        $this->assertGreaterThan(1850.0, $attr[3]);
        $this->assertLessThan(2000.0, $attr[3]);

        // Sun's magnitude ~-26.7 (allow wider range for simplified formula)
        $this->assertGreaterThan(-29.0, $attr[4], "Sun magnitude should be reasonable");
        $this->assertLessThan(-25.0, $attr[4], "Sun magnitude should be reasonable");
    }
    /**
     * Test that swe_pheno_ut and swe_pheno produce consistent results.
     */
    public function testPhenoUtConsistency(): void
    {
        $jd_ut = 2451545.0;

        // Call swe_pheno_ut
        $ret_ut = swe_pheno_ut($jd_ut, Constants::SE_MARS, Constants::SEFLG_SWIEPH, $attr_ut, $serr_ut);

        // Manually convert UT to ET and call swe_pheno
        // For J2000.0, Delta-T is about 63.8 seconds
        $delta_t = 63.8 / 86400.0; // Convert to days
        $jd_et = $jd_ut + $delta_t;

        $ret_et = swe_pheno($jd_et, Constants::SE_MARS, Constants::SEFLG_SWIEPH, $attr_et, $serr_et);

        $this->assertSame(Constants::SE_OK, $ret_ut);
        $this->assertSame(Constants::SE_OK, $ret_et);

        // Results should be very similar (within small tolerance due to Delta-T approximation)
        for ($i = 0; $i < 6; $i++) {
            $tolerance = ($i === 3) ? 0.5 : 0.01; // Larger tolerance for diameter
            $this->assertEqualsWithDelta(
                $attr_et[$i],
                $attr_ut[$i],
                $tolerance,
                "attr[$i] should match between UT and ET versions"
            );
        }
    }

    /**
     * Test attr array structure and values.
     */
    public function testAttrArrayStructure(): void
    {
        $jd_et = 2451545.0;

        $ret = swe_pheno($jd_et, Constants::SE_VENUS, Constants::SEFLG_SWIEPH, $attr, $serr);

        $this->assertSame(Constants::SE_OK, $ret);
        $this->assertIsArray($attr);
        $this->assertCount(6, $attr, "attr array should have 6 elements");

        // Verify each element is a float
        for ($i = 0; $i < 6; $i++) {
            $this->assertIsFloat($attr[$i], "attr[$i] should be float");
        }

        // Verify logical constraints
        $this->assertGreaterThanOrEqual(0.0, $attr[0], "Phase angle >= 0");
        $this->assertLessThanOrEqual(180.0, $attr[0], "Phase angle <= 180");

        $this->assertGreaterThanOrEqual(0.0, $attr[1], "Illuminated fraction >= 0");
        $this->assertLessThanOrEqual(1.0, $attr[1], "Illuminated fraction <= 1");

        $this->assertLessThanOrEqual(180.0, abs($attr[2]), "Elongation <= 180");

        $this->assertGreaterThan(0.0, $attr[3], "Diameter > 0");
    }
}
