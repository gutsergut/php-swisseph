<?php

require __DIR__ . '/bootstrap.php';

use Swisseph\Constants;

$flags = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED; // degrees by default
$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_SATURN, $flags, $xx, $serr);
// swe_calc returns iflag >= 0 on success, SE_ERR (-1) on error
if ($ret < 0) {
    fwrite(STDERR, "Saturn EQUATORIAL SPEED ret=$ret serr=$serr\n");
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
