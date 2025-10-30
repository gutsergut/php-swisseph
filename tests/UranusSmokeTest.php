<?php

require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Uranus.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_URANUS, 0, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Uranus should be supported: ret=$ret serr=$serr\n");
    exit(1);
}
// Uranus ~19 AU from Sun; geocentric distance ~18-21 AU acceptable
if ($xx[2] <= 16.0 || $xx[2] > 22.5) {
    fwrite(STDERR, "Uranus dist AU looks wrong: {$xx[2]}\n");
    exit(2);
}

$xx = [];
$serr = null;
$ret = swe_calc(
    2451545.0,
    Constants::SE_URANUS,
    Constants::SEFLG_SPEED,
    $xx,
    $serr
);
if ($ret !== 0) {
    fwrite(STDERR, "Uranus SPEED failed: ret=$ret serr=$serr\n");
    exit(3);
}
if (abs($xx[3]) < 0.001 || abs($xx[3]) > 0.1) {
    fwrite(STDERR, "Uranus dLon deg/day suspicious: {$xx[3]}\n");
    exit(4);
}

$xx = [];
$serr = null;
$ret = swe_calc(
    2451545.0,
    Constants::SE_URANUS,
    Constants::SEFLG_XYZ | Constants::SEFLG_SPEED,
    $xx,
    $serr
);
if ($ret !== 0) {
    fwrite(STDERR, "Uranus XYZ SPEED failed: ret=$ret serr=$serr\n");
    exit(5);
}
if (abs($xx[3]) + abs($xx[4]) + abs($xx[5]) <= 0.0) {
    fwrite(STDERR, "Uranus xyz speed zero\n");
    exit(6);
}

echo "OK\n";
