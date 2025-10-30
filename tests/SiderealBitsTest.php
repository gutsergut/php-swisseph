<?php

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

/**
 * Unit tests for SE_SIDBIT_* sidereal option constants
 */
class SiderealBitsTest extends TestCase
{
    /**
     * Test that all SE_SIDBIT_* constants are defined with correct values
     */
    public function testSiderealBitConstants(): void
    {
        // From swephexp.h
        $this->assertEquals(256, Constants::SE_SIDBITS);
        $this->assertEquals(256, Constants::SE_SIDBIT_ECL_T0);
        $this->assertEquals(512, Constants::SE_SIDBIT_SSY_PLANE);
        $this->assertEquals(1024, Constants::SE_SIDBIT_USER_UT);
        $this->assertEquals(2048, Constants::SE_SIDBIT_ECL_DATE);
        $this->assertEquals(4096, Constants::SE_SIDBIT_NO_PREC_OFFSET);
        $this->assertEquals(8192, Constants::SE_SIDBIT_PREC_ORIG);
    }

    /**
     * Test that sidereal bits are powers of 2 (can be combined with bitwise OR)
     */
    public function testSiderealBitsArePowersOfTwo(): void
    {
        $bits = [
            Constants::SE_SIDBIT_ECL_T0,
            Constants::SE_SIDBIT_SSY_PLANE,
            Constants::SE_SIDBIT_USER_UT,
            Constants::SE_SIDBIT_ECL_DATE,
            Constants::SE_SIDBIT_NO_PREC_OFFSET,
            Constants::SE_SIDBIT_PREC_ORIG,
        ];

        foreach ($bits as $bit) {
            // Each bit should be a power of 2
            $this->assertEquals(1, popcount($bit), "Bit value {$bit} is not a power of 2");
        }
    }

    /**
     * Test that sidereal bits can be combined
     */
    public function testSiderealBitsCombination(): void
    {
        // Combine ECL_T0 and PREC_ORIG
        $combined = Constants::SE_SIDBIT_ECL_T0 | Constants::SE_SIDBIT_PREC_ORIG;
        $this->assertEquals(256 + 8192, $combined);

        // Test individual bits can be extracted
        $this->assertTrue(($combined & Constants::SE_SIDBIT_ECL_T0) !== 0);
        $this->assertTrue(($combined & Constants::SE_SIDBIT_PREC_ORIG) !== 0);
        $this->assertFalse(($combined & Constants::SE_SIDBIT_SSY_PLANE) !== 0);
    }
}

/**
 * Count number of 1 bits in an integer
 */
function popcount(int $n): int
{
    $count = 0;
    while ($n > 0) {
        $count += $n & 1;
        $n >>= 1;
    }
    return $count;
}
