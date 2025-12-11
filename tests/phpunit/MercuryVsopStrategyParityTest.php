<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;
use Swisseph\Swe\Planets\Vsop87Strategy;
use Swisseph\Swe\Functions\PlanetsFunctions;

final class MercuryVsopStrategyParityTest extends TestCase
{
    /**
     * @dataProvider flagsProvider
     */
    public function testParity(float $jd_tt, int $iflag): void
    {
        if (defined('SWISSEPH_EPHE_SET') && SWISSEPH_EPHE_SET === false) {
            $this->markTestSkipped('Swiss Ephemeris planet files not available (sepl_18.se1)');
        }
        // Прямой вызов стратегии
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute($jd_tt, Constants::SE_MERCURY, $iflag | Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, $res->serr ?? 'strategy err');
        $xs = $res->x;

        // Через фасад (тот же набор флагов)
        $xx = [];
        $serr = null;
        $rc = PlanetsFunctions::calc($jd_tt, Constants::SE_MERCURY, $iflag | Constants::SEFLG_VSOP87, $xx, $serr);
        $this->assertGreaterThanOrEqual(0, $rc, $serr ?? 'facade err');

        // Сравнение с высокой точностью
        for ($i=0;$i<6;$i++) {
            $this->assertEqualsWithDelta($xs[$i], $xx[$i], 1e-12, "idx=$i");
        }
    }

    public static function flagsProvider(): array
    {
        $epochs = [2451545.0, 2455197.5, 2462502.5];
        $flagSets = [
            0,
            Constants::SEFLG_SPEED,
            Constants::SEFLG_EQUATORIAL,
            Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED,
            Constants::SEFLG_XYZ | Constants::SEFLG_SPEED,
        ];
        $out = [];
        foreach ($epochs as $jd) {
            foreach ($flagSets as $f) {
                $out[] = [$jd, $f];
            }
        }
        return $out;
    }
}
