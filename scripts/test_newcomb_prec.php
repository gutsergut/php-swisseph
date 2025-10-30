<?php

require __DIR__ . '/../tests/bootstrap.php';

// Direct test of Newcomb precession
echo "=== Testing Newcomb precession ===\n\n";

// Vernal point at J2000
$x = [1.0, 0.0, 0.0];
echo "Initial: [" . implode(", ", $x) . "]\n";

// Target: B1950 (JD 2433282.42346)
$t0 = 2433282.42346;

echo "Precessing from J2000 to B1950 using Newcomb model...\n";
\Swisseph\Precession::precess($x, $t0, 0, -1, \Swisseph\Precession::SEMOD_PREC_NEWCOMB);
echo "Result: [" . implode(", ", $x) . "]\n";

// Now test with IAU 2006
$x2 = [1.0, 0.0, 0.0];
echo "\nPrecessing from J2000 to B1950 using IAU 2006 model...\n";
\Swisseph\Precession::precess($x2, $t0, 0, -1, \Swisseph\Precession::SEMOD_PREC_IAU_2006);
echo "Result: [" . implode(", ", $x2) . "]\n";

// Compare
echo "\nDifference: [" . ($x[0] - $x2[0]) . ", " . ($x[1] - $x2[1]) . ", " . ($x[2] - $x2[2]) . "]\n";

// Convert to ecliptic longitude at B1950
$eps = \Swisseph\Obliquity::meanObliquityRadFromJdTT($t0);
echo "\nObliquity at B1950: " . rad2deg($eps) . "°\n";

$x_ecl_newcomb = \Swisseph\Coordinates::equatorialToEcliptic($x[0], $x[1], $x[2], $eps);
$lon_newcomb = rad2deg(atan2($x_ecl_newcomb[1], $x_ecl_newcomb[0]));

$x_ecl_iau = \Swisseph\Coordinates::equatorialToEcliptic($x2[0], $x2[1], $x2[2], $eps);
$lon_iau = rad2deg(atan2($x_ecl_iau[1], $x_ecl_iau[0]));

echo "Longitude (Newcomb): $lon_newcomb°\n";
echo "Longitude (IAU 2006): $lon_iau°\n";
echo "Difference: " . abs($lon_newcomb - $lon_iau) * 3600 . " arcsec\n";
