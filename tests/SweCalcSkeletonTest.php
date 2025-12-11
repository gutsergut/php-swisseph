<?php

require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Error.php';
require __DIR__ . '/../src/Julian.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/DeltaT.php';
require __DIR__ . '/../src/Obliquity.php';
require __DIR__ . '/../src/Coordinates.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_MARS, 0, $xx, $serr);
if ($ret !== Constants::SE_ERR) {
    fwrite(STDERR, "swe_calc should return SE_ERR for now\n");
    exit(1);
}
if ($serr === null || stripos($serr, 'not implemented') === false) {
    fwrite(STDERR, "swe_calc serr should explain not implemented\n");
    exit(2);
}
if (count($xx) !== 6) {
    fwrite(STDERR, "swe_calc should provide xx[6] array\n");
    exit(3);
}

$xx = [];
$serr = null;
$ret = swe_calc_ut(2451545.0, Constants::SE_MARS, 0, $xx, $serr);
if ($ret !== Constants::SE_ERR) {
    fwrite(STDERR, "swe_calc_ut should return SE_ERR for now\n");
    exit(4);
}
if ($serr === null || stripos($serr, 'not implemented') === false) {
    fwrite(STDERR, "swe_calc_ut serr should explain not implemented\n");
    exit(5);
}
if (count($xx) !== 6) {
    fwrite(STDERR, "swe_calc_ut should provide xx[6] array\n");
    exit(6);
}

echo "OK\n";
