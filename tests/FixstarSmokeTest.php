<?php
/**
 * Basic smoke test for swe_fixstar functions.
 * Tests star name search, magnitude retrieval, and position calculation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\FixstarFunctions;
use Swisseph\Constants;
use Swisseph\Error;
use Swisseph\SwephFile\SwedState;

// Set ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
if (is_dir($ephePath)) {
    SwedState::getInstance()->setEphePath($ephePath);
    echo "Ephemeris path set to: $ephePath\n\n";
} else {
    echo "Warning: Ephemeris path not found: $ephePath\n\n";
}

echo "=== Swiss Ephemeris Fixed Stars Test ===\n\n";

// Test date: 2025-01-01 00:00 UT (to match swetest reference)
$tjdUt = 2460676.5;
$iflag = 0; // Default geocentric, ecliptic, degrees

// Test 1: Get magnitude of Spica (built-in star)
echo "Test 1: Star Magnitude (Spica)\n";
$star = 'Spica';
$mag = 0.0;
$serr = null;
$result = FixstarFunctions::fixstarMag($star, $mag, $serr);
if ($result === Constants::SE_ERR) {
    echo "  ERROR: " . ($serr ?? 'unknown error') . "\n";
} else {
    echo "  Star: $star\n";
    printf("  Magnitude: %.2f\n", $mag);
}
echo "\n";

// Test 2: Calculate position of Spica
echo "Test 2: Star Position (Spica)\n";
$star = 'Spica';
$xx = [];
$serr = null;
$result = FixstarFunctions::fixstarUt($star, $tjdUt, $iflag, $xx, $serr);
if ($result === Constants::SE_ERR) {
    echo "  ERROR: " . ($serr ?? 'unknown error') . "\n";
} else {
    echo "  Star: $star\n";
    printf("  Longitude: %.6f°\n", $xx[0]);
    printf("  Latitude:  %.6f°\n", $xx[1]);
    printf("  Distance:  %.6f AU\n", $xx[2]);
}
echo "\n";

// Test 3: Try searching by Bayer designation
echo "Test 3: Bayer Designation (alpha Virginis)\n";
$star = ',alVir';
$mag = 0.0;
$serr = null;
$result = FixstarFunctions::fixstarMag($star, $mag, $serr);
if ($result === Constants::SE_ERR) {
    echo "  ERROR: " . ($serr ?? 'unknown error') . "\n";
} else {
    echo "  Star: $star\n";
    printf("  Magnitude: %.2f\n", $mag);
}
echo "\n";

// Test 4: Test caching (call same star twice)
echo "Test 4: Caching Test (Spica twice)\n";
$start = microtime(true);
$star1 = 'Spica';
$xx1 = [];
FixstarFunctions::fixstarUt($star1, $tjdUt, $iflag, $xx1, $serr);
$time1 = microtime(true) - $start;

$start = microtime(true);
$star2 = 'Spica';
$xx2 = [];
FixstarFunctions::fixstarUt($star2, $tjdUt, $iflag, $xx2, $serr);
$time2 = microtime(true) - $start;

echo "  First call:  {$time1}s\n";
echo "  Second call: {$time2}s\n";
if ($time2 < $time1) {
    echo "  ✓ Caching appears to work (second call faster)\n";
}
echo "\n";

// Test 5: Error handling - invalid star name
echo "Test 5: Error Handling (invalid star)\n";
$star = 'InvalidStarXYZ123';
$xx = [];
$serr = null;
$result = FixstarFunctions::fixstarUt($star, $tjdUt, $iflag, $xx, $serr);
if ($result === Constants::SE_ERR) {
    echo "  ✓ Expected error: " . ($serr ?? 'star not found') . "\n";
} else {
    echo "  ✗ Should have returned an error\n";
}
echo "\n";

echo "=== Test completed ===\n";
