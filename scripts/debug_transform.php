<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Coordinates;
use Swisseph\Obliquity;
use Swisseph\Precession;
use Swisseph\Nutation;
use Swisseph\Constants;

$jd = 2451545.0; // J2000

// Start with Jupiter node from raw table
$lon_deg = 100.464441;
$lat_deg = 0.0;
$dist = 5.20336301; // semimajor axis

echo "=== Step-by-step coordinate transformation ===\n\n";
echo "Initial (from VSOP87 table, T=0):\n";
printf("  Lon: %.6f°, Lat: %.6f°, Dist: %.6f AU\n\n", $lon_deg, $lat_deg, $dist);

// Step 1: degrees to radians, polar to cartesian
$xx = [$lon_deg, $lat_deg, $dist, 0.0, 0.0, 0.0]; // no speed for simplicity

$xx[0] = deg2rad($xx[0]);
$xx[1] = deg2rad($xx[1]);
Coordinates::polCartSp($xx, $xx);

echo "After polar→cartesian:\n";
printf("  X: %.6f, Y: %.6f, Z: %.6f\n\n", $xx[0], $xx[1], $xx[2]);

// Step 2: Get obliquity (for date, not J2000)
$eps = Obliquity::meanObliquityRadFromJdTT($jd);
$seps = sin($eps);
$ceps = cos($eps);

echo "Obliquity: " . rad2deg($eps) . "°\n\n";

// Step 3: Transform to equator
$pos = [$xx[0], $xx[1], $xx[2]];
$posOut = [];
Coordinates::coortrf2($pos, $posOut, -$seps, $ceps);

echo "After ecliptic→equator:\n";
printf("  X: %.6f, Y: %.6f, Z: %.6f\n\n", $posOut[0], $posOut[1], $posOut[2]);

// Step 4: Precess to J2000
$xx_j2000 = [$posOut[0], $posOut[1], $posOut[2], 0.0, 0.0, 0.0];
Precession::precess($xx_j2000, $jd, 0, Constants::J_TO_J2000);

echo "After precess to J2000:\n";
printf("  X: %.6f, Y: %.6f, Z: %.6f\n", $xx_j2000[0], $xx_j2000[1], $xx_j2000[2]);
printf("  Diff from previous: %.10f\n\n", abs($xx_j2000[0] - $posOut[0]) + abs($xx_j2000[1] - $posOut[1]) + abs($xx_j2000[2] - $posOut[2]));

// Step 5: Precess back to date
$xx_date = [$xx_j2000[0], $xx_j2000[1], $xx_j2000[2], 0.0, 0.0, 0.0];
Precession::precess($xx_date, $jd, 0, Constants::J2000_TO_J);

echo "After precess back to date:\n";
printf("  X: %.6f, Y: %.6f, Z: %.6f\n", $xx_date[0], $xx_date[1], $xx_date[2]);
printf("  Diff from before precess: %.10f\n\n", abs($xx_date[0] - $posOut[0]) + abs($xx_date[1] - $posOut[1]) + abs($xx_date[2] - $posOut[2]));

// Step 6: Transform back to ecliptic
$pos2 = [$xx_date[0], $xx_date[1], $xx_date[2]];
$posOut2 = [];
Coordinates::coortrf2($pos2, $posOut2, $seps, $ceps);

echo "After equator→ecliptic:\n";
printf("  X: %.6f, Y: %.6f, Z: %.6f\n\n", $posOut2[0], $posOut2[1], $posOut2[2]);

// Step 7: Cartesian to polar
$xx_final = [$posOut2[0], $posOut2[1], $posOut2[2], 0.0, 0.0, 0.0];
Coordinates::cartPolSp($xx_final, $xx_final);

echo "After cartesian→polar:\n";
printf("  Lon: %.6f rad, Lat: %.6f rad, Dist: %.6f AU\n\n", $xx_final[0], $xx_final[1], $xx_final[2]);

// Step 8: Radians to degrees
$lon_final = rad2deg($xx_final[0]);
$lat_final = rad2deg($xx_final[1]);

echo "After radians→degrees:\n";
printf("  Lon: %.6f°, Lat: %.6f°\n\n", $lon_final, $lat_final);

// Compare
$diff = $lon_final - $lon_deg;
echo "=== Result ===\n";
printf("Initial: %.6f°\n", $lon_deg);
printf("Final:   %.6f°\n", $lon_final);
printf("Diff:    %.6f° (%.2f\")\n", $diff, $diff * 3600);
