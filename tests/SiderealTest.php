<?php

require __DIR__ . '/../src/Julian.php';
require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Sidereal.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Julian;
use Swisseph\Sidereal;

// 2000-01-01 12:00 UT (J2000) — GMST ≈ 18h 41m 50.54841s (Meeus)
$jd_noon = Julian::toJulianDay(2000, 1, 1, 12.0, 1);
$gmst_h = Sidereal::gmstHoursFromJdUt($jd_noon);
if (abs($gmst_h - (18.0 + 41.0/60.0 + 50.54841/3600.0)) > 0.01) {
    fwrite(STDERR, "GMST J2000 mismatch: $gmst_h\n");
    exit(1);
}

// 2000-01-01 00:00 UT — проверим в разумном диапазоне
$jd_mid = Julian::toJulianDay(2000, 1, 1, 0.0, 1);
$gmst0 = swe_sidtime($jd_mid);
if (!($gmst0 >= 6.5 && $gmst0 <= 7.0)) {
    fwrite(STDERR, "GMST midnight out of band: $gmst0\n");
    exit(2);
}

echo "OK\n";
