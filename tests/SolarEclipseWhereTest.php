<?php

declare(strict_types=1);

/**
 * Solar Eclipse Where Test
 *
 * Tests swe_sol_eclipse_where() - find geographic position of solar eclipse maximum
 * WITHOUT SIMPLIFICATIONS - validates full algorithm against known eclipses
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== Solar Eclipse Where Test ===\n";
echo "Testing swe_sol_eclipse_where() - find eclipse center line\n\n";

// Test 1: Total solar eclipse of 2024-04-08
// Known data: totality path crosses Mexico, USA, Canada
// Maximum eclipse: near Nazas, Mexico
echo "=== Test 1: Total Solar Eclipse 2024-04-08 ===\n";
$tjd = swe_julday(2024, 4, 8, 18.0, Constants::SE_GREG_CAL); // Approx time of maximum
$geopos = [];
$attr = [];
$serr = '';

$retflag = swe_sol_eclipse_where(
    $tjd,
    Constants::SEFLG_SWIEPH,
    $geopos,
    $attr,
    $serr
);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

if ($retflag === 0) {
    echo "FAIL: No eclipse found at this time\n";
    exit(1);
}

// Output results
echo "Eclipse Type: ";
if ($retflag & Constants::SE_ECL_TOTAL) {
    echo "TOTAL ";
}
if ($retflag & Constants::SE_ECL_ANNULAR) {
    echo "ANNULAR ";
}
if ($retflag & Constants::SE_ECL_PARTIAL) {
    echo "PARTIAL ";
}
if ($retflag & Constants::SE_ECL_CENTRAL) {
    echo "(CENTRAL) ";
}
if ($retflag & Constants::SE_ECL_NONCENTRAL) {
    echo "(NON-CENTRAL) ";
}
echo "\n";

printf("Geographic Position of Maximum:\n");
printf("  Longitude: %.4f° %s\n", abs($geopos[0]), $geopos[0] >= 0 ? 'E' : 'W');
printf("  Latitude: %.4f° %s\n", abs($geopos[1]), $geopos[1] >= 0 ? 'N' : 'S');

printf("\nEclipse Characteristics:\n");
printf("  Magnitude: %.4f (fraction of diameter covered)\n", $attr[0]);
printf("  Moon/Sun diameter ratio: %.4f\n", $attr[1]);
printf("  Obscuration: %.4f (fraction of area covered)\n", $attr[2]);
printf("  Core shadow diameter: %.2f km\n", $attr[3]);

// Expected: Should be near Mexico (around 104°W, 25°N)
// NASA data: Maximum at 25.3°N, 104.1°W at 18:17:16 UT
echo "\n=== Validation ===\n";
if ($retflag & Constants::SE_ECL_TOTAL) {
    echo "✓ Eclipse type is TOTAL\n";
} else {
    echo "✗ Expected TOTAL eclipse\n";
}

if ($geopos[0] < -100 && $geopos[0] > -110) {
    echo "✓ Longitude in expected range (around 104°W)\n";
} else {
    echo "✗ Longitude outside expected range\n";
}

if ($geopos[1] > 20 && $geopos[1] < 30) {
    echo "✓ Latitude in expected range (around 25°N)\n";
} else {
    echo "✗ Latitude outside expected range\n";
}

if ($attr[3] < 0) {
    echo "✓ Core shadow diameter negative (total eclipse)\n";
} else {
    echo "✗ Expected negative shadow diameter for total eclipse\n";
}

echo "\n✓ Test completed\n";
