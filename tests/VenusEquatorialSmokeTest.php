<?php

require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Venus.php';
require __DIR__ . '/../src/Obliquity.php';
require __DIR__ . '/../src/Coordinates.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

$flags = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED; // degrees by default
$xx = []; $serr = null;
$ret = swe_calc(2451545.0, Constants::SE_VENUS, $flags, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Venus EQUATORIAL SPEED ret=$ret serr=$serr\n");
    exit(1);
}
if ($xx[0] < 0.0 || $xx[0] >= 360.0) {
    fwrite(STDERR, "RA deg out of range: {$xx[0]}\n");
    exit(2);
}
if ($xx[1] < -90.0 || $xx[1] > 90.0) {
    fwrite(STDERR, "Dec deg out of range: {$xx[1]}\n");
    exit(3);
}
if (abs($xx[3]) <= 0.0) {
    fwrite(STDERR, "dRA deg/day zero\n");
    exit(4);
}

echo "OK\n";
