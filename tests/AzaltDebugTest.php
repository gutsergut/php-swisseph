<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Initialize Swiss Ephemeris
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_ut = 2451545.0; // J2000.0
$geopos = [13.0, 52.0, 0.0]; // Berlin

echo "=== Azalt Round-Trip Debug ===\n\n";
echo "Testing: Az=180°, Alt=45°\n\n";

// Forward: HOR -> EQU
$xin_hor = [180.0, 45.0];
$xout_equ = [0.0, 0.0, 0.0];
swe_azalt_rev($tjd_ut, Constants::SE_HOR2EQU, $geopos, $xin_hor, $xout_equ);

echo "After azalt_rev (HOR->EQU):\n";
echo sprintf("  RA: %.8f°\n", $xout_equ[0]);
echo sprintf("  Dec: %.8f°\n", $xout_equ[1]);
echo "\n";

// Backward: EQU -> HOR
$xin_equ = [$xout_equ[0], $xout_equ[1]];
$xout_hor = [0.0, 0.0, 0.0];
swe_azalt($tjd_ut, Constants::SE_EQU2HOR, $geopos, 1013.25, 15.0, $xin_equ, $xout_hor);

echo "After azalt (EQU->HOR):\n";
echo sprintf("  Azimuth: %.8f°\n", $xout_hor[0]);
echo sprintf("  True Alt: %.8f°\n", $xout_hor[1]);
echo sprintf("  App Alt: %.8f°\n", $xout_hor[2]);
echo "\n";

echo "Round-trip error:\n";
echo sprintf("  Azimuth: %.8f° (expected 0)\n", $xout_hor[0] - $xin_hor[0]);
echo sprintf("  Altitude: %.8f° (expected 0)\n", $xout_hor[1] - $xin_hor[1]);
echo "\n";

// Now debug step by step using internal functions
echo "=== Manual Step-by-Step Debug ===\n\n";

$armc = swe_degnorm(swe_sidtime($tjd_ut) * 15.0 + $geopos[0]);
echo sprintf("ARMC: %.8f°\n", $armc);

// Step 1: azalt_rev (HOR->EQU)
$xaz = [180.0, 45.0, 1.0];
echo "\nStep 1: Input horizontal coords\n";
echo sprintf("  Az(south): %.8f°, Alt: %.8f°\n", $xaz[0], $xaz[1]);

// Convert azimuth from south-clockwise to east-counterclockwise
$xaz[0] = 360.0 - $xaz[0];
$xaz[0] = swe_degnorm($xaz[0] - 90.0);
echo "\nStep 2: After azimuth conversion (east-counterclock)\n";
echo sprintf("  Az(east): %.8f°, Alt: %.8f°\n", $xaz[0], $xaz[1]);

// Rotate to equatorial
$dang = $geopos[1] - 90.0;
echo sprintf("\nStep 3: Rotation angle (geolat - 90): %.8f°\n", $dang);

$xaz_before = [$xaz[0], $xaz[1], $xaz[2]];
swe_cotrans($xaz, $xaz, $dang);
echo sprintf("  Before cotrans: [%.8f, %.8f, %.8f]\n", $xaz_before[0], $xaz_before[1], $xaz_before[2]);
echo sprintf("  After cotrans:  [%.8f, %.8f, %.8f]\n", $xaz[0], $xaz[1], $xaz[2]);

$xaz[0] = swe_degnorm($xaz[0] + $armc + 90.0);
echo sprintf("\nStep 4: Add ARMC+90\n");
echo sprintf("  RA: %.8f°, Dec: %.8f°\n", $xaz[0], $xaz[1]);

// Step 2: azalt (EQU->HOR)
$xra = [$xaz[0], $xaz[1], 1.0];
echo "\n\nStep 5: Now reverse with azalt (EQU->HOR)\n";
echo sprintf("  Input RA: %.8f°, Dec: %.8f°\n", $xra[0], $xra[1]);

$mdd = swe_degnorm($xra[0] - $armc);
echo sprintf("\nStep 6: Meridian distance (RA - ARMC)\n");
echo sprintf("  MDD: %.8f°\n", $mdd);

$x = [swe_degnorm($mdd - 90.0), $xra[1], 1.0];
echo sprintf("\nStep 7: Before horizontal rotation\n");
echo sprintf("  x[0]=MDD-90: %.8f°, x[1]=Dec: %.8f°\n", $x[0], $x[1]);

$x_before = [$x[0], $x[1], $x[2]];
swe_cotrans($x, $x, 90.0 - $geopos[1]);
echo sprintf("\nStep 8: After cotrans(90 - geolat = %.8f°)\n", 90.0 - $geopos[1]);
echo sprintf("  Before: [%.8f, %.8f, %.8f]\n", $x_before[0], $x_before[1], $x_before[2]);
echo sprintf("  After:  [%.8f, %.8f, %.8f]\n", $x[0], $x[1], $x[2]);

$x[0] = swe_degnorm($x[0] + 90.0);
$az_final = 360.0 - $x[0];
echo sprintf("\nStep 9: Convert to south-clockwise\n");
echo sprintf("  x[0]+90: %.8f°\n", $x[0]);
echo sprintf("  Final Az: %.8f° (expected 180°)\n", $az_final);
echo sprintf("  Final Alt: %.8f° (expected 45°)\n", $x[1]);

echo "\n=== End Debug ===\n";
