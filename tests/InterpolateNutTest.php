<?php

/**
 * Test swe_set_interpolate_nut()
 *
 * Full port verification from swephlib.c:3558-3576.
 *
 * Tests nutation interpolation control:
 * - Flag state changes (false→true, true→false)
 * - Cache reset behavior
 * - Early return optimization when flag unchanged
 */

require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

final class InterpolateNutTest extends TestCase
{
    private \Swisseph\SwephFile\SwedState $swed;

    protected function setUp(): void
    {
        $this->swed = \Swisseph\SwephFile\SwedState::getInstance();
    }

    public function testDefaultState(): void
    {
        // Default should be FALSE
        $this->assertFalse($this->swed->do_interpolate_nut, 'Default interpolation flag should be FALSE');
    }

    public function testEnableInterpolation(): void
    {
        // Initially false
        $this->swed->do_interpolate_nut = false;

        // Enable interpolation
        swe_set_interpolate_nut(true);

        $this->assertTrue($this->swed->do_interpolate_nut, 'Flag should be TRUE after enabling');

        // Verify cache reset
        $this->assertEquals(0.0, $this->swed->interpol->tjd_nut0, 'tjd_nut0 should be reset to 0');
        $this->assertEquals(0.0, $this->swed->interpol->tjd_nut2, 'tjd_nut2 should be reset to 0');
        $this->assertEquals(0.0, $this->swed->interpol->nut_dpsi0, 'nut_dpsi0 should be reset to 0');
        $this->assertEquals(0.0, $this->swed->interpol->nut_dpsi1, 'nut_dpsi1 should be reset to 0');
        $this->assertEquals(0.0, $this->swed->interpol->nut_dpsi2, 'nut_dpsi2 should be reset to 0');
        $this->assertEquals(0.0, $this->swed->interpol->nut_deps0, 'nut_deps0 should be reset to 0');
        $this->assertEquals(0.0, $this->swed->interpol->nut_deps1, 'nut_deps1 should be reset to 0');
        $this->assertEquals(0.0, $this->swed->interpol->nut_deps2, 'nut_deps2 should be reset to 0');
    }

    public function testDisableInterpolation(): void
    {
        // Set to true
        $this->swed->do_interpolate_nut = true;

        // Disable interpolation
        swe_set_interpolate_nut(false);

        $this->assertFalse($this->swed->do_interpolate_nut, 'Flag should be FALSE after disabling');

        // Verify cache reset
        $this->assertEquals(0.0, $this->swed->interpol->tjd_nut0, 'Cache should be reset on flag change');
    }

    public function testCacheResetOnFlagChange(): void
    {
        // Start with false
        $this->swed->do_interpolate_nut = false;

        // Populate cache with non-zero values
        $this->swed->interpol->tjd_nut0 = 2451545.0;
        $this->swed->interpol->tjd_nut2 = 2451546.0;
        $this->swed->interpol->nut_dpsi0 = 0.001;
        $this->swed->interpol->nut_dpsi1 = 0.002;
        $this->swed->interpol->nut_dpsi2 = 0.003;
        $this->swed->interpol->nut_deps0 = 0.004;
        $this->swed->interpol->nut_deps1 = 0.005;
        $this->swed->interpol->nut_deps2 = 0.006;

        // Change flag
        swe_set_interpolate_nut(true);

        // All cache values should be reset to 0
        $this->assertEquals(0.0, $this->swed->interpol->tjd_nut0);
        $this->assertEquals(0.0, $this->swed->interpol->tjd_nut2);
        $this->assertEquals(0.0, $this->swed->interpol->nut_dpsi0);
        $this->assertEquals(0.0, $this->swed->interpol->nut_dpsi1);
        $this->assertEquals(0.0, $this->swed->interpol->nut_dpsi2);
        $this->assertEquals(0.0, $this->swed->interpol->nut_deps0);
        $this->assertEquals(0.0, $this->swed->interpol->nut_deps1);
        $this->assertEquals(0.0, $this->swed->interpol->nut_deps2);
    }

