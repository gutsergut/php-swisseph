<?php
/**
 * Debug: trace xear through swe_nod_aps
 */
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Swe\Functions\NodesApsidesFunctions;
use Swisseph\Constants;

// Set env to enable debug
putenv('DEBUG_NODAPS=1');

$ephePath = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe';
SwedState::getInstance()->setEphePath($ephePath);

$tjdEt = 2451545.0; // J2000.0
$ipl = Constants::SE_JUPITER;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$method = 2; // SE_NODBIT_OSCU

// Storage for results
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';

// Before call - check pldat
$swed = SwedState::getInstance();
echo "=== Before swe_nod_aps call ===\n";
$pedp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;
if ($pedp) {
    echo "pldat[SEI_EARTH].teval = {$pedp->teval}\n";
    echo "pldat[SEI_EARTH].x = [" . implode(', ', array_map(fn($v) => sprintf('%.15f', $v), array_slice($pedp->x, 0, 6))) . "]\n";
}

// Call - but we need to add trace inside applyOsculatingNodApsTransformations
echo "\n=== swe_nod_aps call ===\n";
$ret = NodesApsidesFunctions::nodAps($tjdEt, $ipl, $iflag, $method, $xnasc, $xndsc, $xperi, $xaphe, $serr);

echo "\n=== After swe_nod_aps call ===\n";
$pedp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;
if ($pedp) {
    echo "pldat[SEI_EARTH].teval = {$pedp->teval}\n";
    echo "pldat[SEI_EARTH].x = [" . implode(', ', array_map(fn($v) => sprintf('%.15f', $v), array_slice($pedp->x, 0, 6))) . "]\n";
}

$cXear = [-0.184284294, 0.884779352, 0.383819005, -0.017203037, -0.003029234, -0.001312948];
echo "\nC reference xear:\n";
echo "x = [" . implode(', ', array_map(fn($v) => sprintf('%.15f', $v), array_slice($cXear, 0, 6))) . "]\n";

if ($pedp) {
    echo "\nDifferences:\n";
    for ($i = 0; $i < 6; $i++) {
        $diff = $pedp->x[$i] - $cXear[$i];
        $diffKm = $diff * 149597870.7; // AU to km
        echo sprintf("x[%d]: diff = %.15f AU = %.2f km\n", $i, $diff, $diffKm);
    }
}

echo "\n=== Result ===\n";
echo "ret = $ret\n";
echo "Ascending Node: lon = {$xnasc[0]}°\n";
