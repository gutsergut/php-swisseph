<?php

require __DIR__ . '/bootstrap.php';

use Swisseph\Constants;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_SATURN, 0, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Saturn should be supported: ret=$ret serr=$serr\n");
    exit(1);
}
// Saturn distance around ~9-10 AU (geocentric varies), keep broad range
if ($xx[2] <= 7.0 || $xx[2] > 11.5) {
    fwrite(STDERR, "Saturn dist AU looks wrong: {$xx[2]}\n");
    exit(2);
}

$xx = [];
$serr = null;
$ret = swe_calc(
    2451545.0,
    Constants::SE_SATURN,
    Constants::SEFLG_SPEED,
    $xx,
    $serr
);
if ($ret !== 0) {
    fwrite(STDERR, "Saturn SPEED failed: ret=$ret serr=$serr\n");
    exit(3);
}
if (abs($xx[3]) < 0.002 || abs($xx[3]) > 0.2) {
    fwrite(STDERR, "Saturn dLon deg/day suspicious: {$xx[3]}\n");
    exit(4);
}

$xx = [];
$serr = null;
$ret = swe_calc(
    2451545.0,
    Constants::SE_SATURN,
    Constants::SEFLG_XYZ | Constants::SEFLG_SPEED,
    $xx,
    $serr
);
if ($ret !== 0) {
    fwrite(STDERR, "Saturn XYZ SPEED failed: ret=$ret serr=$serr\n");
    exit(5);
}
if (abs($xx[3]) + abs($xx[4]) + abs($xx[5]) <= 0.0) {
    fwrite(STDERR, "Saturn xyz speed zero\n");
    exit(6);
}

echo "OK\n";
