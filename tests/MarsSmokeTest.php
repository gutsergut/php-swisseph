<?php

require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Mars.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MARS, 0, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Mars should be supported: ret=$ret serr=$serr\n");
    exit(1);
}
if ($xx[2] <= 0.3 || $xx[2] > 3.0) {
    fwrite(STDERR, "Mars dist AU looks wrong: {$xx[2]}\n");
    exit(2);
}

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MARS, Constants::SEFLG_SPEED, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Mars SPEED failed: ret=$ret serr=$serr\n");
    exit(3);
}
if (abs($xx[3]) < 0.05 || abs($xx[3]) > 1.5) {
    fwrite(STDERR, "Mars dLon deg/day suspicious: {$xx[3]}\n");
    exit(4);
}

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MARS, Constants::SEFLG_XYZ | Constants::SEFLG_SPEED, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Mars XYZ SPEED failed: ret=$ret serr=$serr\n");
    exit(5);
}
if (abs($xx[3]) + abs($xx[4]) + abs($xx[5]) <= 0.0) {
    fwrite(STDERR, "Mars xyz speed zero\n");
    exit(6);
}

echo "OK\n";
