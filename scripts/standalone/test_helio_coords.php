<?php
// Quick test for heliocentric coordinates
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$SE_MARS = Constants::SE_MARS;
$SE_JUPITER = Constants::SE_JUPITER;
$SEFLG_HELCTR = Constants::SEFLG_HELCTR;
$SEFLG_XYZ = Constants::SEFLG_XYZ;
$SEFLG_TRUEPOS = Constants::SEFLG_TRUEPOS;
$SEFLG_NONUT = Constants::SEFLG_NONUT;
$SEFLG_SPEED = Constants::SEFLG_SPEED;

$jd = 2451545.0; // J2000.0
$planets = [
    'Mars' => $SE_MARS,
    'Jupiter' => $SE_JUPITER,
];

echo "Testing Heliocentric Coordinates\n";
echo str_repeat("=", 70) . "\n\n";

foreach ($planets as $name => $id) {
    echo "Planet: $name\n";

    // WITHOUT SEFLG_XYZ
    $iflag1 = $SEFLG_HELCTR | $SEFLG_TRUEPOS | $SEFLG_NONUT | $SEFLG_SPEED;
    $xx1 = [];
    $serr1 = '';
    $ret1 = swe_calc($jd, $id, $iflag1, $xx1, $serr1);

    echo "  WITHOUT XYZ: ret=$ret1, serr='$serr1'\n";
    echo "  WITHOUT XYZ: xx[0-2] = [" . implode(', ', array_map(fn($v) => sprintf('%.10f', $v), array_slice($xx1, 0, 3))) . "]\n";
    if ($ret1 >= 0) {
        echo "  Distance from sqrt(x²+y²+z²): " . sprintf('%.6f', sqrt($xx1[0]**2 + $xx1[1]**2 + $xx1[2]**2)) . " AU\n";
    }    // WITH SEFLG_XYZ
    $iflag2 = $SEFLG_HELCTR | $SEFLG_XYZ | $SEFLG_TRUEPOS | $SEFLG_NONUT | $SEFLG_SPEED;
    $xx2 = [];
    $serr2 = '';
    swe_calc($jd, $id, $iflag2, $xx2, $serr2);

    echo "  WITH XYZ:    xx[0-2] = [" . implode(', ', array_map(fn($v) => sprintf('%.10f', $v), array_slice($xx2, 0, 3))) . "]\n";
    echo "  Distance from sqrt(x²+y²+z²): " . sprintf('%.6f', sqrt($xx2[0]**2 + $xx2[1]**2 + $xx2[2]**2)) . " AU\n";    echo "\n";
}
