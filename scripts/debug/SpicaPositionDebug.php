<?php
/**
 * Debug script to trace Spica position calculation step by step
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\SwephFile\SwedState;
use Swisseph\Swe\Functions\FixstarFunctions;

// Configure ephemeris path
SwedState::getInstance()->setEphePath(__DIR__ . '/../../eph/ephe');

// Test parameters
$star = 'Spica';
$jdUt = 2460676.5; // 2025-01-01 00:00 UT
$iflag = 0; // Geocentric, ecliptic, degrees

echo "=== Spica Position Debug ===\n\n";
echo "Date: 2025-01-01 00:00 UT\n";
echo "JD (UT): $jdUt\n\n";

// Calculate position
$serr = '';
$xx = [];
$retc = FixstarFunctions::fixstarUt($star, $jdUt, $iflag, $xx, $serr);

if ($retc < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

// Display result
echo "PHP Result:\n";
echo sprintf("  Longitude: %.10f°\n", $xx[0]);
echo sprintf("  Latitude:  %.10f°\n", $xx[1]);
echo sprintf("  Distance:  %.10f AU\n", $xx[2]);
echo "\n";

// Expected from swetest
echo "Expected (swetest):\n";
echo "  Longitude: 204.18903694°\n";
echo "  Latitude:  -2.05617556°\n";
echo "  Distance:  15793635.375364481 AU\n";
echo "\n";

// Calculate difference
$diffLon = abs($xx[0] - 204.18903694);
$diffLat = abs($xx[1] - (-2.05617556));
$diffDist = abs($xx[2] - 15793635.375364481);

echo "Differences:\n";
echo sprintf("  Longitude: %.10f° (%.2f arcsec)\n", $diffLon, $diffLon * 3600);
echo sprintf("  Latitude:  %.10f° (%.2f arcsec)\n", $diffLat, $diffLat * 3600);
echo sprintf("  Distance:  %.10f AU\n", $diffDist);
echo "\n";

// Assess accuracy
if ($diffLon * 3600 < 1.0) {
    echo "✓ Longitude accuracy: EXCELLENT (<1 arcsec)\n";
} elseif ($diffLon * 3600 < 10.0) {
    echo "✓ Longitude accuracy: GOOD (<10 arcsec)\n";
} elseif ($diffLon * 3600 < 60.0) {
    echo "⚠ Longitude accuracy: MODERATE (<1 arcmin)\n";
} else {
    echo "✗ Longitude accuracy: POOR (>1 arcmin)\n";
}
