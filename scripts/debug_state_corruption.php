<?php
/**
 * Debug state corruption between JPL and SWIEPH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

function dumpEarth(string $label): void {
    $swed = SwedState::getInstance();
    $pedp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;
    echo "$label:\n";
    if ($pedp === null) {
        echo "  Earth pldat is NULL\n";
    } else {
        echo "  teval=" . $pedp->teval . "\n";
        echo "  iephe=" . ($pedp->iephe ?? 'null') . " (JPL=1, SWI=2, MOS=4)\n";
        echo "  x[0..5] = [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), array_slice($pedp->x, 0, 6))) . "]\n";
        echo "  xreturn[0..5] = [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), array_slice($pedp->xreturn, 0, 6))) . "]\n";
    }
    echo "\n";
}

// === Test 1: SWIEPH only ===
echo "=== Test 1: SWIEPH Mercury ===\n";
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
dumpEarth("Before SWIEPH calc");

$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);
printf("SWIEPH Mercury: lon=%.6f, lat=%.6f, dist=%.6f AU\n\n", $xx[0], $xx[1], $xx[2]);
dumpEarth("After SWIEPH Mercury calc");

// === Test 2: JPL ===
echo "=== Test 2: JPL Mercury ===\n";
swe_set_ephe_path(__DIR__ . '/../../eph/data/ephemerides/jpl');
swe_set_jpl_file('de441.eph');
dumpEarth("Before JPL calc");

$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_JPLEPH | Constants::SEFLG_SPEED, $xx, $serr);
printf("JPL Mercury: lon=%.6f, lat=%.6f, dist=%.6f AU\n\n", $xx[0], $xx[1], $xx[2]);
dumpEarth("After JPL Mercury calc");

// === Test 3: SWIEPH after JPL ===
echo "=== Test 3: SWIEPH Mercury after JPL ===\n";
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
dumpEarth("Before second SWIEPH calc");

$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);
printf("SWIEPH Mercury after JPL: lon=%.6f, lat=%.6f, dist=%.6f AU\n\n", $xx[0], $xx[1], $xx[2]);
dumpEarth("After second SWIEPH Mercury calc");
