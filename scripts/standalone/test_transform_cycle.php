<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Coordinates;
use Swisseph\Obliquity;
use Swisseph\Precession;
use Swisseph\Constants;

$jd = 2451545.0; // J2000

echo "=== Testing Full Coordinate Transformation Cycle ===\n\n";
echo "Date: JD $jd (J2000.0)\n\n";

// Start with Jupiter's mean ascending node from table
$lon_deg = 100.464441; // from VSOP87 table
$lat_deg = 0.0;
$dist = 5.2; // approximate

echo "Input (from VSOP87 table):\n";
echo "  Longitude: $lon_deg°\n";
echo "  Latitude:  $lat_deg°\n";
echo "  Distance:  $dist AU\n\n";

// Step 1: Convert to radians
$lon_rad = deg2rad($lon_deg);
$lat_rad = deg2rad($lat_deg);

echo "Step 1: Convert to radians\n";
echo "  Longitude: $lon_rad rad\n";
echo "  Latitude:  $lat_rad rad\n\n";

// Step 2: Polar to Cartesian
$pol = [$lon_rad, $lat_rad, $dist];
$cart = [];
Coordinates::polCart($pol, $cart);

echo "Step 2: Polar → Cartesian\n";
printf("  X: %.10f\n", $cart[0]);
printf("  Y: %.10f\n", $cart[1]);
printf("  Z: %.10f\n\n", $cart[2]);

// Step 3: Ecliptic → Equator (rotate by -obliquity)
$eps = Obliquity::meanObliquityRadFromJdTT($jd);
$seps = sin($eps);
$ceps = cos($eps);

$cartEq = [];
Coordinates::coortrf2($cart, $cartEq, -$seps, $ceps);

echo "Step 3: Ecliptic → Equator (rotate by -obliquity)\n";
echo "  Obliquity: " . rad2deg($eps) . "°\n";
printf("  X: %.10f\n", $cartEq[0]);
printf("  Y: %.10f\n", $cartEq[1]);
printf("  Z: %.10f\n\n", $cartEq[2]);

// Step 4: Precess to J2000
$cartJ2000 = [$cartEq[0], $cartEq[1], $cartEq[2]];
Precession::precess($cartJ2000, $jd, 0, Constants::J_TO_J2000);

echo "Step 4: Precess to J2000\n";
printf("  X: %.10f\n", $cartJ2000[0]);
printf("  Y: %.10f\n", $cartJ2000[1]);
printf("  Z: %.10f\n\n", $cartJ2000[2]);

// Step 5: Precess back to date
$cartDate = [$cartJ2000[0], $cartJ2000[1], $cartJ2000[2]];
Precession::precess($cartDate, $jd, 0, Constants::J2000_TO_J);

echo "Step 5: Precess back to date\n";
printf("  X: %.10f\n", $cartDate[0]);
printf("  Y: %.10f\n", $cartDate[1]);
printf("  Z: %.10f\n\n", $cartDate[2]);

// Step 6: Equator → Ecliptic (rotate by +obliquity)
$cartEcl = [];
Coordinates::coortrf2($cartDate, $cartEcl, $seps, $ceps);

echo "Step 6: Equator → Ecliptic (rotate by +obliquity)\n";
printf("  X: %.10f\n", $cartEcl[0]);
printf("  Y: %.10f\n", $cartEcl[1]);
printf("  Z: %.10f\n\n", $cartEcl[2]);

// Step 7: Cartesian → Polar
$polResult = [];
Coordinates::cartPol($cartEcl, $polResult);

echo "Step 7: Cartesian → Polar\n";
printf("  Longitude: %.10f rad\n", $polResult[0]);
printf("  Latitude:  %.10f rad\n", $polResult[1]);
printf("  Distance:  %.10f AU\n\n", $polResult[2]);

// Step 8: Convert to degrees
$lon_final = rad2deg($polResult[0]);
$lat_final = rad2deg($polResult[1]);

echo "Step 8: Convert to degrees\n";
echo "  Longitude: $lon_final°\n";
echo "  Latitude:  $lat_final°\n";
echo "  Distance:  {$polResult[2]} AU\n\n";

// Compare
$diff = $lon_final - $lon_deg;
echo "=== Result ===\n";
echo "Input longitude:  $lon_deg°\n";
echo "Output longitude: $lon_final°\n";
echo "Difference:       $diff° (" . ($diff * 3600) . "\")\n\n";

if (abs($diff) < 0.001) {
    echo "✓ Transformation cycle is reversible (within 0.001°)\n";
} else {
    echo "✗ Transformation introduces error of $diff°\n";
}
