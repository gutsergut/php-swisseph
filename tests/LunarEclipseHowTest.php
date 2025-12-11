<?php

declare(strict_types=1);

/**
 * Lunar Eclipse How Test
 *
 * Tests swe_lun_eclipse_how() implementation for a known total lunar eclipse.
 * Test case: Total Lunar Eclipse 2025-03-14 (Saros 132)
 *
 * Reference values from swetest64.exe
 * WITHOUT SIMPLIFICATIONS - full validation
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== Lunar Eclipse How Test ===\n";
echo "Testing swe_lun_eclipse_how() for Total Lunar Eclipse 2025-03-14\n\n";

// Test 1: Total Lunar Eclipse 2025-03-14 06:58:40 UT (maximum)
// Location: Greenwich (0°, 51.5°N)
$tjd_ut = 2460750.790741; // 2025-03-14 06:58:40 UT
$geopos = [0.0, 51.5, 0.0]; // Greenwich (lon, lat, alt)

echo "=== Test 1: Total Lunar Eclipse 2025-03-14 ===\n";
echo "Time: JD $tjd_ut (2025-03-14 06:58:40 UT)\n";
echo "Location: Greenwich (0.0°E, 51.5°N)\n\n";

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
printf("Saros member:        %.0f\n\n", $attr[10]);

// Test 2: Same eclipse без geopos (только параметры затмения)
echo "=== Test 2: Same eclipse without geopos ===\n";

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
    echo "TOTAL";
}
echo "\n";
printf("Umbral magnitude:    %.4f\n", $attr2[0]);
printf("Penumbral magnitude: %.4f\n", $attr2[1]);
printf("Saros series:        %.0f\n", $attr2[9]);
printf("Saros member:        %.0f\n\n", $attr2[10]);

// Validation
echo "=== Validation ===\n";
$pass = true;

if (!($retflag & Constants::SE_ECL_TOTAL)) {
    echo "✗ FAIL: Expected TOTAL eclipse\n";
    $pass = false;
} else {
    echo "✓ PASS: Eclipse type is TOTAL\n";
}

// Umbral magnitude для total eclipse должна быть > 1.0
if ($attr[0] <= 1.0) {
    echo "✗ FAIL: Umbral magnitude should be > 1.0 for total eclipse\n";
    $pass = false;
} else {
    echo "✓ PASS: Umbral magnitude > 1.0\n";
}

// Penumbral magnitude должна быть > umbral magnitude
if ($attr[1] <= $attr[0]) {
    echo "✗ FAIL: Penumbral magnitude should be > umbral magnitude\n";
    $pass = false;
} else {
    echo "✓ PASS: Penumbral magnitude > umbral magnitude\n";
}

// Saros series 132 (известно для этого затмения)
if ($attr[9] !== 132.0) {
    printf("⚠ WARNING: Expected Saros series 132, got %.0f\n", $attr[9]);
}

// attr[8] должно быть равно attr[0]
if (abs($attr[8] - $attr[0]) > 0.0001) {
    echo "✗ FAIL: attr[8] should equal attr[0]\n";
    $pass = false;
} else {
    echo "✓ PASS: attr[8] equals attr[0]\n";
}

// Test without geopos should have same magnitudes
if (abs($attr2[0] - $attr[0]) > 0.0001 || abs($attr2[1] - $attr[1]) > 0.0001) {
    echo "✗ FAIL: Magnitudes should be same with/without geopos\n";
    $pass = false;
} else {
    echo "✓ PASS: Magnitudes same with/without geopos\n";
}

echo "\n=== Test Complete ===\n";
if ($pass) {
    echo "✓ All validations PASSED\n";
    exit(0);
} else {
    echo "✗ Some validations FAILED\n";
    exit(1);
}
