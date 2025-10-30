<?php

require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Obliquity.php';
require __DIR__ . '/../src/Coordinates.php';
require __DIR__ . '/../src/Moon.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MOON, 0, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Moon should be supported, ret=$ret serr=$serr\n");
    exit(1);
}
if ($xx[2] <= 0.0 || $xx[2] > 0.01) { // distance in AU ~ 0.00257
    fwrite(STDERR, "Unexpected Moon distance AU: {$xx[2]}\n");
    exit(2);
}

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MOON, Constants::SEFLG_SPEED, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Moon SPEED should be supported, ret=$ret serr=$serr\n");
    exit(3);
}
if (abs($xx[3]) < 10.0 || abs($xx[3]) > 20.0) { // Moon ~13 deg/day
    fwrite(STDERR, "Unexpected Moon dLon deg/day: {$xx[3]}\n");
    exit(4);
}

echo "OK\n";
