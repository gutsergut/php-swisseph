<?php
/**
 * Test Mercury speed accuracy against C reference (swetest64.exe)
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd_ut = 2451545.0; // J2000.0

// Reference values from swetest64.exe (1.1.2000 12:00 UT)
// Mercury          271.8892770  -0.9948286    1.415469448   1.5562581
$c_lon = 271.8892770;
$c_lat = -0.9948286;
$c_dist = 1.415469448;
$c_speed = 1.5562581; // deg/day

$xx = [];
$serr = '';
$ret = swe_calc_ut($jd_ut, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "=== Mercury Speed Test ===\n";
echo "Date: J2000.0 (2000-01-01 12:00 UT)\n";
echo "Reference: swetest64.exe v2.10.03\n\n";

$dLon = ($xx[0] - $c_lon) * 3600;   // arcsec
$dLat = ($xx[1] - $c_lat) * 3600;   // arcsec
$dDist = ($xx[2] - $c_dist) * 149597870.7; // km
$dSpeed = ($xx[3] - $c_speed) * 3600; // arcsec/day

printf("Longitude: PHP=%.7f° C=%.7f° diff=%+.2f\"\n", $xx[0], $c_lon, $dLon);
printf("Latitude:  PHP=%.7f° C=%.7f° diff=%+.2f\"\n", $xx[1], $c_lat, $dLat);
printf("Distance:  PHP=%.9f AU C=%.9f AU diff=%+.1f km\n", $xx[2], $c_dist, $dDist);
printf("Speed:     PHP=%.7f°/d C=%.7f°/d diff=%+.2f\"/day\n", $xx[3], $c_speed, $dSpeed);

echo "\n";
if (abs($dSpeed) < 1.0) {
    echo "✓ Speed within acceptable tolerance (<1\"/day)\n";
} else {
    echo "✗ Speed differs by more than 1\"/day\n";
}
