<?php
/**
 * Smoke test - быстрая проверка основной функциональности
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';

use Swe\SwissEph;

echo "=== SMOKE TEST ===\n\n";

$tests = [
    'Версия библиотеки' => function() {
        $version = swe_version();
        assert(is_string($version) && strlen($version) > 0);
        return "OK: $version";
    },

    'Преобразование даты' => function() {
        $jd = swe_julday(2000, 1, 1, 12.0, SE_GREG_CAL);
        $expected = 2451545.0;
        $diff = abs($jd - $expected);
        assert($diff < 0.001, "JD mismatch: $jd vs $expected");
        return sprintf("OK: JD=%.6f", $jd);
    },

    'Расчёт Солнца' => function() {
        $jd = 2451545.0; // J2000
        $xx = [];
        $serr = '';
        $ret = swe_calc($jd, SE_SUN, SEFLG_SWIEPH, $xx, $serr);
        assert($ret >= 0, "Error: $serr");
        assert(count($xx) >= 6);
        return sprintf("OK: Sun lon=%.4f°", $xx[0]);
    },

    'Расчёт Луны' => function() {
        $jd = 2451545.0;
        $xx = [];
        $serr = '';
        $ret = swe_calc($jd, SE_MOON, SEFLG_SWIEPH, $xx, $serr);
        assert($ret >= 0, "Error: $serr");
        assert(count($xx) >= 6);
        return sprintf("OK: Moon lon=%.4f°", $xx[0]);
    },

    'Система домов Placidus' => function() {
        $jd = 2451545.0;
        $cusp = [];
        $ascmc = [];
        $ret = swe_houses($jd, 51.5, -0.13, 'P', $cusp, $ascmc);
        assert($ret === SE_OK);
        assert(count($cusp) === 13);
        assert(count($ascmc) === 10);
        return sprintf("OK: ASC=%.2f°, MC=%.2f°", $ascmc[0], $ascmc[1]);
    },

    'Аянамша Lahiri' => function() {
        $jd = 2451545.0;
        swe_set_sid_mode(SE_SIDM_LAHIRI, 0, 0);
        $aya = swe_get_ayanamsa($jd);
        assert(is_float($aya));
        assert($aya > 0 && $aya < 360);
        return sprintf("OK: Lahiri ayanamsa=%.4f°", $aya);
    },

    'Восход Солнца' => function() {
        $jd = 2451545.0;
        $geolon = 0.0;
        $geolat = 51.5;
        $alt = 0.0;
        $tret = 0.0;
        $serr = '';
        $ret = swe_rise_trans($jd, SE_SUN, '', SEFLG_SWIEPH, SE_CALC_RISE,
                             $geolon, $geolat, $alt, 1013.25, 15.0, $tret, $serr);
        assert($ret >= 0, "Error: $serr");
        return sprintf("OK: Rise JD=%.6f", $tret);
    },
];

$passed = 0;
$failed = 0;

foreach ($tests as $name => $test) {
    echo "[$name] ";
    try {
        $result = $test();
        echo "$result\n";
        $passed++;
    } catch (Throwable $e) {
        echo "✗ FAILED: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\n";
echo str_repeat('-', 50) . "\n";
echo "Пройдено: $passed, Провалено: $failed\n";

exit($failed > 0 ? 1 : 0);
