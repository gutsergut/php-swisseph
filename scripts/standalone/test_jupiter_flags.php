<?php
/**
 * Test different flag combinations for Jupiter planetocentric
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Testing Flag Combinations for Jupiter from Venus ===\n\n";

$jd_ut = swe_julday(2024, 6, 15, 18.5, Constants::SE_GREG_CAL);
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH);

$ref_lon = 55.9212183;
$ref_lat = -1.0964699;

$test_cases = [
    'Standard (SWIEPH | SPEED)' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
    'No aberration' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR,
    'No deflection' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOGDEFL,
    'No aberr + no defl' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR | Constants::SEFLG_NOGDEFL,
    'TRUEPOS (no corrections)' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS,
    'J2000' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000,
    'ICRS' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_ICRS,
];

foreach ($test_cases as $name => $iflag) {
    $xx = [];
    $serr = '';

    $ret = swe_calc_pctr($jd_et, 5, 3, $iflag, $xx, $serr);

    if ($ret < 0) {
        echo "$name: ERROR - $serr\n";
        continue;
    }

    $diff_lon = abs($xx[0] - $ref_lon);
    $diff_lat = abs($xx[1] - $ref_lat);

    if ($diff_lon > 180) $diff_lon = 360 - $diff_lon;

    printf("%-30s: Lon=%10.6f° Lat=%9.6f° | ΔLon=%6.2f\" ΔLat=%6.2f\"\n",
           $name, $xx[0], $xx[1], $diff_lon * 3600, $diff_lat * 3600);
}

echo "\nReference (swetest64): Lon=$ref_lon° Lat=$ref_lat°\n";
