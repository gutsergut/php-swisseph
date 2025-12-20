<?php
/**
 * Verify polar->cartesian and ecliptic->equatorial manually
 */

// Reference values from swetest -j2000 (ecliptic J2000 polar):
$L_deg = 88.9178875;  // longitude degrees
$B_deg = 4.5724582;   // latitude degrees
$R = 0.308572307;     // distance AU

// Convert to radians
$L = deg2rad($L_deg);
$B = deg2rad($B_deg);

echo "=== Manual verification ===\n\n";
echo "Input (ecliptic J2000 polar from swetest -j2000):\n";
echo sprintf("  L = %.7f deg = %.10f rad\n", $L_deg, $L);
echo sprintf("  B = %.7f deg = %.10f rad\n", $B_deg, $B);
echo sprintf("  R = %.9f AU\n\n", $R);

// Step 1: Convert polar to cartesian ecliptic
// x = r * cos(B) * cos(L)
// y = r * cos(B) * sin(L)
// z = r * sin(B)

$cosB = cos($B);
$sinB = sin($B);
$cosL = cos($L);
$sinL = sin($L);

$x_ecl = $R * $cosB * $cosL;
$y_ecl = $R * $cosB * $sinL;
$z_ecl = $R * $sinB;

echo "Step 1: Polar -> Cartesian (ecliptic):\n";
echo sprintf("  X_ecl = %.12f AU\n", $x_ecl);
echo sprintf("  Y_ecl = %.12f AU\n", $y_ecl);
echo sprintf("  Z_ecl = %.12f AU\n\n", $z_ecl);

// Step 2: Convert ecliptic to equatorial J2000
// Rotation around X axis by obliquity epsilon
// epsilon(J2000) = 23.4392911 degrees = 0.4090928 radians

$eps_deg = 23.4392911;  // Mean obliquity J2000
$eps = deg2rad($eps_deg);
$cosEps = cos($eps);
$sinEps = sin($eps);

echo "Obliquity J2000:\n";
echo sprintf("  epsilon = %.7f deg = %.10f rad\n", $eps_deg, $eps);
echo sprintf("  sin(eps) = %.16f\n", $sinEps);
echo sprintf("  cos(eps) = %.16f\n\n", $cosEps);

// Transform:
// x_eq = x_ecl
// y_eq = y_ecl * cos(eps) - z_ecl * sin(eps)
// z_eq = y_ecl * sin(eps) + z_ecl * cos(eps)

$x_eq = $x_ecl;
$y_eq = $y_ecl * $cosEps - $z_ecl * $sinEps;
$z_eq = $y_ecl * $sinEps + $z_ecl * $cosEps;

echo "Step 2: Ecliptic -> Equatorial J2000:\n";
echo sprintf("  X_eq = %.12f AU\n", $x_eq);
echo sprintf("  Y_eq = %.12f AU\n", $y_eq);
echo sprintf("  Z_eq = %.12f AU\n\n", $z_eq);

// Reference from swetest -j2000 -fx:
echo "Reference (swetest -j2000 -fx):\n";
echo "  X = 0.005808984 AU\n";
echo "  Y = 0.272373163 AU\n";
echo "  Z = 0.144899909 AU\n\n";

// Comparison
$ref = [0.005808984, 0.272373163, 0.144899909];
$AU_TO_KM = 149597870.7;
$dx = ($x_eq - $ref[0]) * $AU_TO_KM;
$dy = ($y_eq - $ref[1]) * $AU_TO_KM;
$dz = ($z_eq - $ref[2]) * $AU_TO_KM;

echo "Differences:\n";
echo sprintf("  ΔX = %.1f km\n", $dx);
echo sprintf("  ΔY = %.1f km\n", $dy);
echo sprintf("  ΔZ = %.1f km\n", $dz);
