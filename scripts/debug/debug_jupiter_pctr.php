<?php
/**
 * Debug script for Jupiter planetocentric calculation
 * Test case: Jupiter from Venus center, 15.6.2024 18:30 UT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=================================================================\n";
echo "=== Jupiter from Venus Center Debug (15.6.2024 18:30 UT) ===\n";
echo "=================================================================\n\n";

// Test date: 15.6.2024 18:30 UT
$jd_ut = swe_julday(2024, 6, 15, 18.5, Constants::SE_GREG_CAL);
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH);

echo "JD_UT: $jd_ut\n";
echo "JD_ET: $jd_et\n\n";

// Reference from swetest64
$ref_lon = 55.9212183;
$ref_lat = -1.0964699;
$ref_dist = 4.427077404;

echo "Reference (swetest64 -pc3):\n";
echo sprintf("  Lon: %.7f°\n", $ref_lon);
echo sprintf("  Lat: %.7f°\n", $ref_lat);
echo sprintf("  Dist: %.9f AU\n\n", $ref_dist);

// ========================================================================
// PART 1: Barycentric coordinates
// ========================================================================
echo "--- PART 1: Barycentric Coordinates ---\n\n";

$iflag_bary = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 |
              Constants::SEFLG_ICRS | Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL |
              Constants::SEFLG_XYZ | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR |
              Constants::SEFLG_NOGDEFL;

$xx_venus = [];
$xx_jupiter = [];
$serr = '';

swe_calc($jd_et, 3, $iflag_bary, $xx_venus, $serr);
swe_calc($jd_et, 5, $iflag_bary, $xx_jupiter, $serr);

echo "Venus barycentric XYZ:\n";
echo sprintf("  [%.12f, %.12f, %.12f]\n", $xx_venus[0], $xx_venus[1], $xx_venus[2]);
echo sprintf("  VEL: [%.12f, %.12f, %.12f]\n\n", $xx_venus[3], $xx_venus[4], $xx_venus[5]);

echo "Jupiter barycentric XYZ:\n";
echo sprintf("  [%.12f, %.12f, %.12f]\n", $xx_jupiter[0], $xx_jupiter[1], $xx_jupiter[2]);
echo sprintf("  VEL: [%.12f, %.12f, %.12f]\n\n", $xx_jupiter[3], $xx_jupiter[4], $xx_jupiter[5]);

// Simple subtraction
$xx_diff = [
    $xx_jupiter[0] - $xx_venus[0],
    $xx_jupiter[1] - $xx_venus[1],
    $xx_jupiter[2] - $xx_venus[2]
];

echo "Jupiter - Venus (barycentric subtraction):\n";
echo sprintf("  XYZ: [%.12f, %.12f, %.12f]\n", $xx_diff[0], $xx_diff[1], $xx_diff[2]);

// Convert to equatorial RA/Dec
$r_diff = sqrt($xx_diff[0]**2 + $xx_diff[1]**2 + $xx_diff[2]**2);
$ra_diff = atan2($xx_diff[1], $xx_diff[0]);
if ($ra_diff < 0) $ra_diff += 2 * M_PI;
$dec_diff = asin($xx_diff[2] / $r_diff);

echo sprintf("  RA: %.6f° (%.2fh)\n", rad2deg($ra_diff), rad2deg($ra_diff) / 15);
echo sprintf("  Dec: %.6f°\n", rad2deg($dec_diff));
echo sprintf("  Dist: %.9f AU\n\n", $r_diff);

// Transform to ecliptic J2000
$eps_j2000 = 23.4392794444444 * M_PI / 180;
$sin_eps = sin($eps_j2000);
$cos_eps = cos($eps_j2000);

$xx_ecl = [
    $xx_diff[0],
    $xx_diff[1] * $cos_eps + $xx_diff[2] * $sin_eps,
    -$xx_diff[1] * $sin_eps + $xx_diff[2] * $cos_eps
];

$lon_simple = atan2($xx_ecl[1], $xx_ecl[0]);
if ($lon_simple < 0) $lon_simple += 2 * M_PI;
$lat_simple = asin($xx_ecl[2] / $r_diff);

echo "Simple barycentric subtraction → ecliptic:\n";
echo sprintf("  Lon: %.7f°\n", rad2deg($lon_simple));
echo sprintf("  Lat: %.7f°\n\n", rad2deg($lat_simple));

// ========================================================================
// PART 2: Full swe_calc_pctr()
// ========================================================================
echo "--- PART 2: swe_calc_pctr() Result ---\n\n";

$xx_pctr = [];
$serr = '';
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

$ret = swe_calc_pctr($jd_et, 5, 3, $iflag, $xx_pctr, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "swe_calc_pctr(ipl=5[Jupiter], iplctr=3[Venus]):\n";
echo sprintf("  Lon: %.7f°\n", $xx_pctr[0]);
echo sprintf("  Lat: %.7f°\n", $xx_pctr[1]);
echo sprintf("  Dist: %.9f AU\n", $xx_pctr[2]);
echo sprintf("  Speed: [%.9f, %.9f, %.9f] °/day\n\n", $xx_pctr[3], $xx_pctr[4], $xx_pctr[5]);

// ========================================================================
// PART 3: Analysis
// ========================================================================
echo "--- PART 3: Comparison ---\n\n";

$diff_simple_lon = abs(rad2deg($lon_simple) - $ref_lon);
$diff_simple_lat = abs(rad2deg($lat_simple) - $ref_lat);

$diff_pctr_lon = abs($xx_pctr[0] - $ref_lon);
$diff_pctr_lat = abs($xx_pctr[1] - $ref_lat);
$diff_pctr_dist = abs($xx_pctr[2] - $ref_dist);

// Handle 360° wrap
if ($diff_simple_lon > 180) $diff_simple_lon = 360 - $diff_simple_lon;
if ($diff_pctr_lon > 180) $diff_pctr_lon = 360 - $diff_pctr_lon;

echo "Differences from swetest64 reference:\n\n";

echo "Simple barycentric subtraction:\n";
echo sprintf("  ΔLon: %.7f° (%.2f\")\n", $diff_simple_lon, $diff_simple_lon * 3600);
echo sprintf("  ΔLat: %.7f° (%.2f\")\n\n", $diff_simple_lat, $diff_simple_lat * 3600);

echo "swe_calc_pctr():\n";
echo sprintf("  ΔLon: %.7f° (%.2f\")\n", $diff_pctr_lon, $diff_pctr_lon * 3600);
echo sprintf("  ΔLat: %.7f° (%.2f\")\n", $diff_pctr_lat, $diff_pctr_lat * 3600);
echo sprintf("  ΔDist: %.9f AU\n\n", $diff_pctr_dist);

if ($diff_pctr_lon < 0.001 && $diff_pctr_lat < 0.001) {
    echo "✅ Excellent precision (<4\" arcseconds)\n";
} elseif ($diff_pctr_lon < 0.01 && $diff_pctr_lat < 0.01) {
    echo "✅ Good precision (<36\" arcseconds)\n";
} else {
    echo "⚠️  Precision issue detected\n";
    echo "\nPossible causes:\n";
    echo "1. Light-time correction differences\n";
    echo "2. Ephemeris version differences\n";
    echo "3. Numerical precision in transformations\n";
}

// ========================================================================
// PART 4: Light-time effect
// ========================================================================
echo "\n--- PART 4: Light-time Impact ---\n\n";

$light_time_days = $r_diff * 1.495978707e11 / 299792458 / 86400;
echo sprintf("Light-time: %.6f days (%.2f minutes)\n", $light_time_days, $light_time_days * 1440);
echo sprintf("Distance: %.9f AU\n\n", $r_diff);

$diff_light = abs(rad2deg($lon_simple) - $xx_pctr[0]);
if ($diff_light > 180) $diff_light = 360 - $diff_light;

echo sprintf("Longitude difference (simple vs full):\n");
echo sprintf("  %.7f° (%.2f\")\n", $diff_light, $diff_light * 3600);

if ($diff_light < 0.0001) {
    echo "→ Light-time correction has minimal impact (<0.4\")\n";
} else {
    echo "→ Light-time correction changes result by " . number_format($diff_light * 3600, 1) . "\"\n";
}
