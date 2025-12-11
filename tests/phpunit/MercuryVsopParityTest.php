<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;
use Swisseph\Swe\Planets\Vsop87Strategy;

final class MercuryVsopParityTest extends TestCase
{
    private static function ensureEphe(): void
    {
        if (!defined('SWISSEPH_EPHE_SET') || SWISSEPH_EPHE_SET === false) {
            // Абсолютный путь из запроса пользователя: с-swisseph\swisseph\ephe относительно корня репо
            $cand = realpath(__DIR__ . '/../../../с-swisseph/swisseph/ephe');
            if ($cand && is_dir($cand) && is_file($cand . DIRECTORY_SEPARATOR . 'sepl_18.se1')) {
                swe_set_ephe_path($cand);
                define('SWISSEPH_EPHE_SET', true);
            }
        }
    }

    /** @return array<int,array{float,int}> */
    public static function jdFlagProvider(): array
    {
        $baseFlags = [0, Constants::SEFLG_SPEED, Constants::SEFLG_EQUATORIAL, Constants::SEFLG_XYZ|Constants::SEFLG_SPEED];
        $jds = [2451545.0, 2462502.5, 2415020.5];
        $cases = [];
        foreach ($jds as $jd) {
            foreach ($baseFlags as $fl) {
                $cases[] = [$jd, $fl];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider jdFlagProvider
     */
    public function testParity(float $jd_tt, int $flags): void
    {
        self::ensureEphe();
        require_once __DIR__ . '/../../src/functions.php';

        $ipl = Constants::SE_MERCURY;

        // 1) Фасад с VSOP87 флагом
        $xx1 = []; $serr1 = null;
        $rc1 = swe_calc($jd_tt, $ipl, $flags | Constants::SEFLG_VSOP87, $xx1, $serr1);
        $this->assertGreaterThanOrEqual(0, $rc1, 'Facade VSOP must succeed: ' . ($serr1 ?? ''));

        // 2) Прямой вызов стратегии VSOP
        $st = new Vsop87Strategy();
        $res = $st->compute($jd_tt, $ipl, $flags | Constants::SEFLG_VSOP87);
        $this->assertGreaterThanOrEqual(0, $res->retc, 'Strategy VSOP must succeed: ' . ($res->serr ?? ''));

        // 3) Сравнение (жёсткое, в пределах машинного eps на даблах)
        $this->assertCount(6, $xx1);
        $this->assertCount(6, $res->x);
        for ($i=0; $i<6; $i++) {
            $this->assertEqualsWithDelta($res->x[$i], $xx1[$i], 1e-12, "Mismatch at idx=$i (flags=$flags)");
        }
    }
}
