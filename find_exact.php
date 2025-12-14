<?php
/**
 * Find which planet call corrupts SUNBARY cache
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

define('SE_SUN', 0);
define('SE_MOON', 1);
define('SE_MERCURY', 2);
define('SE_VENUS', 3);
define('SEFLG_SPEED', 256);
define('SEFLG_EQUATORIAL', 2048);
define('SEFLG_RADIANS', 32768);
define('SEFLG_XYZ', 8);

$jd = 2451545.0;
$serr = null;

function dumpSunbary($label) {
    $swed = SwedState::getInstance();
    $psbdp = $swed->pldat[SwephConstants::SEI_SUNBARY] ?? null;
    if ($psbdp) {
        $dist = sqrt($psbdp->x[0]*$psbdp->x[0] + $psbdp->x[1]*$psbdp->x[1] + $psbdp->x[2]*$psbdp->x[2]);
        echo "$label: SUNBARY dist=$dist AU, x=[{$psbdp->x[0]},{$psbdp->x[1]},{$psbdp->x[2]}]\n";
        if ($dist > 0.1) {
            echo "  !!! CORRUPTED - should be ~0.007 AU !!!\n";
        }
    }
}

$flags = [0, SEFLG_SPEED, SEFLG_EQUATORIAL, SEFLG_RADIANS,
          SEFLG_SPEED | SEFLG_EQUATORIAL, SEFLG_RADIANS | SEFLG_EQUATORIAL,
          SEFLG_XYZ, SEFLG_XYZ | SEFLG_SPEED];
$flagNames = ['0', 'SPEED', 'EQUAT', 'RAD', 'SPEED|EQUAT', 'RAD|EQUAT', 'XYZ', 'XYZ|SPEED'];
$planetNames = ['Sun', 'Moon', 'Mercury'];

echo "=== Finding SUNBARY corruption ===\n\n";

// First call Venus to initialize SUNBARY correctly
$xx = [];
swe_calc($jd, SE_VENUS, SEFLG_SPEED, $xx, $serr);
dumpSunbary("After initial Venus");

// Now try each planet/flag combination
foreach ($planetNames as $pi => $pname) {
    foreach ($flags as $fi => $flag) {
        $xx = [];
        swe_calc($jd, $pi, $flag, $xx, $serr);
        dumpSunbary("After $pname/{$flagNames[$fi]}");
    }
}
