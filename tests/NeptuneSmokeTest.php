<?php
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Neptune.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

// Quick smoke test for Neptune ecliptic spherical output
$xx = [];
$jd_tt = 2451545.0; // J2000.0
$rc = swe_calc($jd_tt, \Swisseph\Constants::SE_NEPTUNE, 0, $xx, $err);
if ($rc < 0) {
    fwrite(STDERR, "swe_calc returned error: $err\n");
    exit(2);
}
// Expect distance roughly ~30 AU
if ($xx[2] < 20.0 || $xx[2] > 40.0) {
    fwrite(STDERR, "Neptune distance out of expected range: {$xx[2]}\n");
    exit(3);
}
echo "OK\n";
exit(0);
