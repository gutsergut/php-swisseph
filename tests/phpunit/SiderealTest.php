<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Sidereal;

final class SiderealTest extends TestCase
{
    public function testGmstJ2000Noon(): void
    {
        $hours = Sidereal::gmstHoursFromJdUt(2451545.0);
        // GMST expected around 18.697374558 hours for JD=2451545.0
        $this->assertEqualsWithDelta(18.697374558, $hours, 1e-6);
    }
}
