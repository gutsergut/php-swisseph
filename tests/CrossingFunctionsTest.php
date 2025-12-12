<?php

/**
 * Test Crossing Functions - swe_solcross, swe_mooncross, swe_helio_cross
 *
 * Test longitude crossing calculations for Sun, Moon, and planets
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Initialize ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo str_repeat('=', 70) . "\n";
echo "Testing Crossing Functions\n";
echo str_repeat('=', 70) . "\n\n";

// Test 1: Sun crossing 0° Aries (Spring Equinox 2024)
echo "Test 1: Sun crossing 0° Aries (Spring Equinox)\n";
echo str_repeat('-', 70) . "\n";

$jdStart = swe_julday(2024, 3, 1, 0.0, Constants::SE_GREG_CAL);  // Start March 1
$serr = '';

// Find when Sun crosses 0° (Aries)
$jdCross = swe_solcross_ut(0.0, $jdStart, Constants::SEFLG_SWIEPH, $serr);

if ($jdCross < $jdStart) {
    echo "ERROR: $serr\n";
    exit(1);
}

list($y, $m, $d, $h) = swe_revjul($jdCross, Constants::SE_GREG_CAL);
$hours = floor($h);
$minutes = floor(($h - $hours) * 60);
$seconds = round((($h - $hours) * 60 - $minutes) * 60);

echo sprintf("  Sun crosses 0° Aries at:\n");
echo sprintf("  Date: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hours, $minutes, $seconds);
echo sprintf("  JD: %.6f\n", $jdCross);
echo sprintf("  Expected: ~2024-03-20 03:06 UT (Spring Equinox)\n");

// Verify position
$xx = [];
swe_calc_ut($jdCross, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx, $serr);
echo sprintf("  Sun longitude at crossing: %.6f° (should be ~0.0°)\n", $xx[0]);

if (abs($xx[0]) < 0.001) {
    echo "  ✓ Test 1 PASSED\n\n";
} else {
    echo "  ✗ Test 1 FAILED - longitude error\n\n";
}

// Test 2: Moon crossing 90° (First Quarter)
echo "Test 2: Moon crossing 90° longitude\n";
echo str_repeat('-', 70) . "\n";

$jdStart = swe_julday(2024, 4, 1, 0.0, Constants::SE_GREG_CAL);
$jdCross = swe_mooncross_ut(90.0, $jdStart, Constants::SEFLG_SWIEPH, $serr);

if ($jdCross < $jdStart) {
    echo "ERROR: $serr\n";
    exit(1);
}

list($y, $m, $d, $h) = swe_revjul($jdCross, Constants::SE_GREG_CAL);
$hours = floor($h);
$minutes = floor(($h - $hours) * 60);
$seconds = round((($h - $hours) * 60 - $minutes) * 60);

echo sprintf("  Moon crosses 90° at:\n");
echo sprintf("  Date: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hours, $minutes, $seconds);
echo sprintf("  JD: %.6f\n", $jdCross);

swe_calc_ut($jdCross, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xx, $serr);
echo sprintf("  Moon longitude at crossing: %.6f° (should be ~90.0°)\n", $xx[0]);

if (abs($xx[0] - 90.0) < 0.001) {
    echo "  ✓ Test 2 PASSED\n\n";
} else {
    echo "  ✗ Test 2 FAILED - longitude error\n\n";
}

// Test 3: Moon crossing node (zero latitude)
echo "Test 3: Moon crossing node (zero latitude)\n";
echo str_repeat('-', 70) . "\n";

$jdStart = swe_julday(2024, 4, 1, 0.0, Constants::SE_GREG_CAL);
$xlon = 0.0;
$xlat = 0.0;

$jdNode = swe_mooncross_node_ut($jdStart, Constants::SEFLG_SWIEPH, $xlon, $xlat, $serr);

if ($jdNode < $jdStart) {
    echo "ERROR: $serr\n";
    exit(1);
}

list($y, $m, $d, $h) = swe_revjul($jdNode, Constants::SE_GREG_CAL);
$hours = floor($h);
$minutes = floor(($h - $hours) * 60);
$seconds = round((($h - $hours) * 60 - $minutes) * 60);

echo sprintf("  Moon crosses node at:\n");
echo sprintf("  Date: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hours, $minutes, $seconds);
echo sprintf("  JD: %.6f\n", $jdNode);
echo sprintf("  Longitude at node: %.6f°\n", $xlon);
echo sprintf("  Latitude at node: %.8f° (should be ~0.0°)\n", $xlat);

if (abs($xlat) < 0.001) {
    echo "  ✓ Test 3 PASSED\n\n";
} else {
    echo "  ✗ Test 3 FAILED - latitude error\n\n";
}

// Test 4: Mars heliocentric crossing 180° (opposition point)
echo "Test 4: Mars heliocentric crossing 180°\n";
echo str_repeat('-', 70) . "\n";

$jdStart = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$jdCross = 0.0;

$retval = swe_helio_cross_ut(
    Constants::SE_MARS,
    180.0,
    $jdStart,
    Constants::SEFLG_SWIEPH,
    0,  // forward search
    $jdCross,
    $serr
);

if ($retval === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

list($y, $m, $d, $h) = swe_revjul($jdCross, Constants::SE_GREG_CAL);
$hours = floor($h);
$minutes = floor(($h - $hours) * 60);
$seconds = round((($h - $hours) * 60 - $minutes) * 60);

echo sprintf("  Mars crosses 180° (helio) at:\n");
echo sprintf("  Date: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hours, $minutes, $seconds);
echo sprintf("  JD: %.6f\n", $jdCross);

// Verify heliocentric position
swe_calc_ut($jdCross, Constants::SE_MARS, Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR, $xx, $serr);
echo sprintf("  Mars helio longitude at crossing: %.6f° (should be ~180.0°)\n", $xx[0]);

if (abs($xx[0] - 180.0) < 0.01) {
    echo "  ✓ Test 4 PASSED\n\n";
} else {
    echo "  ✗ Test 4 FAILED - longitude error\n\n";
}

// Test 5: Jupiter heliocentric crossing 0° (backward search)
echo "Test 5: Jupiter heliocentric crossing 0° (backward search)\n";
echo str_repeat('-', 70) . "\n";

$jdStart = swe_julday(2024, 6, 1, 0.0, Constants::SE_GREG_CAL);
$jdCross = 0.0;

$retval = swe_helio_cross_ut(
    Constants::SE_JUPITER,
    0.0,
    $jdStart,
    Constants::SEFLG_SWIEPH,
    -1,  // backward search
    $jdCross,
    $serr
);

if ($retval === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

list($y, $m, $d, $h) = swe_revjul($jdCross, Constants::SE_GREG_CAL);
$hours = floor($h);
$minutes = floor(($h - $hours) * 60);
$seconds = round((($h - $hours) * 60 - $minutes) * 60);

echo sprintf("  Jupiter crosses 0° (helio, backward) at:\n");
echo sprintf("  Date: %04d-%02d-%02d %02d:%02d:%02d UT\n", $y, $m, $d, $hours, $minutes, $seconds);
echo sprintf("  JD: %.6f\n", $jdCross);

swe_calc_ut($jdCross, Constants::SE_JUPITER, Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR, $xx, $serr);
$lon_normalized = swe_degnorm($xx[0]);
echo sprintf("  Jupiter helio longitude at crossing: %.6f° (should be ~0.0° or ~360.0°)\n", $lon_normalized);

if ($lon_normalized < 0.01 || $lon_normalized > 359.99) {
    echo "  ✓ Test 5 PASSED\n\n";
} else {
    echo "  ✗ Test 5 FAILED - longitude error\n\n";
}

// Test 6: Error handling - helio_cross for Moon (should fail)
echo "Test 6: Error handling - helio_cross for Moon (should fail)\n";
echo str_repeat('-', 70) . "\n";

$jdCross = 0.0;
$retval = swe_helio_cross_ut(
    Constants::SE_MOON,  // Moon doesn't have heliocentric position
    90.0,
    $jdStart,
    Constants::SEFLG_SWIEPH,
    0,
    $jdCross,
    $serr
);

if ($retval === Constants::SE_ERR) {
    echo "  Expected error received: $serr\n";
    echo "  ✓ Test 6 PASSED (correct error handling)\n\n";
} else {
    echo "  ✗ Test 6 FAILED - should have returned error\n\n";
}

// Cleanup
swe_close();

echo str_repeat('=', 70) . "\n";
echo "ALL TESTS COMPLETED ✓\n";
echo str_repeat('=', 70) . "\n";
