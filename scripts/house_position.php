<?php
/**
 * Тест позиции планет в домах
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd = 2451545.0;
$lat = 51.5;
$lon = 0.0;
$hsys = 'P';

// Calculate houses
$cusps = [];
$ascmc = [];
swe_houses($jd, $lat, $lon, $hsys, $cusps, $ascmc);

// Calculate Sun position
$xx = [];
$serr = null;
swe_calc($jd, Constants::SE_SUN, 0, $xx, $serr);
$sun_lon = $xx[0];

// Get house position for Sun
$hpos = swe_house_pos($ascmc[2], $lat, $xx[1], $hsys, [$sun_lon, $xx[1]], $serr);

if ($hpos < 1.0 || $hpos > 12.999) {
    fwrite(STDERR, "House position out of range: $hpos\n");
    exit(1);
}

// Test that house position changes with longitude
swe_calc($jd, Constants::SE_MOON, 0, $xx, $serr);
$moon_lon = $xx[0];
$moon_hpos = swe_house_pos($ascmc[2], $lat, $xx[1], $hsys, [$moon_lon, $xx[1]], $serr);

if (abs($moon_hpos - $hpos) < 0.001 && abs($moon_lon - $sun_lon) > 10.0) {
    fwrite(STDERR, "House positions suspiciously identical\n");
    exit(2);
}

// Test Equal houses (simpler calculation)
swe_calc($jd, Constants::SE_SUN, 0, $xx, $serr);
$hpos_equal = swe_house_pos($ascmc[2], $lat, $xx[1], 'E', [$xx[0], $xx[1]], $serr);
if ($hpos_equal < 1.0 || $hpos_equal > 12.999) {
    fwrite(STDERR, "Equal house position out of range\n");
    exit(3);
}

echo "OK\n";
