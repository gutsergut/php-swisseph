<?php

require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Mercury.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MERCURY, 0, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Mercury should be supported: ret=$ret serr=$serr\n");
    exit(1);
}
if ($xx[2] <= 0.0 || $xx[2] > 2.0) {
    fwrite(STDERR, "Mercury dist AU looks wrong: {$xx[2]}\n");
    exit(2);
}

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SPEED, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Mercury SPEED failed: ret=$ret serr=$serr\n");
    exit(3);
}
if (abs($xx[3]) < 0.5 || abs($xx[3]) > 3.0) {
    fwrite(STDERR, "Mercury dLon deg/day suspicious: {$xx[3]}\n");
    exit(4);
}

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_XYZ | Constants::SEFLG_SPEED, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Mercury XYZ SPEED failed: ret=$ret serr=$serr\n");
    exit(5);
}
if (abs($xx[3]) + abs($xx[4]) + abs($xx[5]) <= 0.0) {
    fwrite(STDERR, "Mercury xyz speed zero\n");
    exit(6);
}

echo "OK\n";
