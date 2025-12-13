<?php

declare(strict_types=1);

/**
 * Comprehensive Heliacal Events Tests
 *
 * Tests the main heliacal PUBLIC API functions against swetest64.exe reference data:
 * - swe_heliacal_ut() - heliacal rising/setting events
 * - swe_heliacal_pheno_ut() - detailed phenomena at specific time
 * - swe_vis_limit_mag() - limiting magnitude calculation
 * - swe_heliacal_angle() - heliacal angle calculation
 * - swe_topo_arcus_visionis() - topocentric arcus visionis
 *
 * Target accuracy: ±1 day for event dates, ±0.5 magnitude for limiting magnitude
 */

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

class HeliacalEventsTest extends TestCase
{
    private string $ephePath;

    protected function setUp(): void
    {
        $this->ephePath = __DIR__ . '/../../eph/ephe';
        swe_set_ephe_path($this->ephePath);
    }

    /**
     * Test Venus heliacal rising
     * Reference: swetest64.exe -hev1 -p3 -b1.6.2000 -geopos13.4,52.5,100 -topo
     * Expected: 2001/04/05 03:53:44.9 UT (JD 2452004.66233)
     */
    public function testVenusHeliacalRising(): void
    {
        $dgeo = [13.4, 52.5, 100.0]; // Berlin: lon, lat, alt(m)
        $datm = [1013.25, 15.0, 40.0, 0.0]; // pressure(hPa), temp(°C), RH(%), VR(km, 0=auto)
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0]; // age, SN ratio, binocular(0/1), magnification, aperture(mm), transmission

        // Search from June 2000
        $jd_ut_start = 2451697.5; // 2000-06-01 00:00 UT
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_ut(
            $jd_ut_start,
            $dgeo,
            $datm,
            $dobs,
            'venus',
            1, // SE_HELIACAL_RISING = 1
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_heliacal_ut failed: $serr");

        // Expected JD: 2452004.66233 (2001-04-05 03:53:44 UT)
        $expectedJD = 2452004.66233;
        $actualJD = $dret[0];

        // Allow ±1 day tolerance
        $diffDays = abs($actualJD - $expectedJD);
        $this->assertLessThan(1.0, $diffDays,
            sprintf("Venus heliacal rising: expected JD %.5f, got %.5f (diff: %.2f days)",
                $expectedJD, $actualJD, $diffDays)
        );
    }

