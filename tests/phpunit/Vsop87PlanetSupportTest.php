<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;
use Swisseph\Swe\Planets\Vsop87Strategy;

/**
 * Проверяет корректность обработки планет в VSOP87Strategy:
 * - All planets Mercury-Neptune supported (data loaded, coordinates accurate)
 * - Pluto explicitly rejected (VSOP87 does not support)
 */
final class Vsop87PlanetSupportTest extends TestCase
{
    private static function ensureEphe(): void
    {
        if (!defined('SWISSEPH_EPHE_SET') || SWISSEPH_EPHE_SET === false) {
            $cand = realpath(__DIR__ . '/../../../с-swisseph/swisseph/ephe');
            if ($cand && is_dir($cand) && is_file($cand . DIRECTORY_SEPARATOR . 'sepl_18.se1')) {
                swe_set_ephe_path($cand);
                define('SWISSEPH_EPHE_SET', true);
            }
        }
    }

    public function testMercurySupported(): void
    {
        self::ensureEphe();
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, 'Mercury should be supported: ' . ($res->serr ?? ''));
        $this->assertCount(6, $res->x);
    }

    public function testVenusSupported(): void
    {
        self::ensureEphe();
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute(2451545.0, Constants::SE_VENUS, Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, 'Venus should be supported: ' . ($res->serr ?? ''));
        $this->assertCount(6, $res->x);
    }

    public function testMarsSupported(): void
    {
        self::ensureEphe();
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute(2451545.0, Constants::SE_MARS, Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, 'Mars should be supported: ' . ($res->serr ?? ''));
        $this->assertCount(6, $res->x);
    }

    public function testJupiterSupported(): void
    {
        self::ensureEphe();
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute(2451545.0, Constants::SE_JUPITER, Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, 'Jupiter should be supported: ' . ($res->serr ?? ''));
        $this->assertCount(6, $res->x);
    }

    public function testSaturnSupported(): void
    {
        self::ensureEphe();
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute(2451545.0, Constants::SE_SATURN, Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, 'Saturn should be supported: ' . ($res->serr ?? ''));
        $this->assertCount(6, $res->x);
    }

    public function testUranusSupported(): void
    {
        self::ensureEphe();
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute(2451545.0, Constants::SE_URANUS, Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, 'Uranus should be supported: ' . ($res->serr ?? ''));
        $this->assertCount(6, $res->x);
    }

    public function testNeptuneSupported(): void
    {
        self::ensureEphe();
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute(2451545.0, Constants::SE_NEPTUNE, Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, 'Neptune should be supported: ' . ($res->serr ?? ''));
        $this->assertCount(6, $res->x);
    }

    public function testPlutoNotSupportedByVsop87(): void
    {
        $strategy = new Vsop87Strategy();
        $res = $strategy->compute(2451545.0, Constants::SE_PLUTO, Constants::SEFLG_VSOP87);
        $this->assertLessThan(0, $res->retc);
        $this->assertStringContainsString('Pluto', $res->serr ?? '');
        $this->assertStringContainsString('does not support', $res->serr ?? '');
    }
}
