<?php

/**
 * Test script for main belt asteroids (Chiron, Pholus, Ceres, Pallas, Juno, Vesta)
 *
 * Reference values from swetest64.exe for JD=2460000.5 (25 Feb 2023 00:00 UT)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2460000.5;

// Reference values from swetest64:
// Chiron: 13°38'47.9293" = 13.64664692°, lat=1°37'52.6365" = 1.63127125°, dist=19.577859095
// Pholus: 278.4755978°, lat=9.9411965°, dist=30.149720360
// Ceres: 185.3528945°, lat=16.2879155°, dist=1.673828157
// Pallas: 100.7999382°, lat=-40.1074536°, dist=1.507360256
// Juno: 21.8129822°, lat=-8.1383841°, dist=2.549714074
// Vesta: 7.4579634°, lat=-5.3827112°, dist=3.252325689

$asteroids = [
    Constants::SE_CHIRON => ['name' => 'Chiron', 'lon' => 13.64664692, 'lat' => 1.63127125, 'dist' => 19.577859095],
    Constants::SE_PHOLUS => ['name' => 'Pholus', 'lon' => 278.4755978, 'lat' => 9.9411965, 'dist' => 30.149720360],
    Constants::SE_CERES  => ['name' => 'Ceres', 'lon' => 185.3528945, 'lat' => 16.2879155, 'dist' => 1.673828157],
    Constants::SE_PALLAS => ['name' => 'Pallas', 'lon' => 100.7999382, 'lat' => -40.1074536, 'dist' => 1.507360256],
    Constants::SE_JUNO   => ['name' => 'Juno', 'lon' => 21.8129822, 'lat' => -8.1383841, 'dist' => 2.549714074],
    Constants::SE_VESTA  => ['name' => 'Vesta', 'lon' => 7.4579634, 'lat' => -5.3827112, 'dist' => 3.252325689],
];

echo "Testing main belt asteroids for JD=$jd (25 Feb 2023)\n";
echo str_repeat("=", 70) . "\n\n";

$allPass = true;

foreach ($asteroids as $ipl => $data) {
    $xx = [];
    $serr = null;

    $ret = \swe_calc($jd, $ipl, Constants::SEFLG_SPEED, $xx, $serr);

    if ($ret < 0) {
        echo "{$data['name']} (ipl=$ipl): ERROR - $serr\n";
        $allPass = false;
        continue;
    }

    $lonDiff = abs($xx[0] - $data['lon']);
    $latDiff = abs($xx[1] - $data['lat']);
    $distDiff = abs($xx[2] - $data['dist']);

    // Tolerance: 0.01° for lon/lat, 0.001 AU for distance
    $lonOk = $lonDiff < 0.01;
    $latOk = $latDiff < 0.01;
    $distOk = $distDiff < 0.001;

    $status = ($lonOk && $latOk && $distOk) ? 'OK' : 'FAIL';
    if ($status !== 'OK') $allPass = false;

    echo "{$data['name']} (ipl=$ipl): $status\n";
    echo "  Longitude: {$xx[0]}° (expected: {$data['lon']}°, diff: " . sprintf("%.6f", $lonDiff) . "°)\n";
    echo "  Latitude:  {$xx[1]}° (expected: {$data['lat']}°, diff: " . sprintf("%.6f", $latDiff) . "°)\n";
    echo "  Distance:  {$xx[2]} AU (expected: {$data['dist']} AU, diff: " . sprintf("%.6f", $distDiff) . ")\n";
    echo "  Speed:     {$xx[3]}°/day\n\n";
}

echo str_repeat("=", 70) . "\n";
echo $allPass ? "ALL TESTS PASSED!\n" : "SOME TESTS FAILED!\n";
