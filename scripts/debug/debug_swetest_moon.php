<?php
/**
 * Debug: Get all moon XYZ combinations from swetest and compare
 */

$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';
$edir = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe';

echo "=== swetest Moon XYZ Reference ===\n\n";

// Test 1: Ecliptic polar
$cmd = sprintf('cmd /c ""%s" -b1.1.2000 -ut12:00:00 -p1 -fPl -head -eswe -true -j2000 -nonut -edir%s"',
    $swetest, $edir);
$output = [];
exec($cmd, $output);
echo "Ecliptic polar:\n";
echo implode("\n", $output) . "\n\n";

// Test 2: Ecliptic XYZ
$cmd = sprintf('cmd /c ""%s" -b1.1.2000 -ut12:00:00 -p1 -fXYZ -head -eswe -true -j2000 -nonut -edir%s"',
    $swetest, $edir);
$output = [];
exec($cmd, $output);
echo "Ecliptic XYZ:\n";
echo implode("\n", $output) . "\n\n";

// Test 3: Equatorial polar (-ep flag)
$cmd = sprintf('cmd /c ""%s" -b1.1.2000 -ut12:00:00 -p1 -fPAD -head -eswe -true -j2000 -nonut -ep -edir%s"',
    $swetest, $edir);
$output = [];
exec($cmd, $output);
echo "Equatorial polar:\n";
echo implode("\n", $output) . "\n\n";

// Test 4: Equatorial XYZ
$cmd = sprintf('cmd /c ""%s" -b1.1.2000 -ut12:00:00 -p1 -fXYZ -head -eswe -true -j2000 -nonut -ep -edir%s"',
    $swetest, $edir);
$output = [];
exec($cmd, $output);
echo "Equatorial XYZ (-ep flag):\n";
echo implode("\n", $output) . "\n\n";

// Parse equatorial values from RA/Dec and compute XYZ manually
echo "=== Computing equatorial XYZ from RA/Dec ===\n";
// From earlier output: RA = 14h49'49.4090, Dec = -10°54'10.4880
$ra_deg = 14*15 + 49/4 + 49.4090/240;
$dec_deg = -(10 + 54/60 + 10.4880/3600);

// Distance from ecliptic XYZ
$x_ecl = -0.001949007;
$y_ecl = -0.001838438;
$z_ecl = 0.000242453;
$dist = sqrt($x_ecl*$x_ecl + $y_ecl*$y_ecl + $z_ecl*$z_ecl);

// Compute equatorial XYZ from RA/Dec
$ra_rad = $ra_deg * M_PI / 180.0;
$dec_rad = $dec_deg * M_PI / 180.0;

$x_eq = $dist * cos($dec_rad) * cos($ra_rad);
$y_eq = $dist * cos($dec_rad) * sin($ra_rad);
$z_eq = $dist * sin($dec_rad);

echo "RA = $ra_deg deg, Dec = $dec_deg deg, dist = $dist AU\n";
echo "Computed equatorial XYZ:\n";
echo sprintf("  X = %.15f AU\n", $x_eq);
echo sprintf("  Y = %.15f AU\n", $y_eq);
echo sprintf("  Z = %.15f AU\n", $z_eq);
