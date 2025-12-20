<?php
/**
 * Quick test for planetary moons (Io/Jupiter - 9501)
 *
 * Reference values from swetest64.exe:
 * Io/Jupiter at J2000.0 12:00 UT: lon=25.2454533°, lat=-1.2606572°, dist=4.623913869 AU
 */

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../eph/ephe');

// Convert to JD_TT: J2000.0 = 2451545.0 (noon on 1 Jan 2000)
// UT 12:00 → TT = UT + delta_t (~64 seconds in 2000)
$jd_ut = 2451545.0; // J2000.0, 1 Jan 2000, 12:00 UT
$delta_t = swe_deltat($jd_ut);
$jd_tt = $jd_ut + $delta_t;

echo "Testing Io/Jupiter (9501)\n";
echo "========================\n";
echo "JD UT: $jd_ut\n";
echo "JD TT: $jd_tt\n";
echo "Delta T: " . ($delta_t * 86400) . " seconds\n\n";

// Reference values from swetest64.exe
$ref_lon = 25.2454533;
$ref_lat = -1.2606572;
$ref_dist = 4.623913869;

// Calculate Io position
$ipl = Constants::SE_PLMOON_OFFSET + 501; // 9501 = Io/Jupiter
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$xx = [];
$serr = '';

echo "Calling swe_calc() for ipl=$ipl (Io)\n\n";

$ret = swe_calc($jd_tt, $ipl, $iflag, $xx, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Longitude: {$xx[0]}° (ref: {$ref_lon}°, diff: " . abs($xx[0] - $ref_lon) . "°)\n";
echo "Latitude:  {$xx[1]}° (ref: {$ref_lat}°, diff: " . abs($xx[1] - $ref_lat) . "°)\n";
echo "Distance:  {$xx[2]} AU (ref: {$ref_dist} AU, diff: " . abs($xx[2] - $ref_dist) . " AU)\n";
echo "Speed Lon: {$xx[3]}°/day\n";
echo "Speed Lat: {$xx[4]}°/day\n";
echo "Speed Dist:{$xx[5]} AU/day\n\n";

// Check accuracy
$lon_diff = abs($xx[0] - $ref_lon);
$lat_diff = abs($xx[1] - $ref_lat);
$dist_diff = abs($xx[2] - $ref_dist);

$tolerance_deg = 0.001; // 0.001° = 3.6 arcsec
$tolerance_au = 0.0001;

$lon_ok = $lon_diff < $tolerance_deg;
$lat_ok = $lat_diff < $tolerance_deg;
$dist_ok = $dist_diff < $tolerance_au;

echo "Accuracy check (tolerance: {$tolerance_deg}°, {$tolerance_au} AU):\n";
echo "Longitude: " . ($lon_ok ? "✓ PASS" : "✗ FAIL") . " (diff: " . ($lon_diff * 3600) . " arcsec)\n";
echo "Latitude:  " . ($lat_ok ? "✓ PASS" : "✗ FAIL") . " (diff: " . ($lat_diff * 3600) . " arcsec)\n";
echo "Distance:  " . ($dist_ok ? "✓ PASS" : "✗ FAIL") . " (diff: " . $dist_diff . " AU)\n\n";

if ($lon_ok && $lat_ok && $dist_ok) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
