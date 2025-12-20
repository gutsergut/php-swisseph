<?php
/**
 * Debug: Compare ecliptic vs equatorial XYZ
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== Ecliptic vs Equatorial XYZ Comparison ===\n\n";

$jd = 2451545.0;

$baseFlags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;

// Ecliptic XYZ (default)
$flags1 = $baseFlags | Constants::SEFLG_XYZ;
$xx1 = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags1, $xx1, $serr);

echo "PHP Moon ECLIPTIC XYZ:\n";
echo sprintf("  X = %.15f\n", $xx1[0]);
echo sprintf("  Y = %.15f\n", $xx1[1]);
echo sprintf("  Z = %.15f\n", $xx1[2]);

// Equatorial XYZ
$flags2 = $baseFlags | Constants::SEFLG_XYZ | Constants::SEFLG_EQUATORIAL;
$xx2 = [];
swe_calc($jd, Constants::SE_MOON, $flags2, $xx2, $serr);

echo "\nPHP Moon EQUATORIAL XYZ:\n";
echo sprintf("  X = %.15f\n", $xx2[0]);
echo sprintf("  Y = %.15f\n", $xx2[1]);
echo sprintf("  Z = %.15f\n", $xx2[2]);

// swetest reference (with -fXYZ - which is ecliptic!)
echo "\nswetest reference (should be ecliptic):\n";
echo "  X = -0.001949007\n";
echo "  Y = -0.001838438\n";
echo "  Z = 0.000242453\n";

echo "\n=== Differences ===\n";
echo "Ecliptic X diff: " . (($xx1[0] - (-0.001949007)) * 149597870.7) . " km\n";
echo "Equatorial X diff: " . (($xx2[0] - (-0.001949007)) * 149597870.7) . " km\n";

// Manual rotation check
echo "\n=== Manual Equatorial→Ecliptic rotation ===\n";
// J2000 obliquity: 23.4392911 degrees
$eps = 23.4392911 * M_PI / 180.0;
$cosEps = cos($eps);
$sinEps = sin($eps);

// Rotate equatorial to ecliptic:
// x_ecl = x_eq
// y_ecl = y_eq * cos(eps) + z_eq * sin(eps)
// z_ecl = -y_eq * sin(eps) + z_eq * cos(eps)

$x_ecl = $xx2[0];
$y_ecl = $xx2[1] * $cosEps + $xx2[2] * $sinEps;
$z_ecl = -$xx2[1] * $sinEps + $xx2[2] * $cosEps;

echo "Equatorial XYZ:\n";
echo sprintf("  X = %.15f\n", $xx2[0]);
echo sprintf("  Y = %.15f\n", $xx2[1]);
echo sprintf("  Z = %.15f\n", $xx2[2]);

echo "\nRotated to Ecliptic:\n";
echo sprintf("  X = %.15f\n", $x_ecl);
echo sprintf("  Y = %.15f\n", $y_ecl);
echo sprintf("  Z = %.15f\n", $z_ecl);

echo "\nPHP Ecliptic XYZ:\n";
echo sprintf("  X = %.15f\n", $xx1[0]);
echo sprintf("  Y = %.15f\n", $xx1[1]);
echo sprintf("  Z = %.15f\n", $xx1[2]);

echo "\nDifference (rotated vs PHP ecliptic):\n";
echo sprintf("  dX = %.15f = %.3f km\n", $x_ecl - $xx1[0], ($x_ecl - $xx1[0]) * 149597870.7);
echo sprintf("  dY = %.15f = %.3f km\n", $y_ecl - $xx1[1], ($y_ecl - $xx1[1]) * 149597870.7);
echo sprintf("  dZ = %.15f = %.3f km\n", $z_ecl - $xx1[2], ($z_ecl - $xx1[2]) * 149597870.7);
