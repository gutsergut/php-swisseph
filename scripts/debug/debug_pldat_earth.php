<?php
/**
 * Debug SwedState pldat[SEI_EARTH]
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
swe_set_ephe_path($ephePath);

$tjdEt = 2451545.0;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

// First compute Jupiter to populate pldat[SEI_EARTH]
$x = [];
$serr = '';
swe_calc($tjdEt, Constants::SE_JUPITER, $iflag, $x, $serr);

// Now check what's in pldat[SEI_EARTH]
$swed = SwedState::getInstance();
$pedp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;

if ($pedp === null) {
    echo "pldat[SEI_EARTH] is NULL!\n";
    exit(1);
}

echo "SwedState pldat[SEI_EARTH] after swe_calc(JUPITER):\n";
echo sprintf("  teval = %.10f\n", $pedp->teval);
echo sprintf("  x = [%.15f, %.15f, %.15f]\n", $pedp->x[0] ?? 0, $pedp->x[1] ?? 0, $pedp->x[2] ?? 0);
echo sprintf("  velocity = [%.15f, %.15f, %.15f]\n", $pedp->x[3] ?? 0, $pedp->x[4] ?? 0, $pedp->x[5] ?? 0);

echo "\nC reference xear = [-0.184284294, 0.884779352, 0.383819005]\n";
$cEar = [-0.184284294, 0.884779352, 0.383819005];
echo sprintf("Difference: dX=%.9f AU = %.2f km\n", ($pedp->x[0] ?? 0) - $cEar[0], (($pedp->x[0] ?? 0) - $cEar[0]) * 149597870.7);
echo sprintf("            dY=%.9f AU = %.2f km\n", ($pedp->x[1] ?? 0) - $cEar[1], (($pedp->x[1] ?? 0) - $cEar[1]) * 149597870.7);
echo sprintf("            dZ=%.9f AU = %.2f km\n", ($pedp->x[2] ?? 0) - $cEar[2], (($pedp->x[2] ?? 0) - $cEar[2]) * 149597870.7);
