<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
$jd = 2451545.0;
$serr = '';

// Вычислим deltaT
$deltaT = swe_deltat($jd);
$tjd_et = $jd + $deltaT;

echo "PHP deltaT = {$deltaT} days = " . ($deltaT * 86400) . " sec\n";
echo "PHP tjd_et = {$tjd_et}\n\n";

// Шаг 0.0001 дня - как в C для расчёта скоростей
for ($i = 0; $i < 3; $i++) {
    $t = $tjd_et + $i * 0.0001;
    $xx = array_fill(0, 6, 0.0);
    swe_calc($t, Constants::SE_JUPITER,
        Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_J2000,
        $xx, $serr);
    printf("PHP planForOscElem INPUT [i=%d]: xx=[%.10f, %.10f, %.10f, %.10f, %.10f, %.10f]\n",
           $i, $xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]);
}
