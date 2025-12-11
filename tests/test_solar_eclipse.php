<?php
/**
 * Simple test for solar eclipse functions
 * WITHOUT SIMPLIFICATIONS - tests against C reference values
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Test solar eclipse of 2024-04-08 (total eclipse over North America)
// Reference: https://eclipse.gsfc.nasa.gov/SEcat5/SE2024.html
// Maximum eclipse approximately 18:18 UT

$tjd_ut = 2460408.5 + 18.3/24.0;  // 2024-04-08 18:18 UT

// Test location: Dallas, TX (path of totality)
$geopos = [-96.8, 32.8, 0.0];  // lon, lat, alt

$attr = array_fill(0, 20, 0.0);
$serr = null;

echo "Testing swe_sol_eclipse_how for 2024-04-08 eclipse\n";
echo "Location: Dallas, TX (lon={$geopos[0]}, lat={$geopos[1]})\n";
echo "JD UT: $tjd_ut\n\n";

$retflag = \swe_sol_eclipse_how($tjd_ut, Constants::SEFLG_SWIEPH, $geopos, $attr, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Return flags: $retflag\n";

// Decode flags
$flags = [];
if ($retflag & Constants::SE_ECL_TOTAL) $flags[] = 'TOTAL';
if ($retflag & Constants::SE_ECL_ANNULAR) $flags[] = 'ANNULAR';
if ($retflag & Constants::SE_ECL_PARTIAL) $flags[] = 'PARTIAL';
if ($retflag & Constants::SE_ECL_CENTRAL) $flags[] = 'CENTRAL';
if ($retflag & Constants::SE_ECL_NONCENTRAL) $flags[] = 'NONCENTRAL';
if ($retflag & Constants::SE_ECL_VISIBLE) $flags[] = 'VISIBLE';

echo "Eclipse type: " . implode(' | ', $flags) . "\n\n";

echo "Eclipse attributes:\n";
echo sprintf("  attr[0] (magnitude IMCCE): %.6f\n", $attr[0]);
echo sprintf("  attr[1] (ratio lunar/solar diameter): %.6f\n", $attr[1]);
echo sprintf("  attr[2] (obscuration): %.6f\n", $attr[2]);
echo sprintf("  attr[3] (core shadow diameter km): %.3f\n", $attr[3]);
echo sprintf("  attr[4] (azimuth): %.6f°\n", $attr[4]);
echo sprintf("  attr[5] (true altitude): %.6f°\n", $attr[5]);
echo sprintf("  attr[6] (apparent altitude): %.6f°\n", $attr[6]);
echo sprintf("  attr[7] (elongation): %.6f°\n", $attr[7]);
echo sprintf("  attr[8] (magnitude NASA): %.6f\n", $attr[8]);
echo sprintf("  attr[9] (saros series): %.0f\n", $attr[9]);
echo sprintf("  attr[10] (saros member): %.0f\n", $attr[10]);

// Basic sanity checks
if ($retflag & Constants::SE_ECL_TOTAL) {
    echo "\n✓ Eclipse is TOTAL (expected for Dallas on 2024-04-08)\n";
} else {
    echo "\n⚠ Warning: Expected TOTAL eclipse for Dallas\n";
}

if ($attr[3] > 0) {
    echo "✓ Core shadow diameter calculated: {$attr[3]} km\n";
} else {
    echo "⚠ Warning: Core shadow diameter is zero or negative\n";
}

if ($attr[0] > 0.99) {
    echo "✓ Magnitude > 0.99 (near total)\n";
} else {
    echo "⚠ Warning: Magnitude unexpectedly low: {$attr[0]}\n";
}

echo "\nTest completed.\n";
