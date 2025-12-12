<?php

declare(strict_types=1);

/**
 * Test swe_sol_eclipse_how() implementation
 *
 * Tests the function that calculates solar eclipse attributes
 * at a specific time and location.
 */

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

require_once __DIR__ . '/../vendor/autoload.php';

class SolarEclipseHowTest extends TestCase
{
    private const EPHE_PATH = __DIR__ . '/../../eph/ephe';
    
    protected function setUp(): void
    {
        swe_set_ephe_path(self::EPHE_PATH);
    }

    /**
     * Test solar eclipse attributes at Dallas during 2024-04-08 total eclipse
     * 
     * This tests swe_sol_eclipse_how() which calculates:
     * - Eclipse type at location
     * - Magnitude and obscuration
     * - Sun's azimuth and altitude
     * - Saros series information
     */
    public function testSolarEclipseHow2024Dallas(): void
    {
        // Location: Dallas, Texas
        $geopos = [
            -96.8,  // longitude (west is negative)
            32.8,   // latitude
            0.0     // altitude in meters
        ];
        
        // Time of maximum eclipse in Dallas: 2024-04-08 18:42:47 UT
        // JD ≈ 2460409.279721
        $jd_ut = 2460409.279721;
        
        $attr = array_fill(0, 20, 0.0);
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH;
        
        $retflag = swe_sol_eclipse_how($jd_ut, $flags, $geopos, $attr, $serr);
        
        echo "\n";
        echo "=== swe_sol_eclipse_how() Test ===\n";
        echo "Time: JD {$jd_ut} (2024-04-08 18:42:47 UT)\n";
        echo "Location: Dallas, TX ({$geopos[0]}°, {$geopos[1]}°, {$geopos[2]}m)\n\n";
        
        // Should return eclipse flags
        $this->assertNotEquals(Constants::SE_ERR, $retflag, "Error: $serr");
        $this->assertGreaterThan(0, $retflag, "Eclipse should be visible");
        
        // Check eclipse type
        $isTotal = ($retflag & Constants::SE_ECL_TOTAL) !== 0;
        $isAnnular = ($retflag & Constants::SE_ECL_ANNULAR) !== 0;
        $isPartial = ($retflag & Constants::SE_ECL_PARTIAL) !== 0;
        $isVisible = ($retflag & Constants::SE_ECL_VISIBLE) !== 0;
        $isCentral = ($retflag & Constants::SE_ECL_CENTRAL) !== 0;
        $isNonCentral = ($retflag & Constants::SE_ECL_NONCENTRAL) !== 0;
        
        echo "Eclipse Type:\n";
        if ($isTotal) echo "  ✅ TOTAL\n";
        if ($isAnnular) echo "  ✅ ANNULAR\n";
        if ($isPartial) echo "  ✅ PARTIAL\n";
        if ($isVisible) echo "  ✅ VISIBLE\n";
        if ($isCentral) echo "  ✅ CENTRAL\n";
        if ($isNonCentral) echo "  ✅ NONCENTRAL\n";
        
        // Dallas sees a total eclipse (though non-central - path of totality passes nearby)
        $this->assertTrue($isTotal, "Eclipse should be TOTAL at Dallas");
        $this->assertTrue($isVisible, "Eclipse should be VISIBLE");
        
        echo "\n";
        echo "Eclipse Attributes:\n";
        echo sprintf("  Magnitude (IMCCE):     %.4f\n", $attr[0]);
        echo sprintf("  Diameter ratio:        %.4f\n", $attr[1]);
        echo sprintf("  Obscuration:           %.4f\n", $attr[2]);
        echo sprintf("  Core shadow diam:      %.2f km\n", $attr[3]);
        echo sprintf("  Sun azimuth:           %.2f°\n", $attr[4]);
        echo sprintf("  Sun true altitude:     %.2f°\n", $attr[5]);
        echo sprintf("  Sun apparent altitude: %.2f°\n", $attr[6]);
        echo sprintf("  Elongation:            %.4f°\n", $attr[7]);
        echo sprintf("  Magnitude (NASA):      %.4f\n", $attr[8]);
        echo sprintf("  Saros series:          %.0f\n", $attr[9]);
        echo sprintf("  Saros member:          %.0f\n", $attr[10]);
        
        // Magnitude should be > 1.0 for total eclipse
        $this->assertGreaterThan(1.0, $attr[0], "Magnitude should be > 1.0 for total eclipse");
        $this->assertGreaterThan(1.0, $attr[1], "Diameter ratio should be > 1.0 (Moon larger than Sun)");
        
        // Sun should be well above horizon
        $this->assertGreaterThan(0, $attr[5], "Sun should be above horizon");
        $this->assertGreaterThan(0, $attr[6], "Sun should be above horizon (apparent)");
        
        // Elongation should be very small (Sun-Moon close together)
        $this->assertLessThan(1.0, $attr[7], "Elongation should be < 1° during eclipse");
        
        // Saros series 139 for April 8, 2024 eclipse
        $this->assertEquals(139, $attr[9], "Saros series should be 139");
        $this->assertEquals(30, $attr[10], "Saros member should be 30");
        
        echo "\n";
        echo "✅ All checks passed!\n";
    }

    /**
     * Test no eclipse case
     * 
     * Tests swe_sol_eclipse_how() at a time when no eclipse is occurring
     */
    public function testNoEclipse(): void
    {
        // Location: Dallas, Texas
        $geopos = [-96.8, 32.8, 0.0];
        
        // Random time: 2025-01-15 12:00 UT (no eclipse)
        $jd_ut = swe_julday(2025, 1, 15, 12.0, Constants::SE_GREG_CAL);
        
        $attr = array_fill(0, 20, 0.0);
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH;
        
        $retflag = swe_sol_eclipse_how($jd_ut, $flags, $geopos, $attr, $serr);
        
        echo "\n";
        echo "=== No Eclipse Test ===\n";
        echo "Time: JD {$jd_ut} (2025-01-15 12:00 UT)\n";
        echo "Result: retflag = {$retflag}\n";
        
        // Should return 0 (no eclipse)
        $this->assertEquals(0, $retflag, "Should return 0 when no eclipse");
        
        // Magnitude and obscuration should be 0
        $this->assertEquals(0.0, $attr[0], "Magnitude should be 0");
        $this->assertEquals(0.0, $attr[2], "Obscuration should be 0");
        
        echo "✅ Correctly returns 0 for no eclipse\n";
    }

    /**
     * Test eclipse at high altitude location
     * 
     * Tests that altitude above sea level is properly handled
     */
    public function testHighAltitude(): void
    {
        // Location: La Paz, Bolivia (high altitude city)
        $geopos = [
            -68.15,  // longitude
            -16.50,  // latitude
            3640.0   // altitude: 3640m above sea level
        ];
        
        // Use 2024-04-08 eclipse time
        $jd_ut = 2460409.279721;
        
        $attr = array_fill(0, 20, 0.0);
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH;
        
        $retflag = swe_sol_eclipse_how($jd_ut, $flags, $geopos, $attr, $serr);
        
        echo "\n";
        echo "=== High Altitude Test ===\n";
        echo "Location: La Paz, Bolivia ({$geopos[2]}m altitude)\n";
        
        // Should not error due to altitude
        $this->assertNotEquals(Constants::SE_ERR, $retflag, "Should handle high altitude: $serr");
        
        echo "✅ High altitude location handled correctly\n";
    }
}
