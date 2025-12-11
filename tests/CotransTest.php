<?php
/**
 * Comprehensive test for swe_cotrans() function
 * Tests polar coordinate transformations with various angles and coordinate systems
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== swe_cotrans() Comprehensive Test ===\n\n";

// Test 1: Identity transformation (angle = 0)
echo "--- Test 1: Identity (angle=0) ---\n";
$xin = [45.0, 30.0, 1.0];
$xout = [0.0, 0.0, 0.0];
swe_cotrans($xin, $xout, 0.0);
echo sprintf("Input:  [%.6f, %.6f, %.6f]\n", $xin[0], $xin[1], $xin[2]);
echo sprintf("Output: [%.6f, %.6f, %.6f]\n", $xout[0], $xout[1], $xout[2]);
$err = abs($xout[0] - $xin[0]) + abs($xout[1] - $xin[1]);
echo $err < 0.000001 ? "✓ PASS\n\n" : "✗ FAIL: Error = $err\n\n";

// Test 2: 90° rotation of point on Y-axis
echo "--- Test 2: 90° rotation ---\n";
$xin = [90.0, 0.0, 1.0];  // lon=90° (on Y-axis), lat=0, r=1
$xout = [0.0, 0.0, 0.0];
swe_cotrans($xin, $xout, 90.0);
echo sprintf("Input:  [%.6f, %.6f, %.6f]\n", $xin[0], $xin[1], $xin[2]);
echo sprintf("Output: [%.6f, %.6f, %.6f]\n", $xout[0], $xout[1], $xout[2]);
// Point on Y-axis at equator, after 90° X-rotation: should reach pole (|lat|=90)
$err = abs(abs($xout[1]) - 90.0);
echo $err < 0.001 ? "✓ PASS\n\n" : "✗ FAIL: Expected |lat|=90, got {$xout[1]}\n\n";

// Test 3: Double rotation should give identity
echo "--- Test 3: +90° then -90° (round-trip) ---\n";
$xin = [120.0, 45.0, 1.0];
$xtemp = [0.0, 0.0, 0.0];
$xout = [0.0, 0.0, 0.0];
swe_cotrans($xin, $xtemp, 90.0);
swe_cotrans($xtemp, $xout, -90.0);
echo sprintf("Input:       [%.6f, %.6f, %.6f]\n", $xin[0], $xin[1], $xin[2]);
echo sprintf("After +90°:  [%.6f, %.6f, %.6f]\n", $xtemp[0], $xtemp[1], $xtemp[2]);
echo sprintf("After -90°:  [%.6f, %.6f, %.6f]\n", $xout[0], $xout[1], $xout[2]);
$err_lon = abs($xout[0] - $xin[0]);
$err_lat = abs($xout[1] - $xin[1]);
$err = $err_lon + $err_lat;
echo $err < 0.001 ? "✓ PASS\n\n" : "✗ FAIL: Error = $err (lon: $err_lon, lat: $err_lat)\n\n";

// Test 4: Ecliptic to Equatorial (obliquity ~23.44°)
echo "--- Test 4: Ecliptic→Equatorial conversion ---\n";
$eps = 23.43929;  // Mean obliquity J2000
$ecl = [0.0, 0.0, 1.0];  // Ecliptic: lon=0 (spring equinox), lat=0
$equ = [0.0, 0.0, 0.0];
swe_cotrans($ecl, $equ, -$eps);  // Negative for ECL→EQU
echo sprintf("Ecliptic [%.6f, %.6f] → Equatorial [%.6f, %.6f]\n",
    $ecl[0], $ecl[1], $equ[0], $equ[1]);
// At vernal equinox: RA=0, Dec=0
$err = abs($equ[0]) + abs($equ[1]);
echo $err < 0.000001 ? "✓ PASS (spring equinox maps correctly)\n\n"
    : "✗ FAIL: Expected RA=0 Dec=0, got RA={$equ[0]} Dec={$equ[1]}\n\n";

// Test 5: North ecliptic pole → Equatorial
echo "--- Test 5: North ecliptic pole → Equatorial ---\n";
$ecl = [0.0, 90.0, 1.0];  // North ecliptic pole
$equ = [0.0, 0.0, 0.0];
swe_cotrans($ecl, $equ, -$eps);
echo sprintf("Ecliptic pole lat=90° → Dec=%.6f°\n", $equ[1]);
// North ecliptic pole should have Dec = 90 - obliquity = 66.56°
$expected_dec = 90.0 - $eps;
$err = abs($equ[1] - $expected_dec);
echo $err < 0.001 ? "✓ PASS\n\n" : "✗ FAIL: Expected Dec=$expected_dec, got {$equ[1]}\n\n";

// Test 6: Radius preservation
echo "--- Test 6: Radius preservation ---\n";
$xin = [100.0, -20.0, 5.5];  // Custom radius
$xout = [0.0, 0.0, 0.0];
swe_cotrans($xin, $xout, 45.0);
echo sprintf("Input radius:  %.6f\n", $xin[2]);
echo sprintf("Output radius: %.6f\n", $xout[2]);
$err = abs($xout[2] - $xin[2]);
echo $err < 0.000001 ? "✓ PASS (radius preserved)\n\n"
    : "✗ FAIL: Radius changed by $err\n\n";

// Test 7: In-place transformation
echo "--- Test 7: In-place transformation ---\n";
$x = [150.0, 60.0, 2.0];
$x_copy = [$x[0], $x[1], $x[2]];
swe_cotrans($x, $x, 30.0);  // In-place
echo sprintf("Original: [%.6f, %.6f, %.6f]\n", $x_copy[0], $x_copy[1], $x_copy[2]);
echo sprintf("Modified: [%.6f, %.6f, %.6f]\n", $x[0], $x[1], $x[2]);
// Verify it's actually different (unless angle=0)
$changed = (abs($x[1] - $x_copy[1]) > 0.001);
echo $changed ? "✓ PASS (in-place modification works)\n\n"
    : "✗ FAIL: No modification detected\n\n";

// Test 8: Multiple transformations compose correctly
echo "--- Test 8: Transformation composition ---\n";
$xin = [45.0, 30.0, 1.0];
$x1 = [0.0, 0.0, 0.0];
$x2 = [0.0, 0.0, 0.0];
$x3 = [0.0, 0.0, 0.0];

// Method 1: Single 60° rotation
swe_cotrans($xin, $x1, 60.0);

// Method 2: Two 30° rotations
swe_cotrans($xin, $x2, 30.0);
swe_cotrans($x2, $x2, 30.0);

// Method 3: 45° + 15° rotations
swe_cotrans($xin, $x3, 45.0);
swe_cotrans($x3, $x3, 15.0);

echo sprintf("Direct 60°:      [%.6f, %.6f]\n", $x1[0], $x1[1]);
echo sprintf("30° + 30°:       [%.6f, %.6f]\n", $x2[0], $x2[1]);
echo sprintf("45° + 15°:       [%.6f, %.6f]\n", $x3[0], $x3[1]);

$err12 = abs($x1[0] - $x2[0]) + abs($x1[1] - $x2[1]);
$err13 = abs($x1[0] - $x3[0]) + abs($x1[1] - $x3[1]);
$max_err = max($err12, $err13);

echo $max_err < 0.001 ? "✓ PASS (compositions match)\n\n"
    : "✗ FAIL: Composition error = $max_err\n\n";

echo "=== All swe_cotrans tests completed ===\n";
