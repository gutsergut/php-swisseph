<?php

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use Swisseph\Precession;
use Swisseph\Constants;

/**
 * Unit tests for Precession class
 * Tests different precession models and their accuracy
 */
class PrecessionTest extends TestCase
{
    private const J2000 = 2451545.0;

    /**
     * Test that precession preserves vector length
     */
    public function testPrecessionPreservesLength(): void
    {
        $x = [0.6, 0.8, 0.0];
        $lengthBefore = sqrt($x[0]**2 + $x[1]**2 + $x[2]**2);

        $jdTo = 2460676.5; // 2025-01-11
        Precession::precess($x, $jdTo, 0, Constants::J2000_TO_J);

        $lengthAfter = sqrt($x[0]**2 + $x[1]**2 + $x[2]**2);

        $this->assertEqualsWithDelta($lengthBefore, $lengthAfter, 1e-10,
            'Precession should preserve vector length (rotation only)');
    }

    /**
     * Test round-trip precession (forward and back should equal original)
     */
    public function testPrecessionRoundTrip(): void
    {
        $jdTo = 2460676.5;

        $original = [0.8, 0.5, 0.3];
        $normalized = sqrt($original[0]**2 + $original[1]**2 + $original[2]**2);

        // Normalize
        $x = [
            $original[0] / $normalized,
            $original[1] / $normalized,
            $original[2] / $normalized
        ];

        // Forward precession: J2000 -> date
        Precession::precess($x, $jdTo, 0, Constants::J2000_TO_J);

        // Back precession: date -> J2000
        Precession::precess($x, $jdTo, 0, Constants::J_TO_J2000);

        // Should be close to original
        $this->assertEqualsWithDelta($original[0] / $normalized, $x[0], 1e-10);
        $this->assertEqualsWithDelta($original[1] / $normalized, $x[1], 1e-10);
        $this->assertEqualsWithDelta($original[2] / $normalized, $x[2], 1e-10);
    }

    /**
     * Test IAU 1976 vs IAU 2006 precession models
     */
    public function testDifferentPrecessionModels(): void
    {
        $jdTo = 2460676.5;

        $x2006 = [1.0, 0.0, 0.0];
        Precession::precess($x2006, $jdTo, 0, Constants::J2000_TO_J, Precession::SEMOD_PREC_IAU_2006);

        $x1976 = [1.0, 0.0, 0.0];
        Precession::precess($x1976, $jdTo, 0, Constants::J2000_TO_J, Precession::SEMOD_PREC_IAU_1976);

        // IAU 2006 and IAU 1976 should give similar results
        $diff = sqrt(pow($x2006[0] - $x1976[0], 2) +
                    pow($x2006[1] - $x1976[1], 2) +
                    pow($x2006[2] - $x1976[2], 2));

        // Difference should be small but non-zero
        $this->assertLessThan(0.001, $diff, 'IAU 2006 and 1976 should be similar');
    }

    /**
     * Test Newcomb precession model
     */
    public function testNewcombPrecession(): void
    {
        $jdTo = 2460676.5;

        $xNewcomb = [1.0, 0.0, 0.0];
        Precession::precess($xNewcomb, $jdTo, 0, Constants::J2000_TO_J, Precession::SEMOD_PREC_NEWCOMB);

        $xIAU2006 = [1.0, 0.0, 0.0];
        Precession::precess($xIAU2006, $jdTo, 0, Constants::J2000_TO_J, Precession::SEMOD_PREC_IAU_2006);

        // Both should preserve length
        $lenNewcomb = sqrt($xNewcomb[0]**2 + $xNewcomb[1]**2 + $xNewcomb[2]**2);
        $lenIAU = sqrt($xIAU2006[0]**2 + $xIAU2006[1]**2 + $xIAU2006[2]**2);

        $this->assertEqualsWithDelta(1.0, $lenNewcomb, 1e-10);
        $this->assertEqualsWithDelta(1.0, $lenIAU, 1e-10);
    }

    /**
     * Test precession creates rotation (vector should move)
     */
    public function testPrecessionCausesRotation(): void
    {
        $jdTo = 2460676.5; // 25 years from J2000

        $x = [1.0, 0.0, 0.0];
        $original = $x;

        Precession::precess($x, $jdTo, 0, Constants::J2000_TO_J);

        // Vector should have changed
        $changed = ($x[0] !== $original[0]) || ($x[1] !== $original[1]) || ($x[2] !== $original[2]);
        $this->assertTrue($changed, 'Precession over 25 years should change coordinates');
    }
}
