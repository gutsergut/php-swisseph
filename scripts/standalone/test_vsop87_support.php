<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$planets = [
    'Venus' => Constants::SE_VENUS,
    'Mars' => Constants::SE_MARS,
    'Jupiter' => Constants::SE_JUPITER,
    'Saturn' => Constants::SE_SATURN,
    'Uranus' => Constants::SE_URANUS,
    'Neptune' => Constants::SE_NEPTUNE,
];

echo "=== Testing VSOP87 Planet Support ===\n\n";

foreach ($planets as $name => $ipl) {
    $xx = array_fill(0, 6, 0.0);
    $serr = '';
    $ret = swe_calc(2451545.0, $ipl, Constants::SEFLG_VSOP87, $xx, $serr);

    if ($ret < 0) {
        echo "$name: ✗ FAILED - $serr\n";
    } else {
        printf("%s: ✓ SUCCESS - Lon=%.2f° Lat=%.2f° Dist=%.4f AU\n", $name, $xx[0], $xx[1], $xx[2]);
    }
}