    public function testNoActionWhenFlagUnchanged(): void
    {
        // Set flag to true
        $this->swed->do_interpolate_nut = true;

        // Populate cache
        $this->swed->interpol->tjd_nut0 = 2451545.0;
        $this->swed->interpol->nut_dpsi0 = 0.001;

        // Call with same value (early return should prevent cache reset)
        swe_set_interpolate_nut(true);

        // Cache should NOT be reset
        $this->assertEquals(2451545.0, $this->swed->interpol->tjd_nut0, 'Cache should be preserved when flag unchanged');
        $this->assertEquals(0.001, $this->swed->interpol->nut_dpsi0, 'Cache should be preserved when flag unchanged');
    }

    public function testToggleMultipleTimes(): void
    {
        // Start false
        $this->swed->do_interpolate_nut = false;

        // Toggle true
        swe_set_interpolate_nut(true);
        $this->assertTrue($this->swed->do_interpolate_nut);

        // Toggle false
        swe_set_interpolate_nut(false);
        $this->assertFalse($this->swed->do_interpolate_nut);

        // Toggle true again
        swe_set_interpolate_nut(true);
        $this->assertTrue($this->swed->do_interpolate_nut);

        // Verify cache is still reset
        $this->assertEquals(0.0, $this->swed->interpol->tjd_nut0);
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    echo "Running InterpolateNutTest...\n\n";

    $swed = \Swisseph\SwephFile\SwedState::getInstance();

    try {
        // Test 1: Default state
        if ($swed->do_interpolate_nut === false) {
            echo "✓ testDefaultState\n";
        } else {
            throw new Exception("Default interpolation flag should be FALSE");
        }

        // Test 2: Enable interpolation
        $swed->do_interpolate_nut = false;
        swe_set_interpolate_nut(true);

        if ($swed->do_interpolate_nut !== true) {
            throw new Exception("Flag should be TRUE after enabling");
        }
        if ($swed->interpol->tjd_nut0 !== 0.0 || $swed->interpol->nut_dpsi0 !== 0.0) {
            throw new Exception("Cache should be reset after enabling");
        }
        echo "✓ testEnableInterpolation\n";

        // Test 3: Disable interpolation
        $swed->do_interpolate_nut = true;
        swe_set_interpolate_nut(false);

        if ($swed->do_interpolate_nut !== false) {
            throw new Exception("Flag should be FALSE after disabling");
        }
        if ($swed->interpol->tjd_nut0 !== 0.0) {
            throw new Exception("Cache should be reset on flag change");
        }
        echo "✓ testDisableInterpolation\n";

        // Test 4: Cache reset on flag change
        $swed->do_interpolate_nut = false;
        $swed->interpol->tjd_nut0 = 2451545.0;
        $swed->interpol->nut_dpsi0 = 0.001;

        swe_set_interpolate_nut(true);

        if ($swed->interpol->tjd_nut0 !== 0.0 || $swed->interpol->nut_dpsi0 !== 0.0) {
            throw new Exception("Cache should be reset when flag changes");
        }
        echo "✓ testCacheResetOnFlagChange\n";

        // Test 5: No action when flag unchanged
        $swed->do_interpolate_nut = true;
        $swed->interpol->tjd_nut0 = 2451545.0;
        $swed->interpol->nut_dpsi0 = 0.001;

        swe_set_interpolate_nut(true);

        if ($swed->interpol->tjd_nut0 !== 2451545.0 || $swed->interpol->nut_dpsi0 !== 0.001) {
            throw new Exception("Cache should be preserved when flag unchanged");
        }
        echo "✓ testNoActionWhenFlagUnchanged\n";

        // Test 6: Toggle multiple times
        $swed->do_interpolate_nut = false;

        swe_set_interpolate_nut(true);
        if ($swed->do_interpolate_nut !== true) {
            throw new Exception("First toggle failed");
        }

        swe_set_interpolate_nut(false);
        if ($swed->do_interpolate_nut !== false) {
            throw new Exception("Second toggle failed");
        }

        swe_set_interpolate_nut(true);
        if ($swed->do_interpolate_nut !== true || $swed->interpol->tjd_nut0 !== 0.0) {
            throw new Exception("Third toggle failed or cache not reset");
        }
        echo "✓ testToggleMultipleTimes\n";

        echo "\nAll tests passed! ✓\n";
    } catch (\Exception $e) {
        echo "\n✗ Test failed: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
}
