<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HousesPlacidusTest extends TestCase
{
    public function testPlacidusCuspsShapeAndOpposites(): void
    {
        $jd_ut = \swe_julday(2000, 1, 1, 12.0, 1);
        $cusp = $ascmc = [];
        $rc = \swe_houses($jd_ut, 51.4779, 0.0, 'P', $cusp, $ascmc);
        $this->assertSame(0, $rc);
        $this->assertCount(13, $cusp);
        $this->assertCount(10, $ascmc);
        // Opposite cusps: 1-7, 2-8, 3-9, 4-10, 5-11, 6-12
        $pairs = [[1,7],[2,8],[3,9],[4,10],[5,11],[6,12]];
        foreach ($pairs as [$a,$b]) {
            $d = \Swisseph\Math::angleDiffDeg($cusp[$b], $cusp[$a]);
            $d = $d < 0 ? $d + 360.0 : $d;
            $this->assertEqualsWithDelta(180.0, $d, 1.0, "Cusps $a-$b not opposite enough");
        }
        // Asc/Desc and MC/IC in proper ranges
        $this->assertGreaterThanOrEqual(0.0, $ascmc[0]);
        $this->assertLessThan(360.0, $ascmc[0]);
        $this->assertGreaterThanOrEqual(0.0, $ascmc[1]);
        $this->assertLessThan(360.0, $ascmc[1]);
    }
}
