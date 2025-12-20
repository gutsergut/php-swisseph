<?php
/**
 * Debug: Compute swetest equatorial XYZ from RA/Dec
 */

// swetest RA/Dec (from earlier output):
// RA  = 14h49m49.4090s
// Dec = -10°54'10.4880"

// Convert to degrees
$swe_ra = 14*15 + 49/4 + 49.4090/240;  // 222.45587...
$swe_dec = -(10 + 54/60 + 10.4880/3600);  // -10.90291...

echo "swetest RA/Dec:\n";
echo "  RA  = {$swe_ra}°\n";
echo "  Dec = {$swe_dec}°\n";

// Get distance from swetest (ecliptic, but dist is same)
// From earlier: distance is derived from XYZ
$swe_x_ecl = -0.001949007;
$swe_y_ecl = -0.001838438;
$swe_z_ecl = 0.000242453;
$dist = sqrt($swe_x_ecl*$swe_x_ecl + $swe_y_ecl*$swe_y_ecl + $swe_z_ecl*$swe_z_ecl);

echo "  dist = {$dist} AU\n";

// Compute equatorial XYZ from RA/Dec
$ra_rad = $swe_ra * M_PI / 180.0;
$dec_rad = $swe_dec * M_PI / 180.0;

$x_eq = $dist * cos($dec_rad) * cos($ra_rad);
$y_eq = $dist * cos($dec_rad) * sin($ra_rad);
$z_eq = $dist * sin($dec_rad);

echo "\nswetest Equatorial XYZ (computed from RA/Dec):\n";
echo sprintf("  X = %.15f\n", $x_eq);
echo sprintf("  Y = %.15f\n", $y_eq);
echo sprintf("  Z = %.15f\n", $z_eq);

// PHP equatorial XYZ from earlier:
$php_x_eq = -0.001949281567213;
$php_y_eq = -0.001782892062430;
$php_z_eq = -0.000508713480045;

echo "\nPHP Equatorial XYZ:\n";
echo sprintf("  X = %.15f\n", $php_x_eq);
echo sprintf("  Y = %.15f\n", $php_y_eq);
echo sprintf("  Z = %.15f\n", $php_z_eq);

// PHP distance
$php_dist = sqrt($php_x_eq*$php_x_eq + $php_y_eq*$php_y_eq + $php_z_eq*$php_z_eq);
echo "\n  PHP dist = {$php_dist} AU\n";

$AU_KM = 149597870.7;
echo "\nDifferences (Equatorial):\n";
echo sprintf("  dX = %.15f AU = %.3f km\n", $php_x_eq - $x_eq, ($php_x_eq - $x_eq) * $AU_KM);
echo sprintf("  dY = %.15f AU = %.3f km\n", $php_y_eq - $y_eq, ($php_y_eq - $y_eq) * $AU_KM);
echo sprintf("  dZ = %.15f AU = %.3f km\n", $php_z_eq - $z_eq, ($php_z_eq - $z_eq) * $AU_KM);
