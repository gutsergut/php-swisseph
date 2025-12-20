<?php
/**
 * Test script for lunar True Node and apogees
 * Reference values from swetest64.exe -b1.1.2020 -ut12:00:00
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Domain\NodesApsides\OsculatingCalculator;
use Swisseph\Swe\Functions\PlanetsFunctions;

// Set ephemeris path
$ephePath = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
if ($ephePath) {
    swe_set_ephe_path($ephePath);
    echo "Ephemeris path: $ephePath\n\n";
}

// JD for 2020-01-01 12:00:00 UT
$deltaT = 69.184 / 86400.0;  // approx delta-T for 2020
$jd_ut = 2458850.0;
$jd_tt = $jd_ut + $deltaT;

echo "JD_UT = $jd_ut\n";
echo "JD_TT = $jd_tt\n\n";

// Reference values from swetest64
$refs = [
    'Mean Node' => ['lon' => 98.2127444, 'lat' => 0.0, 'speed' => -0.0529677, 'dist' => 0.002569555],
    'True Node' => ['lon' => 98.3863094, 'lat' => 0.0, 'speed' => -0.0029092, 'dist' => 0.002506995],
    'Mean Apogee' => ['lon' => 357.1074026, 'lat' => -5.0495502, 'speed' => 0.1120060, 'dist' => 0.002710625],
    'Oscu Apogee' => ['lon' => 359.3969669, 'lat' => -5.2210294, 'speed' => -1.1883939, 'dist' => 0.002704573],
];

// Test Mean Node via swe_calc
echo "=== Testing Mean Node via swe_calc ===\n";
$xx = [];
$serr = null;
$ret = PlanetsFunctions::calc($jd_tt, Constants::SE_MEAN_NODE, Constants::SEFLG_SPEED, $xx, $serr);
if ($ret >= 0) {
    printf("Mean Node:   lon=%.7f°, lat=%.7f°, speed=%.7f°/d, dist=%.9f AU\n",
        $xx[0], $xx[1], $xx[3], $xx[2]);
    printf("  Reference: lon=%.7f°, lat=%.7f°, speed=%.7f°/d, dist=%.9f AU\n",
        $refs['Mean Node']['lon'], $refs['Mean Node']['lat'], $refs['Mean Node']['speed'], $refs['Mean Node']['dist']);
    printf("  Δlon=%.6f°\n\n", $xx[0] - $refs['Mean Node']['lon']);
} else {
    echo "Error: $serr\n\n";
}

// Test osculating nodes/apsides for Moon via OsculatingCalculator
echo "=== Testing Osculating Elements via OsculatingCalculator ===\n";
$xnasc = $xndsc = $xperi = $xaphe = [];
$serr = null;

$ok = OsculatingCalculator::calculate(
    $jd_tt,
    Constants::SE_MOON,
    Constants::SEFLG_SPEED,  // with speed
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    false,  // don't return focal point
    true,   // with speed
    false,  // not barycentric
    $serr
);

if ($ok) {
    // Convert cartesian to polar for display
    $nasc_pol = [];
    \Swisseph\Coordinates::cartPolSp($xnasc, $nasc_pol);
    $nasc_pol[0] = \Swisseph\Math::radToDeg($nasc_pol[0]);
    $nasc_pol[1] = \Swisseph\Math::radToDeg($nasc_pol[1]);
    $nasc_pol[3] = \Swisseph\Math::radToDeg($nasc_pol[3]);
    $nasc_pol[4] = \Swisseph\Math::radToDeg($nasc_pol[4]);
    if ($nasc_pol[0] < 0) $nasc_pol[0] += 360.0;

    printf("True Node (from OsculatingCalculator):\n");
    printf("  lon=%.7f°, lat=%.7f°, speed=%.7f°/d, dist=%.9f AU\n",
        $nasc_pol[0], $nasc_pol[1], $nasc_pol[3], $nasc_pol[2]);
    printf("  Reference: lon=%.7f°, lat=%.7f°, speed=%.7f°/d, dist=%.9f AU\n",
        $refs['True Node']['lon'], $refs['True Node']['lat'], $refs['True Node']['speed'], $refs['True Node']['dist']);
    printf("  Δlon=%.6f°\n\n", $nasc_pol[0] - $refs['True Node']['lon']);

    // Apogee (Lilith)
    $aphe_pol = [];
    \Swisseph\Coordinates::cartPolSp($xaphe, $aphe_pol);
    $aphe_pol[0] = \Swisseph\Math::radToDeg($aphe_pol[0]);
    $aphe_pol[1] = \Swisseph\Math::radToDeg($aphe_pol[1]);
    $aphe_pol[3] = \Swisseph\Math::radToDeg($aphe_pol[3]);
    $aphe_pol[4] = \Swisseph\Math::radToDeg($aphe_pol[4]);
    if ($aphe_pol[0] < 0) $aphe_pol[0] += 360.0;

    printf("Oscu Apogee (from OsculatingCalculator):\n");
    printf("  lon=%.7f°, lat=%.7f°, speed=%.7f°/d, dist=%.9f AU\n",
        $aphe_pol[0], $aphe_pol[1], $aphe_pol[3], $aphe_pol[2]);
    printf("  Reference: lon=%.7f°, lat=%.7f°, speed=%.7f°/d, dist=%.9f AU\n",
        $refs['Oscu Apogee']['lon'], $refs['Oscu Apogee']['lat'], $refs['Oscu Apogee']['speed'], $refs['Oscu Apogee']['dist']);
    printf("  Δlon=%.6f°\n\n", $aphe_pol[0] - $refs['Oscu Apogee']['lon']);
} else {
    echo "OsculatingCalculator failed: $serr\n";
}

echo "Done.\n";
