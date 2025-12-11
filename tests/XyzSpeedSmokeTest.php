<?php

require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Obliquity.php';
require __DIR__ . '/../src/Coordinates.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Moon.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

function assertNonZeroVec(array $v)
{
    if (abs($v[0]) + abs($v[1]) + abs($v[2]) <= 0.0) {
        fwrite(STDERR, "Expected non-zero vector speed\n");
        exit(2);
    }
}

$flags = Constants::SEFLG_XYZ | Constants::SEFLG_SPEED;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_SUN, $flags, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Sun XYZ SPEED failed ret=$ret serr=$serr\n");
    exit(1);
}
assertNonZeroVec([$xx[3], $xx[4], $xx[5]]);

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MOON, $flags, $xx, $serr);
if ($ret !== 0) {
    fwrite(STDERR, "Moon XYZ SPEED failed ret=$ret serr=$serr\n");
    exit(1);
}
assertNonZeroVec([$xx[3], $xx[4], $xx[5]]);

echo "OK\n";
