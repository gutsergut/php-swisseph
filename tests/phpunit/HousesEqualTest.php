<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HousesEqualTest extends TestCase
{
    public function testEqualHousesAscAndCuspsShape(): void
    {
        // 2000-01-01 12:00:00 UT, Greenwich
    $jd_ut = \swe_julday(2000, 1, 1, 12.0, 1);
        $cusp = $ascmc = [];
    $rc = \swe_houses($jd_ut, 51.4779, 0.0, 'E', $cusp, $ascmc);
        $this->assertSame(0, $rc);
        $this->assertCount(13, $cusp);
        $this->assertCount(10, $ascmc);
        // Cusps spaced by ~30 degrees (Equal system)
        for ($i = 2; $i <= 12; $i++) {
            $d = \Swisseph\Math::angleDiffDeg($cusp[$i], $cusp[$i - 1]);
            $this->assertEqualsWithDelta(30.0, $d < 0 ? $d + 360.0 : $d, 1e-6);
        }
        // Asc in [0,360), MC in [0,360)
        $this->assertGreaterThanOrEqual(0.0, $ascmc[0]);
        $this->assertLessThan(360.0, $ascmc[0]);
        $this->assertGreaterThanOrEqual(0.0, $ascmc[1]);
        $this->assertLessThan(360.0, $ascmc[1]);
    }
}
