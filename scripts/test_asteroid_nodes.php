<?php
/**
 * Test asteroid nodes calculation
 * Compare PHP vs C swetest64 for Ceres (10001) osculating nodes
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Initialize
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2451545.0; // J2000.0
$ceres = 10001;  // SE_AST_OFFSET + 1

// Calculate osculating nodes for Ceres
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;

echo "Testing asteroid nodes for Ceres (ipl=10001)\n";
echo "JD: $jd (J2000.0)\n\n";

$ret = swe_nod_aps_ut(
    $jd,
    $ceres,
    Constants::SEFLG_SWIEPH | Constants::SE_NODBIT_OSCU,
    Constants::SE_NODBIT_OSCU,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "PHP Results:\n";
echo sprintf("  Ascending Node:  lon=%.6f, lat=%.6f, dist=%.6f AU\n", $xnasc[0], $xnasc[1], $xnasc[2]);
echo sprintf("  Descending Node: lon=%.6f, lat=%.6f, dist=%.6f AU\n", $xndsc[0], $xndsc[1], $xndsc[2]);
echo sprintf("  Perihelion:      lon=%.6f, lat=%.6f, dist=%.6f AU\n", $xperi[0], $xperi[1], $xperi[2]);
echo sprintf("  Aphelion:        lon=%.6f, lat=%.6f, dist=%.6f AU\n", $xaphe[0], $xaphe[1], $xaphe[2]);

echo "\n--- Comparing with C swetest64 ---\n";

// Get reference from swetest64
$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';
$cmd = "cmd /c \"\"$swetest\" -b01.01.2000 -ut12:00 -p1 -N2 -fPl -head\"";

echo "Running: swetest64 -b01.01.2000 -ut12:00 -p1 -N2 -fPl -head\n";
$output = shell_exec($cmd);
echo "\nswetest64 output:\n$output\n";

// Also try with the actual JD
$cmd2 = "cmd /c \"\"$swetest\" -bj2451545.0 -p1 -N2 -fPl -head\"";
echo "Running: swetest64 -bj2451545.0 -p1 -N2 -fPl -head\n";
$output2 = shell_exec($cmd2);
echo "\nswetest64 output:\n$output2\n";

echo "\nDone.\n";
