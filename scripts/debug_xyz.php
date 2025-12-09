<?php
/**
 * Отладка XYZ до и после appPosRest
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;

$ephe_path = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
swe_set_ephe_path($ephe_path);

$jd = 2451545.0;
$ipl = Constants::SE_MERCURY;

echo "=== Mercury J2000.0 VSOP87 BARYCTR XYZ ===\n\n";

// VSOP87 Barycentric XYZ
$xx = [];
$serr = '';
$iflag = Constants::SEFLG_VSOP87 | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
$ret = PlanetsFunctions::calc($jd, $ipl, $iflag, $xx, $serr);

printf("Result: x=%.10f  y=%.10f  z=%.10f AU\n", $xx[0], $xx[1], $xx[2]);
printf("        vx=%.10e  vy=%.10e  vz=%.10e AU/day\n", $xx[3], $xx[4], $xx[5]);

// Вычислим расстояние
$dist = sqrt($xx[0]**2 + $xx[1]**2 + $xx[2]**2);
printf("Distance: %.9f AU\n", $dist);

// Вычислим сферические координаты вручную
$lon_rad = atan2($xx[1], $xx[0]);
$lat_rad = asin($xx[2] / $dist);
printf("Spherical: lon=%.6f°  lat=%.6f°  dist=%.9f AU\n",
    rad2deg($lon_rad), rad2deg($lat_rad), $dist);

// Проверим что в xreturn PlanData
$swed = \Swisseph\SwephFile\SwedState::getInstance();
$ipli = \Swisseph\SwephFile\SwephConstants::PNOEXT2INT[$ipl] ?? null;
if ($ipli !== null) {
    $pd = $swed->pldat[$ipli] ?? null;
    if ($pd) {
        echo "\nPlanData xreturn[6..11] (ecliptic XYZ):\n";
        printf("  x=%.10f  y=%.10f  z=%.10f\n", $pd->xreturn[6], $pd->xreturn[7], $pd->xreturn[8]);
        echo "PlanData xreturn[0..2] (ecliptic lon/lat/dist):\n";
        printf("  lon=%.6f  lat=%.6f  dist=%.9f\n", $pd->xreturn[0], $pd->xreturn[1], $pd->xreturn[2]);
    }
}

echo "\n=== SWIEPH for comparison ===\n\n";
$xx_sw = [];
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
PlanetsFunctions::calc($jd, $ipl, $iflag, $xx_sw, $serr);
printf("SWIEPH: x=%.10f  y=%.10f  z=%.10f AU\n", $xx_sw[0], $xx_sw[1], $xx_sw[2]);
$dist_sw = sqrt($xx_sw[0]**2 + $xx_sw[1]**2 + $xx_sw[2]**2);
printf("        dist=%.9f AU\n", $dist_sw);
