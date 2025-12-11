<?php

require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Obliquity.php';
require __DIR__ . '/../src/Coordinates.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_SUN, Constants::SEFLG_SPEED, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Sun with SPEED should succeed, ret=$ret serr=$serr\n");
    exit(1);
}
if (abs($xx[3]) < 0.1 || abs($xx[3]) > 2.0) { // ~0.9856 deg/day
    fwrite(STDERR, "Unexpected dLon deg/day: {$xx[3]}\n");
    exit(2);
}
if (abs($xx[4]) > 0.1) { // lat speed near 0
    fwrite(STDERR, "Unexpected dLat deg/day: {$xx[4]}\n");
    exit(3);
}
if ($xx[5] === 0.0) {
    fwrite(STDERR, "Expected non-zero radial speed in AU/day\n");
    exit(4);
}

echo "OK\n";
