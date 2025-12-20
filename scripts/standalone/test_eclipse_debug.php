<?php
/**
 * Debug test for eclipse_where() function
 * Compare with swetest64.exe reference data
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Eclipses\EclipseCalculator;

// Set ephemeris path
\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Eclipse Where Debug Test ===\n\n";

// Test 1: Global maximum (from swetest64: -solecl -b1.1.2024)
// Reference: total solar 8.04.2024 18:17:20.4 -189.355755 km
//            Coordinates: -104° 9'15", 25°17'8"
//            JD: 2460409.262041

$tjd_ut_global = 2460409.262041;
echo "Test 1: Global maximum eclipse\n";
echo "JD UT: $tjd_ut_global (2024-04-08 18:17:20 UT)\n";
echo "Reference (swetest64):\n";
echo "  Type: total\n";
echo "  Core shadow: -189.355755 km (negative = total)\n";
echo "  Coordinates: -104° 9'15\", 25°17'8\"\n\n";

$geopos = array_fill(0, 20, 0.0);
$dcore = array_fill(0, 10, 0.0);
$serr = null;

$retflag = EclipseCalculator::eclipseWhere(
    $tjd_ut_global,
    Constants::SE_SUN,
    null,
    Constants::SEFLG_SWIEPH,
    $geopos,
    $dcore,
    $serr
);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "PHP Result:\n";
echo sprintf("  Return flags: %d\n", $retflag);

$flags = [];
if ($retflag & Constants::SE_ECL_TOTAL) $flags[] = 'TOTAL';
if ($retflag & Constants::SE_ECL_ANNULAR) $flags[] = 'ANNULAR';
if ($retflag & Constants::SE_ECL_PARTIAL) $flags[] = 'PARTIAL';
if ($retflag & Constants::SE_ECL_CENTRAL) $flags[] = 'CENTRAL';
if ($retflag & Constants::SE_ECL_NONCENTRAL) $flags[] = 'NONCENTRAL';
echo "  Type: " . implode(' | ', $flags) . "\n";

echo sprintf("  Coordinates: %.6f°, %.6f°\n", $geopos[0], $geopos[1]);
echo "  Core shadow: " . sprintf("%.6f km\n", $dcore[0]);
echo "  Penumbra: " . sprintf("%.6f km\n", $dcore[1]);
echo "  Distance shadow axis from geocenter: " . sprintf("%.6f km\n", $dcore[2]);
echo "  Core shadow on fundamental plane: " . sprintf("%.6f km\n", $dcore[3]);
echo "  Penumbra on fundamental plane: " . sprintf("%.6f km\n", $dcore[4]);
echo sprintf("  cosf1: %.9f\n", $dcore[5]);
echo sprintf("  cosf2: %.9f\n", $dcore[6]);

// Compare
$ref_lon = -104.0 - 9.0/60.0 - 15.0/3600.0;  // -104° 9'15"
$ref_lat = 25.0 + 17.0/60.0 + 8.0/3600.0;     // 25°17'8"
$ref_core = -189.355755;

echo "\nComparison:\n";
echo sprintf("  Longitude diff: %.6f° (ref: %.6f°)\n", abs($geopos[0] - $ref_lon), $ref_lon);
echo sprintf("  Latitude diff: %.6f° (ref: %.6f°)\n", abs($geopos[1] - $ref_lat), $ref_lat);
echo sprintf("  Core shadow diff: %.3f km (ref: %.3f km)\n", abs($dcore[0] - $ref_core), $ref_core);

if ($retflag & Constants::SE_ECL_TOTAL) {
    echo "✓ Type is TOTAL (matches reference)\n";
} else {
    echo "✗ Type is NOT TOTAL (should be TOTAL)\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Test 2: Dallas location (from swetest64: -solecl -local -geopos-96.8,32.8,0)
// This will test eclipse_how, not eclipse_where
echo "Test 2: Eclipse at Dallas, TX\n";
echo "Location: lon=-96.8°, lat=32.8°\n";
echo "Reference (swetest64):\n";
echo "  Type: total\n";
echo "  Time: 18:42:40.8 UT (JD 2460409.279639)\n";
echo "  Magnitude: 1.0567 (IMCCE) / 1.0147 (NASA)\n\n";

$tjd_ut_dallas = 2460409.279639;
$geopos_dallas = [-96.8, 32.8, 0.0];
$attr = array_fill(0, 20, 0.0);
$serr = null;

$retflag2 = \swe_sol_eclipse_how($tjd_ut_dallas, Constants::SEFLG_SWIEPH, $geopos_dallas, $attr, $serr);

if ($retflag2 < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "PHP Result:\n";
echo sprintf("  Return flags: %d\n", $retflag2);

$flags2 = [];
if ($retflag2 & Constants::SE_ECL_TOTAL) $flags2[] = 'TOTAL';
if ($retflag2 & Constants::SE_ECL_ANNULAR) $flags2[] = 'ANNULAR';
if ($retflag2 & Constants::SE_ECL_PARTIAL) $flags2[] = 'PARTIAL';
if ($retflag2 & Constants::SE_ECL_CENTRAL) $flags2[] = 'CENTRAL';
if ($retflag2 & Constants::SE_ECL_NONCENTRAL) $flags2[] = 'NONCENTRAL';
echo "  Type: " . implode(' | ', $flags2) . "\n";

echo sprintf("  Magnitude (IMCCE): %.6f (ref: 1.0567)\n", $attr[0]);
echo sprintf("  Ratio lunar/solar: %.6f (ref: ~1.056)\n", $attr[1]);
echo sprintf("  Obscuration: %.6f\n", $attr[2]);
echo sprintf("  Core shadow diameter: %.3f km\n", $attr[3]);

if ($retflag2 & Constants::SE_ECL_TOTAL) {
    echo "✓ Type is TOTAL (matches reference)\n";
} else {
    echo "✗ Type is NOT TOTAL (should be TOTAL)\n";
}

echo "\nTest completed.\n";
