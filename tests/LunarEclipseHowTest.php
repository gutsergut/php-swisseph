<?php

declare(strict_types=1);

/**
 * Lunar Eclipse How Test
 *
 * Tests swe_lun_eclipse_how() implementation for a known partial lunar eclipse.
 * Test case: Partial Lunar Eclipse 2024-09-18 (Saros 118)
 *
 * Reference data from NASA Eclipse Web Site:
 * https://eclipse.gsfc.nasa.gov/LEcat5/LE2021-2040.html
 * Greatest Eclipse: 02:44:13 UT
 * Umbral Magnitude: 0.085
 * Penumbral Magnitude: 1.091
 * WITHOUT SIMPLIFICATIONS - full validation
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== Lunar Eclipse How Test ===\n";
echo "Testing swe_lun_eclipse_how() for Partial Lunar Eclipse 2024-09-18\n\n";

// Test 1: Partial Lunar Eclipse 2024-09-18 02:44:13 UT (maximum)
// Location: Greenwich (0°, 51.5°N)
$tjd_ut = swe_julday(2024, 9, 18, 2.7369444444444, Constants::SE_GREG_CAL);
$geopos = [0.0, 51.5, 0.0]; // Greenwich (lon, lat, alt)

echo "=== Test 1: Partial Lunar Eclipse 2024-09-18 ===\n";
echo "Time: JD $tjd_ut (2024-09-18 02:44:13 UT)\n";
echo "Location: Greenwich (0.0°E, 51.5°N)\n";
echo "Expected Saros series: 118\n";
echo "Expected umbral magnitude: 0.085\n";
echo "Expected penumbral magnitude: 1.091\n\n";

$attr = [];
$serr = '';

$retflag = swe_lun_eclipse_how(
    $tjd_ut,
    Constants::SEFLG_SWIEPH,
    $geopos,
    $attr,
    $serr
);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Eclipse Type: ";
if ($retflag & Constants::SE_ECL_TOTAL) {
    echo "TOTAL ";
}
if ($retflag & Constants::SE_ECL_PARTIAL) {
    echo "PARTIAL ";
}
if ($retflag & Constants::SE_ECL_PENUMBRAL) {
    echo "PENUMBRAL ";
}
if ($retflag === 0) {
    echo "NO ECLIPSE";
}
echo "\n\n";

echo "=== Eclipse Attributes ===\n";
printf("Umbral magnitude:    %.4f\n", $attr[0]);
printf("Penumbral magnitude: %.4f\n", $attr[1]);
printf("Azimuth:             %.2f°\n", $attr[4]);
printf("True altitude:       %.2f°\n", $attr[5]);
printf("App. altitude:       %.2f°\n", $attr[6]);
printf("Distance from opp.:  %.2f°\n", $attr[7]);
printf("Umbral mag. (copy):  %.4f\n", $attr[8]);
printf("Saros series:        %.0f\n", $attr[9]);
printf("Saros member:        %.0f\n\n", $attr[10]);echo "\n";

// Validation
$success = true;

// Expected: Partial eclipse
if (!($retflag & Constants::SE_ECL_PARTIAL)) {
    echo "FAIL: Expected PARTIAL eclipse flag\n";
    $success = false;
}

// Expected: Umbral magnitude ~0.085 (±0.01)
if (abs($attr[0] - 0.085) > 0.01) {
    echo sprintf("FAIL: Umbral magnitude %.4f differs from expected 0.085\n", $attr[0]);
    $success = false;
}

// Expected: Penumbral magnitude ~1.091 (±0.06, 5% tolerance)
if (abs($attr[1] - 1.091) > 0.06) {
    echo sprintf("FAIL: Penumbral magnitude %.4f differs from expected 1.091\n", $attr[1]);
    $success = false;
}

// Expected: Saros series 118
if (abs($attr[9] - 118) > 0.5) {
    echo sprintf("FAIL: Saros series %.0f differs from expected 118\n", $attr[9]);
    $success = false;
}

if ($success) {
    echo "✓ All validations PASSED\n";
} else {
    echo "✗ Some validations FAILED\n";
    exit(1);
}

echo "\n=== Test 2: Same eclipse without geopos ===\n";

$attr2 = [];
$serr2 = '';

$retflag2 = swe_lun_eclipse_how(
    $tjd_ut,
    Constants::SEFLG_SWIEPH,
    null, // No geopos
    $attr2,
    $serr2
);

if ($retflag2 === Constants::SE_ERR) {
    echo "ERROR: $serr2\n";
    exit(1);
}

echo "Eclipse Type: ";
if ($retflag2 & Constants::SE_ECL_TOTAL) {
    echo "TOTAL ";
}
if ($retflag2 & Constants::SE_ECL_PARTIAL) {
    echo "PARTIAL ";
}
if ($retflag2 & Constants::SE_ECL_PENUMBRAL) {
    echo "PENUMBRAL ";
}
echo "\n";
printf("Umbral magnitude:    %.4f\n", $attr2[0]);
printf("Penumbral magnitude: %.4f\n", $attr2[1]);
printf("Saros series:        %.0f\n", $attr2[9]);
printf("Saros member:        %.0f\n", $attr2[10]);

// Should match Test 1 magnitudes
if (abs($attr2[0] - $attr[0]) > 0.0001) {
    echo sprintf("FAIL: Magnitude differs with/without geopos: %.4f vs %.4f\n", $attr2[0], $attr[0]);
    exit(1);
}

echo "\n✓ TEST PASSED: Lunar eclipse 2024-09-18 calculation successful\n";
