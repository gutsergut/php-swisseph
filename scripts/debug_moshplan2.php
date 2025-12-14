<?php
/**
 * Debug comparison between PHP moshplan2 and swetest
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Coordinates;

// Constants
$SEPS2000 = 0.3977771559319137;
$CEPS2000 = 0.9174821430670688;

$jdUT = 2460477.0;  // 2024-06-15 12:00 UT (CORRECT DATE!)
$deltaT = 69.184 / 86400.0;
$jdTT = $jdUT + $deltaT;

echo "=== Debug moshplan2 vs swetest ===\n\n";
echo sprintf("JD (UT): %.10f\n", $jdUT);
echo sprintf("JD (TT): %.10f\n\n", $jdTT);

// Reference from swetest -p2 -t0 -j2460477.0 -emos -hel:
// Mercury: 89.2534365° lon, 4.5751750° lat, 0.308571369 AU distance
// Equatorial XYZ: [0.005808984, 0.272373163, 0.144899909]

echo "--- Mercury (Moshier index 0) ---\n";

// Call moshplan2 directly
$xe = [0.0, 0.0, 0.0];
MoshierPlanetCalculator::moshplan2($jdTT, 0, $xe);  // 0 = Mercury in Moshier

echo sprintf("moshplan2 polar (lon, lat, rad): [%.10f°, %.10f°, %.10f AU]\n",
    rad2deg($xe[0]), rad2deg($xe[1]), $xe[2]);

// Reference from swetest -p2 -t0 -j2460477.0008007409 -emos -hel -j2000
$refLon = 88.9178875;
$refLat = 4.5724582;
$refRad = 0.308572307;
echo sprintf("swetest polar (j2000):           [%.10f°, %.10f°, %.10f AU]\n",
    $refLon, $refLat, $refRad);

$dLon = rad2deg($xe[0]) - $refLon;
$dLat = rad2deg($xe[1]) - $refLat;
$dRad = $xe[2] - $refRad;
echo sprintf("Difference (lon, lat, rad):      [%.6f°, %.6f°, %.6f AU]\n",
    $dLon, $dLat, $dRad);
echo sprintf("Difference in arcsec:            [%.2f\", %.2f\"]\n",
    $dLon * 3600, $dLat * 3600);

echo "\n";

// Convert to cartesian
$xyz = $xe;
Coordinates::polCart($xyz, $xyz);
echo sprintf("Cartesian ecliptic (PHP):    [%.10f, %.10f, %.10f]\n",
    $xyz[0], $xyz[1], $xyz[2]);

// Expected ecliptic cartesian from swetest XYZ (flag -fPXYZ without equatorial):
$refEclX = 0.003980756;
$refEclY = 0.307563090;
$refEclZ = 0.024616400;
echo sprintf("Cartesian ecliptic (swetest): [%.10f, %.10f, %.10f]\n",
    $refEclX, $refEclY, $refEclZ);

echo "\n";

// Convert to equatorial
Coordinates::coortrf2($xyz, $xyz, -$SEPS2000, $CEPS2000);
echo sprintf("Cartesian equatorial (PHP):   [%.10f, %.10f, %.10f]\n",
    $xyz[0], $xyz[1], $xyz[2]);

// Expected equatorial from swetest -fx:
$refEquX = 0.005808984;
$refEquY = 0.272373163;
$refEquZ = 0.144899909;
echo sprintf("Cartesian equatorial (swetest): [%.10f, %.10f, %.10f]\n",
    $refEquX, $refEquY, $refEquZ);

$diffX = $xyz[0] - $refEquX;
$diffY = $xyz[1] - $refEquY;
$diffZ = $xyz[2] - $refEquZ;
echo sprintf("Difference:                     [%.10f, %.10f, %.10f]\n",
    $diffX, $diffY, $diffZ);

echo "\n=== Done ===\n";
