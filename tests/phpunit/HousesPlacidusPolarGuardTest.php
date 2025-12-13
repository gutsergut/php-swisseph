<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HousesPlacidusPolarGuardTest extends TestCase
{
    public function testPlacidusFailsAtHighLatitude(): void
    {
        $jd_ut = \swe_julday(2000, 6, 21, 12.0, 1);
        $cusp = $ascmc = [];
        $serr = null;
        // Tromsø ~69.65N
    $cuspSpeed = null; $ascmcSpeed = null;
    $rc = \swe_houses_ex2($jd_ut, 0, 69.65, 18.96, 'P', $cusp, $ascmc, $cuspSpeed, $ascmcSpeed, $serr);
        $this->assertSame(\Swisseph\Constants::SE_ERR, $rc);
        $this->assertNotEmpty($serr, 'Error message should be set');
    }
}
