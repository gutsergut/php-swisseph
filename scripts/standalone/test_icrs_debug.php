<?php
/**
 * Test program to debug frame bias (ICRS) logic
 * Compares intermediate values with C implementation
 */

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

$tjd = 2460000.5;
$serr = '';

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

echo "=== Debug Frame Bias (ICRS) for Mercury ===\n";
echo "JD = " . number_format($tjd, 6, '.', '') . "\n\n";

// Step 1: Mercury heliocentric J2000 XYZ
echo "=== Step 1: Heliocentric XYZ J2000 ===\n";
$iflag_hel = Constants::SEFLG_SWIEPH | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_HELCTR | Constants::SEFLG_J2000;
$xx_merc_hel = array_fill(0, 6, 0.0);
PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag_hel, $xx_merc_hel, $serr);
echo "Mercury heliocentric J2000 XYZ:\n";
printf("  x = %.15f\n", $xx_merc_hel[0]);
printf("  y = %.15f\n", $xx_merc_hel[1]);
printf("  z = %.15f\n", $xx_merc_hel[2]);
printf("  vx = %.15f\n", $xx_merc_hel[3]);
printf("  vy = %.15f\n", $xx_merc_hel[4]);
printf("  vz = %.15f\n", $xx_merc_hel[5]);
echo "\n";

// Earth heliocentric
$xx_earth_hel = array_fill(0, 6, 0.0);
PlanetsFunctions::calc($tjd, Constants::SE_EARTH, $iflag_hel, $xx_earth_hel, $serr);
echo "Earth heliocentric J2000 XYZ:\n";
printf("  x = %.15f\n", $xx_earth_hel[0]);
printf("  y = %.15f\n", $xx_earth_hel[1]);
printf("  z = %.15f\n", $xx_earth_hel[2]);
printf("  vx = %.15f\n", $xx_earth_hel[3]);
printf("  vy = %.15f\n", $xx_earth_hel[4]);
printf("  vz = %.15f\n", $xx_earth_hel[5]);
echo "\n";

// Step 2: Sun barycentric
echo "=== Step 2: Sun barycentric position ===\n";
$iflag_bary = Constants::SEFLG_SWIEPH | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000;
$xx_sun_bary = array_fill(0, 6, 0.0);
PlanetsFunctions::calc($tjd, Constants::SE_SUN, $iflag_bary, $xx_sun_bary, $serr);
echo "Sun barycentric J2000 XYZ:\n";
printf("  x = %.15f\n", $xx_sun_bary[0]);
printf("  y = %.15f\n", $xx_sun_bary[1]);
printf("  z = %.15f\n", $xx_sun_bary[2]);
echo "\n";

// Step 3: Mercury barycentric
echo "=== Step 3: Mercury barycentric ===\n";
$xx_merc_bary = array_fill(0, 6, 0.0);
PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag_bary, $xx_merc_bary, $serr);
echo "Mercury barycentric J2000 XYZ:\n";
printf("  x = %.15f\n", $xx_merc_bary[0]);
printf("  y = %.15f\n", $xx_merc_bary[1]);
printf("  z = %.15f\n", $xx_merc_bary[2]);
echo "\n";

// Step 4: Earth barycentric
echo "=== Step 4: Earth barycentric ===\n";
$xx_earth_bary = array_fill(0, 6, 0.0);
PlanetsFunctions::calc($tjd, Constants::SE_EARTH, $iflag_bary, $xx_earth_bary, $serr);
echo "Earth barycentric J2000 XYZ:\n";
printf("  x = %.15f\n", $xx_earth_bary[0]);
printf("  y = %.15f\n", $xx_earth_bary[1]);
printf("  z = %.15f\n", $xx_earth_bary[2]);
echo "\n";

// Step 5: Geocentric = Mercury bary - Earth bary
echo "=== Step 5: Geocentric = Merc_bary - Earth_bary ===\n";
printf("  x = %.15f\n", $xx_merc_bary[0] - $xx_earth_bary[0]);
printf("  y = %.15f\n", $xx_merc_bary[1] - $xx_earth_bary[1]);
printf("  z = %.15f\n", $xx_merc_bary[2] - $xx_earth_bary[2]);
echo "\n";

// Step 6: Final geocentric
echo "=== Step 6: Final Geocentric J2000 TRUEPOS ===\n";
$iflag1 = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS;
$xx = array_fill(0, 6, 0.0);
PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag1, $xx, $serr);
printf("  x = %.15f\n", $xx[0]);
printf("  y = %.15f\n", $xx[1]);
printf("  z = %.15f\n", $xx[2]);
echo "\n";

// Step 7: With ICRS flag
echo "=== Step 7: With ICRS flag ===\n";
$iflag2 = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_ICRS;
$xx_icrs = array_fill(0, 6, 0.0);
PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag2, $xx_icrs, $serr);
printf("  x = %.15f\n", $xx_icrs[0]);
printf("  y = %.15f\n", $xx_icrs[1]);
printf("  z = %.15f\n", $xx_icrs[2]);
echo "\n";

echo "=== Bias effect ===\n";
$dx = $xx_icrs[0] - $xx[0];
$dy = $xx_icrs[1] - $xx[1];
$dz = $xx_icrs[2] - $xx[2];
printf("  dx = %.15e AU\n", $dx);
printf("  dy = %.15e AU\n", $dy);
printf("  dz = %.15e AU\n", $dz);
$dr_km = sqrt($dx*$dx + $dy*$dy + $dz*$dz) * 149597870.7;
printf("  |dr| = %.3f km\n", $dr_km);
