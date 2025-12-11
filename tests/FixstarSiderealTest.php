<?php
/**
 * Test sidereal fixed star positions
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\SwephFile\SwedState;
use Swisseph\Swe\Functions\FixstarFunctions;
use Swisseph\SiderealMode;
use Swisseph\Constants;

// Configure ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
if (is_dir($ephePath)) {
    SwedState::getInstance()->setEphePath($ephePath);
    echo "Ephemeris path set to: $ephePath\n\n";
} else {
    echo "Warning: Ephemeris path not found: $ephePath\n\n";
}

echo "=== Swiss Ephemeris Sidereal Fixed Stars Test ===\n\n";

// Test date: 2025-01-01 00:00 UT
$tjdUt = 2460676.5;

// Test 1: Tropical position (baseline)
echo "Test 1: Tropical Position (Spica)\n";
// Tropical mode: no SEFLG_SIDEREAL flag
$star = 'Spica';
$xx = [];
$serr = null;
$iflag = 0; // Geocentric, ecliptic, degrees (no sidereal flag)
$result = FixstarFunctions::fixstarUt($star, $tjdUt, $iflag, $xx, $serr);
if ($result === Constants::SE_ERR) {
    echo "  ERROR: " . ($serr ?? 'unknown error') . "\n";
} else {
    echo "  Tropical Longitude: " . sprintf("%.6f°", $xx[0]) . "\n";
}
echo "\n";

// Test 2: Sidereal Fagan-Bradley (traditional algorithm)
echo "Test 2: Sidereal Fagan-Bradley (Spica)\n";
SiderealMode::set(Constants::SE_SIDM_FAGAN_BRADLEY, 0.0, 0.0);
$star = 'Spica';
$xx = [];
$serr = null;
$iflag = Constants::SEFLG_SIDEREAL; // Sidereal mode
$result = FixstarFunctions::fixstarUt($star, $tjdUt, $iflag, $xx, $serr);
if ($result === Constants::SE_ERR) {
    echo "  ERROR: " . ($serr ?? 'unknown error') . "\n";
} else {
    echo "  Sidereal Longitude: " . sprintf("%.6f°", $xx[0]) . "\n";

    // Expected: Tropical 204.189° - Ayanamsa ~24.1° = ~180°
    echo "  (Expected: ~180°, Spica is close to 0° Libra sidereal)\n";
}
echo "\n";

// Test 3: Sidereal Lahiri
echo "Test 3: Sidereal Lahiri (Spica)\n";
SiderealMode::set(Constants::SE_SIDM_LAHIRI, 0.0, 0.0);
$star = 'Spica';
$xx = [];
$serr = null;
$iflag = Constants::SEFLG_SIDEREAL;
$result = FixstarFunctions::fixstarUt($star, $tjdUt, $iflag, $xx, $serr);
if ($result === Constants::SE_ERR) {
    echo "  ERROR: " . ($serr ?? 'unknown error') . "\n";
} else {
    echo "  Sidereal Longitude: " . sprintf("%.6f°", $xx[0]) . "\n";
}
echo "\n";

// Test 4: Get ayanamsa value
echo "Test 4: Ayanamsa Values\n";
$tjdEt = $tjdUt + (68.999802 / 86400.0); // Add delta-T

SiderealMode::set(Constants::SE_SIDM_FAGAN_BRADLEY, 0.0, 0.0);
$ayan_fb = \Swisseph\Sidereal::ayanamshaDegFromJdTT($tjdEt);
echo "  Fagan-Bradley: " . sprintf("%.6f°", $ayan_fb) . "\n";

SiderealMode::set(Constants::SE_SIDM_LAHIRI, 0.0, 0.0);
$ayan_lahiri = \Swisseph\Sidereal::ayanamshaDegFromJdTT($tjdEt);
echo "  Lahiri: " . sprintf("%.6f°", $ayan_lahiri) . "\n";

echo "\n=== Test completed ===\n";
