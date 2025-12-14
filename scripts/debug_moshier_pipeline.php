<?php
/**
 * Debug Moshier pipeline step-by-step
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Moshier\MoshierConstants;
use Swisseph\Coordinates;
use Swisseph\Precession;
use Swisseph\Constants;

// J2000 obliquity
const SEPS2000 = 0.3977771559319137;
const CEPS2000 = 0.9174821430670688;

// JD for 2024-06-15 12:00 UT
$jdUT = 2460477.0;
$deltaT = 69.184 / 86400.0;
$jdTT = $jdUT + $deltaT;

echo "=== Debug Moshier Pipeline for Mercury ===\n\n";
echo sprintf("JD (TT): %.10f\n\n", $jdTT);

// Step 1: Get heliocentric equatorial J2000 from moshplan()
$xpret = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
$xeret = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
$serr = null;

$ret = MoshierPlanetCalculator::moshplan($jdTT, MoshierConstants::SEI_MERCURY, $xpret, $xeret, $serr);
if ($ret < 0) {
    die("moshplan() failed: $serr\n");
}

echo "Step 1: moshplan() output (heliocentric equatorial J2000)\n";
echo sprintf("  Mercury: [%.9f, %.9f, %.9f] AU\n", $xpret[0], $xpret[1], $xpret[2]);
echo sprintf("  Earth:   [%.9f, %.9f, %.9f] AU\n\n", $xeret[0], $xeret[1], $xeret[2]);

// Step 2: Geocentric = planet - Earth
$xgeo = [
    $xpret[0] - $xeret[0],
    $xpret[1] - $xeret[1],
    $xpret[2] - $xeret[2],
    $xpret[3] - $xeret[3],
    $xpret[4] - $xeret[4],
    $xpret[5] - $xeret[5],
];
echo "Step 2: Geocentric equatorial J2000\n";
echo sprintf("  [%.9f, %.9f, %.9f] AU\n\n", $xgeo[0], $xgeo[1], $xgeo[2]);

// Step 3: Equatorial J2000 → Ecliptic J2000
// NOTE: moshplan returns equatorial J2000, we need to convert to ecliptic J2000
// Rotation is REVERSE of what we did for polar→cartesian
// ecl_from_eq: y_ecl = y_eq * cos(eps) + z_eq * sin(eps)
//              z_ecl = -y_eq * sin(eps) + z_eq * cos(eps)
$x_ecl = $xgeo;
Coordinates::coortrf2($xgeo, $x_ecl, SEPS2000, CEPS2000);
echo "Step 3: Ecliptic J2000 (geocentric)\n";
echo sprintf("  [%.9f, %.9f, %.9f] AU\n\n", $x_ecl[0], $x_ecl[1], $x_ecl[2]);

// Step 4: Convert to polar
$polar = [0.0, 0.0, 0.0];
Coordinates::cartPol($x_ecl, $polar);
$lon_j2000 = rad2deg($polar[0]);
$lat_j2000 = rad2deg($polar[1]);
while ($lon_j2000 < 0) $lon_j2000 += 360;
echo "Step 4: Polar ecliptic J2000\n";
echo sprintf("  Lon: %.7f°\n", $lon_j2000);
echo sprintf("  Lat: %.7f°\n", $lat_j2000);
echo sprintf("  Rad: %.9f AU\n\n", $polar[2]);

// Step 5: Precession J2000 → date
Precession::precess($x_ecl, $jdTT, 0, 0); // J2000_TO_J
$polar_date = [0.0, 0.0, 0.0];
Coordinates::cartPol($x_ecl, $polar_date);
$lon_date = rad2deg($polar_date[0]);
$lat_date = rad2deg($polar_date[1]);
while ($lon_date < 0) $lon_date += 360;
echo "Step 5: After precession J2000→date\n";
echo sprintf("  Lon: %.7f°\n", $lon_date);
echo sprintf("  Lat: %.7f°\n", $lat_date);
echo sprintf("  Rad: %.9f AU\n\n", $polar_date[2]);

// Compare with swetest reference
echo "Reference (swetest -emos Mercury geocentric ecliptic of date):\n";
echo "  Lon: 89.2584683°\n";
echo sprintf("  Δ: %.2f\"\n\n", ($lon_date - 89.2584683) * 3600);

// Also check J2000 reference
echo "Reference (swetest -emos -j2000 Mercury geocentric ecliptic J2000):\n";
// We need to get this...
// cmd /c "swetest64.exe -b15.6.2024 -ut12:00 -emos -p2 -fPlbr -head -j2000"
echo "  (Need to obtain from swetest)\n";
