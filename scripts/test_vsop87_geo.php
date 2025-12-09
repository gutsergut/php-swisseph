<?php
/**
 * Test VSOP87 geocentric coordinates after ecliptic->equatorial transformation
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// JD = 2451545.0 (J2000.0) for Saturn
$jd = 2451545.0;
$planet = Constants::SE_SATURN;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Calculate with VSOP87
$xx_vsop = array_fill(0, 6, 0.0);
$serr = '';
$ret_vsop = swe_calc($jd, $planet, Constants::SEFLG_VSOP87, $xx_vsop, $serr);

echo "=== VSOP87 Geocentric (after fix) ===\n";
if ($ret_vsop < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

printf("Longitude: %.6f°\n", $xx_vsop[0]);
printf("Latitude:  %.6f°\n", $xx_vsop[1]);
printf("Distance:  %.9f AU\n", $xx_vsop[2]);
printf("Speed Lon: %.9f °/day\n", $xx_vsop[3]);
printf("Speed Lat: %.9f °/day\n", $xx_vsop[4]);
printf("Speed Dst: %.9f AU/day\n", $xx_vsop[5]);

// Calculate with SWIEPH for reference
$xx_swieph = array_fill(0, 6, 0.0);
$ret_swieph = swe_calc($jd, $planet, Constants::SEFLG_SWIEPH, $xx_swieph, $serr);

echo "\n=== SWIEPH Reference ===\n";
if ($ret_swieph < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

printf("Longitude: %.6f°\n", $xx_swieph[0]);
printf("Latitude:  %.6f°\n", $xx_swieph[1]);
printf("Distance:  %.9f AU\n", $xx_swieph[2]);
printf("Speed Lon: %.9f °/day\n", $xx_swieph[3]);
printf("Speed Lat: %.9f °/day\n", $xx_swieph[4]);
printf("Speed Dst: %.9f AU/day\n", $xx_swieph[5]);

// Calculate errors
echo "\n=== Errors (VSOP87 - SWIEPH) ===\n";
$err_lon = $xx_vsop[0] - $xx_swieph[0];
$err_lat = $xx_vsop[1] - $xx_swieph[1];
$err_dist = $xx_vsop[2] - $xx_swieph[2];
$err_speed_lon = $xx_vsop[3] - $xx_swieph[3];
$err_speed_lat = $xx_vsop[4] - $xx_swieph[4];
$err_speed_dist = $xx_vsop[5] - $xx_swieph[5];

printf("ΔLongitude: %+.6f° (%.1f arcsec)\n", $err_lon, $err_lon * 3600);
printf("ΔLatitude:  %+.6f° (%.1f arcsec)\n", $err_lat, $err_lat * 3600);
printf("ΔDistance:  %+.9f AU (%.0f km)\n", $err_dist, $err_dist * 1.496e8);
printf("ΔSpeed Lon: %+.9f °/day\n", $err_speed_lon);
printf("ΔSpeed Lat: %+.9f °/day\n", $err_speed_lat);
printf("ΔSpeed Dst: %+.9f AU/day\n", $err_speed_dist);

// Check if errors are within acceptable range
$max_pos_error_deg = 0.01; // 36 arcsec
$max_dist_error_au = 0.0001; // ~15,000 km

$pos_ok = abs($err_lon) < $max_pos_error_deg && abs($err_lat) < $max_pos_error_deg;
$dist_ok = abs($err_dist) < $max_dist_error_au;

echo "\n=== Status ===\n";
echo "Position accuracy: " . ($pos_ok ? "✓ PASS" : "✗ FAIL") . " (< 0.01° = 36 arcsec)\n";
echo "Distance accuracy: " . ($dist_ok ? "✓ PASS" : "✗ FAIL") . " (< 15,000 km)\n";

if ($pos_ok && $dist_ok) {
    echo "\n✓ All checks PASSED!\n";
    exit(0);
} else {
    echo "\n✗ Some checks FAILED\n";
    exit(1);
}
