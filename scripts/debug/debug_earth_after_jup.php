<?php
/**
 * Debug script to check Earth position in pldat after Jupiter calculation
 */
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;

$ephePath = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe';
SwedState::getInstance()->setEphePath($ephePath);

$tjdEt = 2451545.0; // J2000.0

echo "=== Check pldat[SEI_EARTH] at different stages ===\n\n";

$swed = SwedState::getInstance();

echo "1. Before any calculation:\n";
$pedp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;
if ($pedp) {
    echo "   teval = {$pedp->teval}\n";
    echo "   x = [" . implode(', ', array_map(fn($v) => sprintf('%.15f', $v), array_slice($pedp->x, 0, 6))) . "]\n";
} else {
    echo "   pldat[SEI_EARTH] is null\n";
}

// Call swe_calc for Jupiter with TOPOCTR-like flags (similar to line 5428)
echo "\n2. After swe_calc(JUPITER, iflg0):\n";
$iflg0 = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS | Constants::SEFLG_HELCTR;
$x = [];
$serr = '';
$ret = PlanetsFunctions::calc($tjdEt, Constants::SE_JUPITER, $iflg0, $x, $serr);
echo "   ret = $ret, serr = '$serr'\n";

$pedp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;
if ($pedp) {
    echo "   teval = {$pedp->teval}\n";
    echo "   x = [" . implode(', ', array_map(fn($v) => sprintf('%.15f', $v), array_slice($pedp->x, 0, 6))) . "]\n";

    // Compare with C reference
    $cXear = [-0.184284294, 0.884779352, 0.383819005, -0.017203037, -0.003029234, -0.001312948];
    echo "\n   C reference xear:\n";
    echo "   x = [" . implode(', ', array_map(fn($v) => sprintf('%.15f', $v), array_slice($cXear, 0, 6))) . "]\n";

    echo "\n   Differences:\n";
    for ($i = 0; $i < 6; $i++) {
        $diff = $pedp->x[$i] - $cXear[$i];
        $diffKm = $diff * 149597870.7; // AU to km
        echo sprintf("   x[%d]: diff = %.15f AU = %.2f km\n", $i, $diff, $diffKm);
    }
}

// Now let's see what swe_calc with SE_EARTH returns
echo "\n3. Direct swe_calc(SE_EARTH, BARYCTR|EQUATORIAL|J2000|XYZ):\n";
$earthFlags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 |
              Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR;
$xEarth = [];
$serr = '';
$ret = PlanetsFunctions::calc($tjdEt, Constants::SE_EARTH, $earthFlags, $xEarth, $serr);
echo "   ret = $ret, serr = '$serr'\n";
echo "   xEarth = [" . implode(', ', array_map(fn($v) => sprintf('%.15f', $v), array_slice($xEarth, 0, 6))) . "]\n";

echo "\n   Differences from C xear:\n";
$cXear = [-0.184284294, 0.884779352, 0.383819005, -0.017203037, -0.003029234, -0.001312948];
for ($i = 0; $i < 6; $i++) {
    $diff = $xEarth[$i] - $cXear[$i];
    $diffKm = $diff * 149597870.7; // AU to km
    echo sprintf("   x[%d]: diff = %.15f AU = %.2f km\n", $i, $diff, $diffKm);
}

echo "\n4. Compare pldat content after Earth calculation:\n";
$pedp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;
if ($pedp) {
    echo "   teval = {$pedp->teval}\n";
    echo "   x = [" . implode(', ', array_map(fn($v) => sprintf('%.15f', $v), array_slice($pedp->x, 0, 6))) . "]\n";
}
