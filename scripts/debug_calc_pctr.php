<?php
/**
 * Debug script for swe_calc_pctr() with detailed intermediate values
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Initialize ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Debug swe_calc_pctr() - Venus-centric Mars ===\n\n";

// Test date: 1.1.2000 12:00 UT
$jd_ut = swe_julday(2000, 1, 1, 12.0, Constants::SE_GREG_CAL);
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH);

echo "Date: 1.1.2000 12:00 UT\n";
echo "JD_UT: $jd_ut\n";
echo "JD_ET: $jd_et\n\n";

// Get barycentric J2000 ICRS equatorial XYZ coordinates
$iflag_bary = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 |
              Constants::SEFLG_ICRS | Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL |
              Constants::SEFLG_XYZ | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR |
              Constants::SEFLG_NOGDEFL;

// Venus (center)
$xx_venus = [];
$serr = '';
$ret = swe_calc($jd_et, 3, $iflag_bary, $xx_venus, $serr);
if ($ret < 0) {
    die("Error calculating Venus: $serr\n");
}

echo "VENUS (barycentric J2000 ICRS equatorial XYZ):\n";
echo sprintf("  XYZ: [%.12f, %.12f, %.12f]\n", $xx_venus[0], $xx_venus[1], $xx_venus[2]);
echo sprintf("  VEL: [%.12f, %.12f, %.12f]\n", $xx_venus[3], $xx_venus[4], $xx_venus[5]);

// Convert to RA/Dec for display
$r_venus = sqrt($xx_venus[0]**2 + $xx_venus[1]**2 + $xx_venus[2]**2);
$ra_venus = atan2($xx_venus[1], $xx_venus[0]);
if ($ra_venus < 0) $ra_venus += 2 * M_PI;
$dec_venus = asin($xx_venus[2] / $r_venus);
echo sprintf("  RA:  %.6f° (%.2fh)\n", rad2deg($ra_venus), rad2deg($ra_venus) / 15);
echo sprintf("  Dec: %.6f°\n", rad2deg($dec_venus));
echo sprintf("  Dist: %.9f AU\n\n", $r_venus);

// Mars (target)
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
