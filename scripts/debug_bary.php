<?php
/**
 * Отладка барицентрических координат VSOP87
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;

$ephe_path = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
swe_set_ephe_path($ephe_path);

// J2000.0
$jd = 2451545.0;
$ipl = Constants::SE_MERCURY;

echo "=== Барицентрические координаты Меркурия (J2000.0) ===\n\n";

// 1. SWIEPH барицентрические
$xx_swieph = [];
$serr = '';
$iflag_bary = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
$ret = PlanetsFunctions::calc($jd, $ipl, $iflag_bary, $xx_swieph, $serr);
if ($ret < 0) {
    die("SWIEPH error: $serr\n");
}
printf("SWIEPH BARY (XYZ AU): x=%.10f  y=%.10f  z=%.10f\n", $xx_swieph[0], $xx_swieph[1], $xx_swieph[2]);

// 2. VSOP87 барицентрические
$xx_vsop87 = [];
$serr = '';
$iflag_vsop = Constants::SEFLG_VSOP87 | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
$ret = PlanetsFunctions::calc($jd, $ipl, $iflag_vsop, $xx_vsop87, $serr);
if ($ret < 0) {
    die("VSOP87 error: $serr\n");
}
printf("VSOP87 BARY (XYZ AU): x=%.10f  y=%.10f  z=%.10f\n", $xx_vsop87[0], $xx_vsop87[1], $xx_vsop87[2]);

// 3. Разница
$dx = abs($xx_vsop87[0] - $xx_swieph[0]);
$dy = abs($xx_vsop87[1] - $xx_swieph[1]);
$dz = abs($xx_vsop87[2] - $xx_swieph[2]);
$dist_km = sqrt($dx*$dx + $dy*$dy + $dz*$dz) * 149597870.7; // AU → km

printf("\nРазница (AU):         dx=%.10f  dy=%.10f  dz=%.10f\n", $dx, $dy, $dz);
printf("Расстояние (км):      %.1f\n", $dist_km);

echo "\n=== Гелиоцентрические координаты для проверки ===\n\n";

// 4. VSOP87 гелиоцентрические (вручную загрузим модель)
$loader = new \Swisseph\Domain\Vsop87\VsopSegmentedLoader();
$modelDir = __DIR__ . '/../data/vsop87/mercury';
$model = $loader->loadModel($modelDir);

$calculator = new \Swisseph\Domain\Vsop87\VsopCalculator();
[$L_deg, $B_deg, $R_au] = $calculator->compute($model, $jd);

$lon_rad = deg2rad($L_deg);
$lat_rad = deg2rad($B_deg);
$xh = $R_au * cos($lat_rad) * cos($lon_rad);
$yh = $R_au * cos($lat_rad) * sin($lon_rad);
$zh = $R_au * sin($lat_rad);

printf("VSOP87 HELIO (XYZ AU): x=%.10f  y=%.10f  z=%.10f\n", $xh, $yh, $zh);
printf("VSOP87 HELIO (LBR):    L=%.6f°  B=%.6f°  R=%.10f AU\n", $L_deg, $B_deg, $R_au);

// 5. Получим SunBary из SwedState после расчета
$swed = \Swisseph\SwephFile\SwedState::getInstance();
$sunb_pd = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_SUNBARY] ?? null;

if ($sunb_pd) {
    printf("\nSunBary (из SwedState): x=%.10f  y=%.10f  z=%.10f\n",
        $sunb_pd->x[0], $sunb_pd->x[1], $sunb_pd->x[2]);

    // Проверим ручное преобразование helio → bary
    $xb_manual = $xh + $sunb_pd->x[0];
    $yb_manual = $yh + $sunb_pd->x[1];
    $zb_manual = $zh + $sunb_pd->x[2];

    printf("Ручное HELIO+SunBary:   x=%.10f  y=%.10f  z=%.10f\n", $xb_manual, $yb_manual, $zb_manual);

    // Сравним с результатом VSOP87 из Swe::calc
    $diff_manual = sqrt(
        pow($xb_manual - $xx_vsop87[0], 2) +
        pow($yb_manual - $xx_vsop87[1], 2) +
        pow($zb_manual - $xx_vsop87[2], 2)
    ) * 149597870.7;

    printf("Разница ручное vs VSOP87 BARY: %.1f km\n", $diff_manual);
} else {
    echo "\nSunBary not available in SwedState\n";
}
