<?php
/**
 * Step-by-step debug for IntpApsidesCalculator
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Domain\Moshier\MoshierMoon;
use Swisseph\Coordinates;
use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;

$jd = 2460311.0; // 2024-01-01 12:00 UT

echo "=== Debug intpApsides step-by-step ===\n";
echo "JD = $jd\n\n";

$moon = new MoshierMoon();

// Test apogee (SEI_INTP_APOG = 4)
$pol = array_fill(0, 3, 0.0);
$moon->intpApsides($jd, $pol, 4);

echo "=== After intpApsides() ===\n";
echo sprintf("pol[0] (longitude) = %.10f rad = %.7f°\n", $pol[0], rad2deg($pol[0]));
echo sprintf("pol[1] (latitude)  = %.10f rad = %.7f°\n", $pol[1], rad2deg($pol[1]));
echo sprintf("pol[2] (distance)  = %.10f AU\n", $pol[2]);

// Expected from swetest:
// intp. Apogee     163.5930380   0.2102416    0.002706626
echo "\n=== Expected (swetest64 -emos) ===\n";
echo "Lon: 163.5930380°, Lat: 0.2102416°, Dist: 0.002706626 AU\n";

// Calculate difference
echo "\n=== Differences ===\n";
$lonDiff = rad2deg($pol[0]) - 163.5930380;
$latDiff = rad2deg($pol[1]) - 0.2102416;
$distDiff = $pol[2] - 0.002706626;

echo sprintf("Lon diff: %.7f°\n", $lonDiff);
echo sprintf("Lat diff: %.7f°\n", $latDiff);
echo sprintf("Dist diff: %.10f AU\n", $distDiff);

// Now check polCartSp
echo "\n=== After polCartSp ===\n";
$xx = [$pol[0], $pol[1], $pol[2], 0.0, 0.0, 0.0];
$cart = array_fill(0, 6, 0.0);
Coordinates::polCartSp($xx, $cart);

echo sprintf("cart[0] (x) = %.10f\n", $cart[0]);
echo sprintf("cart[1] (y) = %.10f\n", $cart[1]);
echo sprintf("cart[2] (z) = %.10f\n", $cart[2]);

// Convert back to polar
$polar = array_fill(0, 6, 0.0);
Coordinates::cartPolSp($cart, $polar);

echo "\n=== After cartPolSp (verify round-trip) ===\n";
echo sprintf("polar[0] (longitude) = %.10f rad = %.7f°\n", $polar[0], rad2deg($polar[0]));
echo sprintf("polar[1] (latitude)  = %.10f rad = %.7f°\n", $polar[1], rad2deg($polar[1]));
echo sprintf("polar[2] (distance)  = %.10f AU\n", $polar[2]);
