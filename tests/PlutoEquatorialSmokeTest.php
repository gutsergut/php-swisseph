<?php
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Pluto.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/Obliquity.php';
require __DIR__ . '/../src/Coordinates.php';
require __DIR__ . '/../src/functions.php';

$jd_tt = 2451545.0;
$xx = [];
$rc = swe_calc($jd_tt, \Swisseph\Constants::SE_PLUTO, \Swisseph\Constants::SEFLG_EQUATORIAL, $xx, $err);
if ($rc < 0) {
    fwrite(STDERR, "swe_calc error: $err\n");
    exit(2);
}
if ($xx[0] < 0 || $xx[0] >= 360 || $xx[1] < -90 || $xx[1] > 90) {
    fwrite(STDERR, "Pluto RA/Dec out of range: RA={$xx[0]} Dec={$xx[1]}\n");
    exit(3);
}
echo "OK\n";
exit(0);
