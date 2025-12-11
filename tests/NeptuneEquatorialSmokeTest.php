<?php
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Neptune.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/Obliquity.php';
require __DIR__ . '/../src/Coordinates.php';
require __DIR__ . '/../src/functions.php';

// Smoke test for Neptune equatorial output (RA/Dec)
$jd_tt = 2451545.0;
$iflag = \Swisseph\Constants::SEFLG_EQUATORIAL;
$xx = [];
$rc = swe_calc($jd_tt, \Swisseph\Constants::SE_NEPTUNE, $iflag, $xx, $err);
if ($rc < 0) {
    fwrite(STDERR, "swe_calc returned error: $err\n");
    exit(2);
}
// RA should be within 0..360 and Dec within -90..90
if ($xx[0] < 0 || $xx[0] > 360 || $xx[1] < -90 || $xx[1] > 90) {
    fwrite(STDERR, "Neptune equatorial angles out of range: RA={$xx[0]} Dec={$xx[1]}\n");
    exit(3);
}
echo "OK\n";
exit(0);
