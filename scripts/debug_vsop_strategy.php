<?php
/**
 * Детальная отладка VSOP87 helio/bary
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephe_path = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
swe_set_ephe_path($ephe_path);

$jd = 2451545.0;

echo "=== Тест VSOP87 Strategy напрямую ===\n\n";

// 1. Создадим Strategy и вызовем напрямую
$strategy = new \Swisseph\Swe\Planets\Vsop87Strategy();

$iflag_bary = Constants::SEFLG_VSOP87 | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000;
$serr = '';
$result = $strategy->compute($jd, Constants::SE_MERCURY, $iflag_bary, $serr);

if ($result->retc < 0) {
    die("Strategy error: " . ($result->serr ?? 'unknown') . "\n");
}

$xx = $result->x;
printf("Strategy BARY result: lon=%.6f°  lat=%.6f°  dist=%.9f AU\n", $xx[0], $xx[1], $xx[2]);

// 2. Проверим SwedState после вызова
$swed = \Swisseph\SwephFile\SwedState::getInstance();
$sunb_pd = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_SUNBARY] ?? null;

if ($sunb_pd) {
    printf("\nSunBary в SwedState: x=%.10f  y=%.10f  z=%.10f AU\n",
        $sunb_pd->x[0], $sunb_pd->x[1], $sunb_pd->x[2]);
} else {
    echo "\nSunBary НЕ ЗАПОЛНЕН в SwedState!\n";
}

// 3. Теперь вызовем с HELCTR
$iflag_helio = Constants::SEFLG_VSOP87 | Constants::SEFLG_HELCTR | Constants::SEFLG_J2000;
$result2 = $strategy->compute($jd, Constants::SE_MERCURY, $iflag_helio, $serr);

if ($result2->retc < 0) {
    echo "\nStrategy HELIO error: " . ($result2->serr ?? 'unknown') . "\n";
} else {
    $xx2 = $result2->x;
    printf("\nStrategy HELIO result: lon=%.6f°  lat=%.6f°  dist=%.9f AU\n", $xx2[0], $xx2[1], $xx2[2]);
}

// 4. Проверим SunBary еще раз
if ($sunb_pd) {
    printf("\nSunBary после HELIO: x=%.10f  y=%.10f  z=%.10f AU\n",
        $sunb_pd->x[0], $sunb_pd->x[1], $sunb_pd->x[2]);
}

// 5. Загрузим VSOP87 модель напрямую и вычислим гелиоцентр
echo "\n=== Прямой расчёт VSOP87 ===\n\n";
$loader = new \Swisseph\Domain\Vsop87\VsopSegmentedLoader();
$model = $loader->loadPlanet(__DIR__ . '/../data/vsop87/mercury');
$calc = new \Swisseph\Domain\Vsop87\Vsop87Calculator();
[$L, $B, $R] = $calc->compute($model, $jd);

printf("VSOP87 прямо: L=%.6f°  B=%.6f°  R=%.9f AU\n", $L, $B, $R);

$lon_rad = deg2rad($L);
$lat_rad = deg2rad($B);
$xh = $R * cos($lat_rad) * cos($lon_rad);
$yh = $R * cos($lat_rad) * sin($lon_rad);
$zh = $R * sin($lat_rad);

printf("Helio XYZ: x=%.10f  y=%.10f  z=%.10f AU\n", $xh, $yh, $zh);

if ($sunb_pd) {
    $xb = $xh + $sunb_pd->x[0];
    $yb = $yh + $sunb_pd->x[1];
    $zb = $zh + $sunb_pd->x[2];
    printf("Bary  XYZ: x=%.10f  y=%.10f  z=%.10f AU\n", $xb, $yb, $zb);

    // Преобразуем обратно в сферические
    $r = sqrt($xb*$xb + $yb*$yb + $zb*$zb);
    $lon = atan2($yb, $xb);
    $lat = asin($zb / $r);
    printf("Bary LBR:  L=%.6f°  B=%.6f°  R=%.9f AU\n", rad2deg($lon), rad2deg($lat), $r);
}
