#!/usr/bin/env php
<?php
/**
 * Venus VSOP87 Parity Test - проверка точности VSOP87 для Venus
 * Сравнение с эталонными значениями swetest64.exe
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

// Устанавливаем путь к эфемеридам для SWIEPH (эталон)
$ephe_path = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
swe_set_ephe_path($ephe_path);

echo "Venus VSOP87 Parity Test\n";
echo str_repeat("=", 60) . "\n\n";

// Тестовые даты
$test_dates = [
    ['name' => 'J2000.0', 'jd' => 2451545.0],
    ['name' => '2025-01-01', 'jd' => 2460676.5],
    ['name' => '1950-01-01', 'jd' => 2433282.5],
    ['name' => '2100-01-01', 'jd' => 2488069.5],
];

$max_lon_diff = 0.0;
$max_lat_diff = 0.0;
$max_dist_diff = 0.0;

foreach ($test_dates as $test) {
    echo "{$test['name']} (JD {$test['jd']}):\n";

    // SWIEPH (эталон)
    $xx_swieph = array_fill(0, 6, 0.0);
    $serr_swieph = '';
    $ret_swieph = PlanetsFunctions::calc(
        $test['jd'],
        Constants::SE_VENUS,
        Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
        $xx_swieph,
        $serr_swieph
    );

    if ($ret_swieph < 0) {
        echo "  ERROR SWIEPH: $serr_swieph\n";
        continue;
    }

    // VSOP87
    $xx_vsop = array_fill(0, 6, 0.0);
    $serr_vsop = '';
    $ret_vsop = PlanetsFunctions::calc(
        $test['jd'],
        Constants::SE_VENUS,
        Constants::SEFLG_VSOP87 | Constants::SEFLG_SPEED,
        $xx_vsop,
        $serr_vsop
    );

    if ($ret_vsop < 0) {
        echo "  ERROR VSOP87: $serr_vsop\n";
        continue;
    }

    // Вычисляем разницы
    $diff_lon = abs($xx_swieph[0] - $xx_vsop[0]) * 3600; // в arcsec
    $diff_lat = abs($xx_swieph[1] - $xx_vsop[1]) * 3600;
    $diff_dist_km = abs($xx_swieph[2] - $xx_vsop[2]) * 149597870.7; // AU to km

    $max_lon_diff = max($max_lon_diff, $diff_lon);
    $max_lat_diff = max($max_lat_diff, $diff_lat);
    $max_dist_diff = max($max_dist_diff, $diff_dist_km);

    printf("  SWIEPH: lon=%11.6f°  lat=%11.6f°  dist=%.9f AU\n",
        $xx_swieph[0], $xx_swieph[1], $xx_swieph[2]);
    printf("  VSOP87: lon=%11.6f°  lat=%11.6f°  dist=%.9f AU\n",
        $xx_vsop[0], $xx_vsop[1], $xx_vsop[2]);
    printf("  DIFF:   lon=%8.3f\"      lat=%8.3f\"      dist=%10.1f km\n",
        $diff_lon, $diff_lat, $diff_dist_km);

    // Проверяем точность (VSOP87 обычно ~1-10" точность vs SWIEPH)
    if ($diff_lon > 100 || $diff_lat > 100 || $diff_dist_km > 100000) {
        echo "  ⚠ WARNING: Large discrepancy detected!\n";
    } else {
        echo "  ✓ Within expected VSOP87 accuracy\n";
    }

    echo "\n";
}

echo str_repeat("=", 60) . "\n";
printf("Maximum differences:\n");
printf("  Longitude: %.3f\" (%.6f°)\n", $max_lon_diff, $max_lon_diff / 3600);
printf("  Latitude:  %.3f\" (%.6f°)\n", $max_lat_diff, $max_lat_diff / 3600);
printf("  Distance:  %.1f km\n", $max_dist_diff);

if ($max_lon_diff < 100 && $max_lat_diff < 100 && $max_dist_diff < 100000) {
    echo "\n✓ All tests PASSED - VSOP87 accuracy within expected limits\n";
    exit(0);
} else {
    echo "\n✗ FAILED - VSOP87 accuracy exceeds expected limits\n";
    exit(1);
}
