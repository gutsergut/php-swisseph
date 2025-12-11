<?php
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sun.php';
require __DIR__ . '/../src/Pluto.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/../src/Formatter.php';
require __DIR__ . '/../src/functions.php';

// Pluto spherical ecliptic smoke test
$jd_tt = 2451545.0;
$xx = [];
$rc = swe_calc($jd_tt, \Swisseph\Constants::SE_PLUTO, 0, $xx, $err);
if ($rc < 0) {
    fwrite(STDERR, "swe_calc error: $err\n");
    exit(2);
}
// Distance around ~39 AU (very rough bounds)
if ($xx[2] < 25.0 || $xx[2] > 50.0) {
    fwrite(STDERR, "Pluto distance suspicious: {$xx[2]}\n");
    exit(3);
}
echo "OK\n";
exit(0);