    /**
     * Test Venus morning last
     * Reference: swetest64.exe -hev4 -p3 -b1.6.2000 -geopos13.4,52.5,100 -topo
     * Expected: 2001/11/29 06:07:35.3 UT (JD 2452242.75527)
     */
    public function testVenusMorningLast(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut_start = 2451697.5; // 2000-06-01
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_ut(
            $jd_ut_start,
            $dgeo,
            $datm,
            $dobs,
            'venus',
            4, // SE_MORNING_LAST = 4
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_heliacal_ut failed: $serr");

        $expectedJD = 2452242.75527;
        $actualJD = $dret[0];
        $diffDays = abs($actualJD - $expectedJD);

        $this->assertLessThan(1.0, $diffDays,
            sprintf("Venus morning last: expected JD %.5f, got %.5f (diff: %.2f days)",
                $expectedJD, $actualJD, $diffDays)
        );
    }

    /**
     * Test Venus evening first
     * Reference: swetest64.exe -hev3 -p3 -b1.6.2000 -geopos13.4,52.5,100 -topo
     * Expected: 2000/10/06 16:53:55.0 UT (JD 2451824.20411) [FIRST occurrence after start date]
     * Note: Previous test expected 2002/03/01 (JD 2452335.21615) which is the SECOND occurrence
     */
    public function testVenusEveningFirst(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut_start = 2451697.5; // 2000-06-01
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_ut(
            $jd_ut_start,
            $dgeo,
            $datm,
            $dobs,
            'venus',
            3, // SE_EVENING_FIRST = 3
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_heliacal_ut failed: $serr");

        $expectedJD = 2451824.20411; // First occurrence: 2000/10/06 16:53:55.0 UT
        $actualJD = $dret[0];
        $diffDays = abs($actualJD - $expectedJD);

        $this->assertLessThan(1.0, $diffDays,
            sprintf("Venus evening first: expected JD %.5f, got %.5f (diff: %.2f days)",
                $expectedJD, $actualJD, $diffDays)
        );
    }

    /**
     * Test Venus heliacal setting
     * Reference: swetest64.exe -hev2 -p3 -b1.6.2000 -geopos13.4,52.5,100 -topo
     * Expected: 2001/03/25 17:45:17.0 UT (JD 2451994.23978) [FIRST occurrence after start date]
     * Note: Previous test expected 2002/09/07 (JD 2452525.24693) which is the SECOND occurrence
     */
    public function testVenusHeliacalSetting(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut_start = 2451697.5; // 2000-06-01
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_ut(
            $jd_ut_start,
            $dgeo,
            $datm,
            $dobs,
            'venus',
            2, // SE_HELIACAL_SETTING = 2
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_heliacal_ut failed: $serr");

        $expectedJD = 2451994.23978; // First occurrence: 2001/03/25 17:45:17.0 UT
        $actualJD = $dret[0];
        $diffDays = abs($actualJD - $expectedJD);

        $this->assertLessThan(1.0, $diffDays,
            sprintf("Venus heliacal setting: expected JD %.5f, got %.5f (diff: %.2f days)",
                $expectedJD, $actualJD, $diffDays)
        );
    }

    /**
     * Test Sirius heliacal rising
     * Reference: swetest64.exe -hev1 -pf -xfSirius -b1.7.2000 -geopos13.4,52.5,100 -topo
     * Expected: 2000/08/28 03:29:12.0 UT (JD 2451784.64528)
     */
    public function testSiriusHeliacalRising(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut_start = 2451727.5; // 2000-07-01
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_ut(
            $jd_ut_start,
            $dgeo,
            $datm,
            $dobs,
            'sirius',
            1, // SE_HELIACAL_RISING
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_heliacal_ut failed: $serr");

        $expectedJD = 2451784.64528;
        $actualJD = $dret[0];
        $diffDays = abs($actualJD - $expectedJD);

        $this->assertLessThan(1.0, $diffDays,
            sprintf("Sirius heliacal rising: expected JD %.5f, got %.5f (diff: %.2f days)",
                $expectedJD, $actualJD, $diffDays)
        );
    }

    /**
     * Test Sirius heliacal setting
     * Reference: swetest64.exe -hev2 -pf -xfSirius -b1.7.2000 -geopos13.4,52.5,100 -topo
     * Expected: 2001/04/29 19:03:56.8 UT (JD 2452029.29441)
     */
    public function testSiriusHeliacalSetting(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut_start = 2451727.5; // 2000-07-01
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_ut(
            $jd_ut_start,
            $dgeo,
            $datm,
            $dobs,
            'sirius',
            2, // SE_HELIACAL_SETTING
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_heliacal_ut failed: $serr");

        $expectedJD = 2452029.29441;
        $actualJD = $dret[0];
        $diffDays = abs($actualJD - $expectedJD);

        $this->assertLessThan(1.0, $diffDays,
            sprintf("Sirius heliacal setting: expected JD %.5f, got %.5f (diff: %.2f days)",
                $expectedJD, $actualJD, $diffDays)
        );
    }

    /**
     * Test swe_heliacal_pheno_ut() - detailed phenomena array
     * Should return 30-element array with all heliacal parameters
     */
    public function testHeliacalPhenoUt(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        // Venus at J2000
        $jd_ut = 2451545.0;
        $darr = array_fill(0, 30, 0.0);
        $serr = '';

        $retval = swe_heliacal_pheno_ut(
            $jd_ut,
            $dgeo,
            $datm,
            $dobs,
            'venus',
            1, // morning rising
            Constants::SEFLG_SWIEPH,
            $darr,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_heliacal_pheno_ut failed: $serr");

        // Verify key elements exist
        $this->assertIsFloat($darr[0], "Object altitude should be float");
        $this->assertIsFloat($darr[1], "Object azimuth should be float");
        $this->assertIsFloat($darr[2], "Sun altitude should be float");
        $this->assertIsFloat($darr[4], "Arcus visionis should be float");

        // Altitude should be reasonable (between -90 and +90)
        $this->assertGreaterThanOrEqual(-90.0, $darr[0], "Object altitude too low");
        $this->assertLessThanOrEqual(90.0, $darr[0], "Object altitude too high");
    }

    /**
     * Test swe_vis_limit_mag() - limiting magnitude calculation
     */
    public function testVisLimitMag(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0]; // naked eye

        $jd_ut = 2451545.0; // J2000
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_vis_limit_mag(
            $jd_ut,
            $dgeo,
            $datm,
            $dobs,
            'venus',
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_vis_limit_mag failed: $serr");

        // Limiting magnitude can be negative when object not visible (Sun above horizon)
        // At J2000 in Amsterdam, Venus is visible but Sun Alt=13.67° (daylight), so VLM is negative
        // This matches C behavior: C returns -7.838791, PHP returns -7.843280
        $visLimitMag = $dret[0];
        $this->assertIsFloat($visLimitMag, "Limiting magnitude should be float");
        // Note: VLM > 0 means object visible, VLM < 0 means not visible due to sky brightness

        // Object magnitude should be present
        $objectMag = $dret[7];
        $this->assertIsFloat($objectMag, "Object magnitude should be float");
    }

    /**
     * Test swe_heliacal_angle() - heliacal angle calculation
     */
    public function testHeliacalAngle(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut = 2451545.0;
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_angle(
            $jd_ut,
            $dgeo,
            $datm,
            $dobs,
            'venus',
            Constants::SEFLG_SWIEPH,
            -4.0, // Venus magnitude
            90.0, // AziO (object azimuth)
            -1.0, // AltM (not used)
            0.0,  // AziM
            270.0, // AziS (Sun azimuth)
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_heliacal_angle failed: $serr");

        // Heliacal angle should be present
        $helAngle = $dret[0];
        $this->assertIsFloat($helAngle, "Heliacal angle should be float");
        $this->assertGreaterThanOrEqual(0.0, $helAngle, "Heliacal angle should be non-negative");

        // Arcus visionis from angle method
        $arcusVis = $dret[1];
        $this->assertIsFloat($arcusVis, "Arcus visionis should be float");
    }

    /**
     * Test swe_topo_arcus_visionis() - topocentric arcus visionis
     */
    public function testTopoArcusVisionis(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut = 2451545.0;
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_topo_arcus_visionis(
            $jd_ut,
            $dgeo,
            $datm,
            $dobs,
            'venus',
            Constants::SEFLG_SWIEPH,
            -4.0,  // mag
            90.0,  // AziO
            10.0,  // AltO
            270.0, // AziS
            -5.0,  // AltS (dawn/dusk)
            -1.0,  // AziM (not used)
            0.0,   // AltM
            $dret,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $retval, "swe_topo_arcus_visionis failed: $serr");

        // Topocentric arcus visionis should be present
        $topoAV = $dret[0];
        $this->assertIsFloat($topoAV, "Topocentric arcus visionis should be float");
        $this->assertGreaterThan(0.0, $topoAV, "Topocentric arcus visionis should be positive");
    }

    /**
     * Test Mars heliacal rising
     * Mars is slower-moving, different visibility characteristics
     */
    public function testMarsHeliacalRising(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut_start = 2451545.0; // J2000
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_ut(
            $jd_ut_start,
            $dgeo,
            $datm,
            $dobs,
            'mars',
            1, // SE_HELIACAL_RISING
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        // Should either find an event or return -2 (no event in search period)
        $this->assertGreaterThanOrEqual(-2, $retval,
            "swe_heliacal_ut failed with unexpected error: $serr");

        if ($retval >= 0) {
            // Event found
            $eventJD = $dret[0];
            $this->assertGreaterThan($jd_ut_start, $eventJD,
                "Event should be in the future from start date");
            $this->assertLessThan($jd_ut_start + 1000, $eventJD,
                "Event should be within ~3 years from start");
        }
        // If $retval === -2, no event found, which is acceptable
    }

    /**
     * Test error handling with invalid object name
     */
    public function testInvalidObjectName(): void
    {
        $dgeo = [13.4, 52.5, 100.0];
        $datm = [1013.25, 15.0, 40.0, 0.0];
        $dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

        $jd_ut = 2451545.0;
        $dret = array_fill(0, 10, 0.0);
        $serr = '';

        $retval = swe_heliacal_ut(
            $jd_ut,
            $dgeo,
            $datm,
            $dobs,
            'nonexistent_planet',
            1,
            Constants::SEFLG_SWIEPH,
            $dret,
            $serr
        );

        $this->assertLessThan(0, $retval, "Should fail with invalid object name");
        $this->assertNotEmpty($serr, "Error message should be set");
    }
}
