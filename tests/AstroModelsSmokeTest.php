<?php

require_once __DIR__ . '/bootstrap.php';

use Swisseph\Constants;

/**
 * Smoke test for astronomical models functions
 * Tests swe_set_astro_models() and swe_get_astro_models()
 *
 * Note: swe_get_astro_models() does NOT return the model string in $samod.
 * In C code (swephlib.c:4442-4443), the line `strcpy(samod, samod0)` is commented out.
 * $samod serves only as optional INPUT to trigger swe_set_astro_models().
 * The only OUTPUT is $sdet (detailed description).
 */

echo "=== Astronomical Models Smoke Test ===\n\n";

// Test 1: Set SE 2.06 models and verify via detailed description
echo "Test 1: Set models by version (SE2.06)\n";
swe_set_astro_models("SE2.06", 0);

$samod1 = "";
$sdet1 = "";
swe_get_astro_models($samod1, $sdet1, 0);

echo "Detailed description (excerpt):\n";
$lines = explode("\n", $sdet1);
foreach (array_slice($lines, 0, 5) as $line) {
    echo "  $line\n";
}

if (strpos($sdet1, "Stephenson/Morrison/Hohenkerk 2016") !== false) {
    echo "✓ PASS: Delta T model is Stephenson/Morrison/Hohenkerk 2016\n";
} else {
    echo "✗ FAIL: Expected 'Stephenson/Morrison/Hohenkerk 2016' in description\n";
}

if (strpos($sdet1, "Vondrák 2011") !== false) {
    echo "✓ PASS: Precession model is Vondrák 2011\n";
} else {
    echo "✗ FAIL: Expected 'Vondrák 2011' in description\n";
}
echo "\n";

// Test 2: Set Stephenson/Morrison 2004 (model 3) and verify
echo "Test 2: Set Delta T model to Stephenson/Morrison 2004\n";
swe_set_astro_models("3,9,9,4,3,0,0,4", 0);

$samod2 = "";
$sdet2 = "";
swe_get_astro_models($samod2, $sdet2, 0);

if (strpos($sdet2, "Stephenson/Morrison 2004") !== false) {
    echo "✓ PASS: Delta T model changed to Stephenson/Morrison 2004\n";
} else {
    echo "✗ FAIL: Expected 'Stephenson/Morrison 2004' in description\n";
    $lines = explode("\n", $sdet2);
    foreach ($lines as $line) {
        if (stripos($line, 'delta t') !== false) {
            echo "Got: $line\n";
            break;
        }
    }
}
echo "\n";

// Test 3: Set SE 1.80 (sidereal time model 1 = IAU 1976)
echo "Test 3: Set models by version (SE1.80)\n";
swe_set_astro_models("SE1.80", 0);

$samod3 = "";
$sdet3 = "";
swe_get_astro_models($samod3, $sdet3, 0);

if (strpos($sdet3, "Sidereal time: IAU 1976") !== false) {
    echo "✓ PASS: Sidereal time model is IAU 1976 (SE 1.80 default)\n";
} else {
    echo "✗ FAIL: Expected 'Sidereal time: IAU 1976' for SE 1.80\n";
    // Show last line (sidereal time line)
    $lines3 = explode("\n", trim($sdet3));
    echo "Got: " . end($lines3) . "\n";
}
echo "\n";

// Test 4: Verify all model types are present in description
echo "Test 4: Verify comprehensive model description\n";
swe_set_astro_models("SE2.06", 0);

$samod4 = "";
$sdet4 = "";
swe_get_astro_models($samod4, $sdet4, 0);

$requiredStrings = [
    'JPL eph.' => 'JPL ephemeris number',
    'tidal acc.' => 'Tidal acceleration',
    'Delta T' => 'Delta T model',
    'Precession' => 'Precession model',
    'Nutation' => 'Nutation model',
    'Frame bias' => 'Frame bias model',
    'Sidereal time' => 'Sidereal time model',
];

$passCount = 0;
foreach ($requiredStrings as $needle => $description) {
    if (strpos($sdet4, $needle) !== false) {
        $passCount++;
    } else {
        echo "✗ FAIL: Missing '$description' in output\n";
    }
}

if ($passCount === count($requiredStrings)) {
    echo "✓ PASS: All {$passCount} model types present in description\n";
} else {
    echo "✗ FAIL: Only {$passCount}/" . count($requiredStrings) . " model types found\n";
}
echo "\n";

// Test 5: Empty string should use current version (SE 2.10)
echo "Test 5: Set models with empty string (uses current version)\n";
swe_set_astro_models("", 0);

$samod5 = "";
$sdet5 = "";
swe_get_astro_models($samod5, $sdet5, 0);

// Should use SE 2.06+ defaults (Stephenson 2016, Vondrák 2011, etc.)
if (strpos($sdet5, "Stephenson/Morrison/Hohenkerk 2016") !== false &&
    strpos($sdet5, "Vondrák 2011") !== false) {
    echo "✓ PASS: Empty string uses SE 2.06+ defaults\n";
} else {
    echo "⚠ WARNING: Empty string may use different defaults\n";
    echo "Delta T: " . explode("\n", $sdet5)[1] . "\n";
}

echo "\n=== Test Complete ===\n";
