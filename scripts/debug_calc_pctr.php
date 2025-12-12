<?php
/**
 * Comprehensive debug script for swe_calc_pctr()
 * Tests both simple barycentric subtraction and full swe_calc_pctr() implementation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=================================================================\n";
echo "=== PLANETOCENTRIC DEBUG: Venus-centric Mars (1.1.2000 12:00) ===\n";
echo "=================================================================\n\n";

// Test date: 1.1.2000 12:00 UT
$jd_ut = swe_julday(2000, 1, 1, 12.0, Constants::SE_GREG_CAL);
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH);

echo "JD_UT: $jd_ut, JD_ET: $jd_et\n\n";

// ========================================================================
// PART 1: Simple barycentric subtraction (NO light-time correction)
// ========================================================================
echo "--- PART 1: Simple Barycentric Subtraction ---\n\n";

$iflag_bary = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 |
              Constants::SEFLG_ICRS | Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL |
              Constants::SEFLG_XYZ | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR |
              Constants::SEFLG_NOGDEFL;

$xx_venus = [];
$xx_mars = [];
$serr = '';

swe_calc($jd_et, 3, $iflag_bary, $xx_venus, $serr);
swe_calc($jd_et, 4, $iflag_bary, $xx_mars, $serr);

echo "Venus barycentric XYZ: [" . sprintf("%.12f, %.12f, %.12f", $xx_venus[0], $xx_venus[1], $xx_venus[2]) . "]\n";
echo "Mars  barycentric XYZ: [" . sprintf("%.12f, %.12f, %.12f", $xx_mars[0], $xx_mars[1], $xx_mars[2]) . "]\n\n";

// Subtract Venus from Mars
$xx_simple = [
    $xx_mars[0] - $xx_venus[0],
    $xx_mars[1] - $xx_venus[1],
    $xx_mars[2] - $xx_venus[2]
];

echo "Mars - Venus (simple): [" . sprintf("%.12f, %.12f, %.12f", $xx_simple[0], $xx_simple[1], $xx_simple[2]) . "]\n";

// Convert to RA/Dec
$r_simple = sqrt($xx_simple[0]**2 + $xx_simple[1]**2 + $xx_simple[2]**2);
$ra_simple = atan2($xx_simple[1], $xx_simple[0]);
if ($ra_simple < 0) $ra_simple += 2 * M_PI;
$dec_simple = asin($xx_simple[2] / $r_simple);

echo sprintf("  → Equatorial RA/Dec: %.6f° / %.6f°\n", rad2deg($ra_simple), rad2deg($dec_simple));

// Transform to ecliptic J2000
$eps_j2000 = 23.4392794444444 * M_PI / 180;
$sin_eps = sin($eps_j2000);
$cos_eps = cos($eps_j2000);

$xx_ecl_simple = [
    $xx_simple[0],
    $xx_simple[1] * $cos_eps + $xx_simple[2] * $sin_eps,
    -$xx_simple[1] * $sin_eps + $xx_simple[2] * $cos_eps
];

$lon_simple = atan2($xx_ecl_simple[1], $xx_ecl_simple[0]);
if ($lon_simple < 0) $lon_simple += 2 * M_PI;
$lat_simple = asin($xx_ecl_simple[2] / $r_simple);

echo sprintf("  → Ecliptic Lon/Lat:  %.7f° / %.7f°\n\n", rad2deg($lon_simple), rad2deg($lat_simple));

// ========================================================================
// PART 2: Full swe_calc_pctr() with light-time correction
// ========================================================================
echo "--- PART 2: Full swe_calc_pctr() Implementation ---\n\n";

$xx_pctr = [];
$serr = '';
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

$ret = swe_calc_pctr($jd_et, 4, 3, $iflag, $xx_pctr, $serr);

if ($ret < 0) {
    echo "ERROR: swe_calc_pctr() failed: $serr\n";
} else {
    echo "swe_calc_pctr() result (ecliptic):\n";
    echo sprintf("  Lon: %.7f°\n", $xx_pctr[0]);
    echo sprintf("  Lat: %.7f°\n", $xx_pctr[1]);
    echo sprintf("  Dist: %.9f AU\n", $xx_pctr[2]);
    echo sprintf("  Speed: [%.9f, %.9f, %.9f] AU/day\n\n", $xx_pctr[3], $xx_pctr[4], $xx_pctr[5]);
}

// ========================================================================
// PART 3: Compare with swetest64 reference
// ========================================================================
echo "--- PART 3: Reference from swetest64.exe ---\n\n";

// Reference values obtained with:
// swetest64.exe -b1.1.2000 -ut12:00 -p4 -pctr3 -fPlb -head -eswe
echo "Reference (swetest64 -pctr3):\n";
echo "  Lon: 359.4388477°\n";
echo "  Lat: -1.4197691°\n\n";

// ========================================================================
// PART 4: Analysis and comparison
// ========================================================================
echo "--- PART 4: Analysis ---\n\n";

$ref_lon = 359.4388477;
$ref_lat = -1.4197691;

echo "Comparison:\n";
echo sprintf("  Simple method:      Lon=%.7f°  Lat=%.7f°\n", rad2deg($lon_simple), rad2deg($lat_simple));
echo sprintf("  swe_calc_pctr():    Lon=%.7f°  Lat=%.7f°\n", $xx_pctr[0], $xx_pctr[1]);
echo sprintf("  Reference (swet64): Lon=%.7f°  Lat=%.7f°\n\n", $ref_lon, $ref_lat);

$diff_simple_lon = abs(rad2deg($lon_simple) - $ref_lon);
$diff_simple_lat = abs(rad2deg($lat_simple) - $ref_lat);
$diff_pctr_lon = abs($xx_pctr[0] - $ref_lon);
$diff_pctr_lat = abs($xx_pctr[1] - $ref_lat);

// Handle 360° wrap for longitude
if ($diff_simple_lon > 180) $diff_simple_lon = 360 - $diff_simple_lon;
if ($diff_pctr_lon > 180) $diff_pctr_lon = 360 - $diff_pctr_lon;

// Check +360 variant for simple method
$lon_simple_deg = rad2deg($lon_simple);
$diff_simple_lon_plus360 = abs(($lon_simple_deg + 360) - $ref_lon);
if ($diff_simple_lon_plus360 < $diff_simple_lon) {
    $diff_simple_lon = $diff_simple_lon_plus360;
}

echo "Differences from reference:\n";
echo sprintf("  Simple method:   ΔLon=%.7f°  ΔLat=%.7f°\n", $diff_simple_lon, $diff_simple_lat);
echo sprintf("  swe_calc_pctr(): ΔLon=%.7f°  ΔLat=%.7f°\n\n", $diff_pctr_lon, $diff_pctr_lat);

if ($diff_pctr_lon < 0.01 && $diff_pctr_lat < 0.01) {
    echo "✅ PASSED: swe_calc_pctr() within 0.01° tolerance\n";
} else {
    echo "❌ FAILED: swe_calc_pctr() exceeds 0.01° tolerance\n";
    echo "\nDiagnostic info:\n";
    echo sprintf("  Light-time correction impact: %.7f°\n", abs($diff_simple_lon - $diff_pctr_lon));
}

$xx_mars = [];
$ret = swe_calc($jd_et, 4, $iflag_bary, $xx_mars, $serr);
if ($ret < 0) {
    die("Error calculating Mars: $serr\n");
}

echo "MARS (barycentric J2000 ICRS equatorial XYZ):\n";
echo sprintf("  XYZ: [%.12f, %.12f, %.12f]\n", $xx_mars[0], $xx_mars[1], $xx_mars[2]);
echo sprintf("  VEL: [%.12f, %.12f, %.12f]\n", $xx_mars[3], $xx_mars[4], $xx_mars[5]);

$r_mars = sqrt($xx_mars[0]**2 + $xx_mars[1]**2 + $xx_mars[2]**2);
$ra_mars = atan2($xx_mars[1], $xx_mars[0]);
if ($ra_mars < 0) $ra_mars += 2 * M_PI;
$dec_mars = asin($xx_mars[2] / $r_mars);
echo sprintf("  RA:  %.6f° (%.2fh)\n", rad2deg($ra_mars), rad2deg($ra_mars) / 15);
echo sprintf("  Dec: %.6f°\n", rad2deg($dec_mars));
echo sprintf("  Dist: %.9f AU\n\n", $r_mars);

// Subtract Venus from Mars (planetocenter)
$xx_rel = [];
for ($i = 0; $i < 6; $i++) {
    $xx_rel[$i] = $xx_mars[$i] - $xx_venus[$i];
}

echo "MARS relative to VENUS (planetocentric equatorial XYZ):\n";
echo sprintf("  XYZ: [%.12f, %.12f, %.12f]\n", $xx_rel[0], $xx_rel[1], $xx_rel[2]);
echo sprintf("  VEL: [%.12f, %.12f, %.12f]\n", $xx_rel[3], $xx_rel[4], $xx_rel[5]);

$r_rel = sqrt($xx_rel[0]**2 + $xx_rel[1]**2 + $xx_rel[2]**2);
$ra_rel = atan2($xx_rel[1], $xx_rel[0]);
if ($ra_rel < 0) $ra_rel += 2 * M_PI;
$dec_rel = asin($xx_rel[2] / $r_rel);
echo sprintf("  RA:  %.6f° (%.2fh)\n", rad2deg($ra_rel), rad2deg($ra_rel) / 15);
echo sprintf("  Dec: %.6f°\n", rad2deg($dec_rel));
echo sprintf("  Dist: %.9f AU\n\n", $r_rel);

// Transform to ecliptic J2000
// Obliquity of ecliptic J2000
$eps_j2000 = 23.4392794444444 * M_PI / 180; // 23°26'21.406"
$sin_eps = sin($eps_j2000);
$cos_eps = cos($eps_j2000);

$xx_ecl = [
    $xx_rel[0],
    $xx_rel[1] * $cos_eps + $xx_rel[2] * $sin_eps,
    -$xx_rel[1] * $sin_eps + $xx_rel[2] * $cos_eps
];

echo "MARS relative to VENUS (ecliptic J2000 XYZ):\n";
echo sprintf("  XYZ: [%.12f, %.12f, %.12f]\n", $xx_ecl[0], $xx_ecl[1], $xx_ecl[2]);

$lon_ecl = atan2($xx_ecl[1], $xx_ecl[0]);
if ($lon_ecl < 0) $lon_ecl += 2 * M_PI;
$r_ecl = sqrt($xx_ecl[0]**2 + $xx_ecl[1]**2 + $xx_ecl[2]**2);
$lat_ecl = asin($xx_ecl[2] / $r_ecl);
echo sprintf("  Lon: %.7f°\n", rad2deg($lon_ecl));
echo sprintf("  Lat: %.7f°\n", rad2deg($lat_ecl));
echo sprintf("  Dist: %.9f AU\n\n", $r_ecl);

echo "REFERENCE from swetest64:\n";
echo "  Lon: 359.4388477°\n";
echo "  Lat: -1.4197691°\n\n";

echo "DIFFERENCES:\n";
echo sprintf("  Lon diff: %.7f°\n", abs(rad2deg($lon_ecl) - 359.4388477));
echo sprintf("  Lat diff: %.7f°\n", abs(rad2deg($lat_ecl) - (-1.4197691)));

if (abs(rad2deg($lon_ecl) - 359.4388477) > 0.01 || abs(rad2deg($lat_ecl) - (-1.4197691)) > 0.01) {
    echo "\n❌ FAILED: Differences exceed 0.01°\n";

    // Additional diagnostic: check if it's a 360° wrap issue
    $lon_diff_360 = abs((rad2deg($lon_ecl) + 360) - 359.4388477);
    $lon_diff_m360 = abs((rad2deg($lon_ecl) - 360) - 359.4388477);
    echo "\nDiagnostics:\n";
    echo sprintf("  Lon diff (current): %.7f°\n", abs(rad2deg($lon_ecl) - 359.4388477));
    echo sprintf("  Lon diff (+360):    %.7f°\n", $lon_diff_360);
    echo sprintf("  Lon diff (-360):    %.7f°\n", $lon_diff_m360);
} else {
    echo "\n✅ PASSED: Within 0.01° tolerance\n";
}
