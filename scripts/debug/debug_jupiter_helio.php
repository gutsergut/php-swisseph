<?php
/**
 * Direct comparison: swe_calc for Jupiter heliocentric J2000 equatorial XYZ
 */
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

$tjd = 2451545.0; // J2000.0

// Flags for heliocentric J2000 equatorial XYZ (matches C osculating calc)
$iflJ2000 = Constants::SEFLG_SWIEPH |
            Constants::SEFLG_J2000 |
            Constants::SEFLG_EQUATORIAL |
            Constants::SEFLG_XYZ |
            Constants::SEFLG_TRUEPOS |
            Constants::SEFLG_NONUT |
            Constants::SEFLG_SPEED |
            Constants::SEFLG_HELCTR;

$x = [];
$serr = '';
$ret = PlanetsFunctions::calc($tjd, Constants::SE_JUPITER, $iflJ2000, $x, $serr);

echo "=== Jupiter heliocentric J2000 equatorial XYZ at J2000.0 ===\n\n";
echo "PHP result (ret=$ret):\n";
printf("  X = %.15f AU\n", $x[0]);
printf("  Y = %.15f AU\n", $x[1]);
printf("  Z = %.15f AU\n", $x[2]);
printf("  vX = %.15f AU/day\n", $x[3]);
printf("  vY = %.15f AU/day\n", $x[4]);
printf("  vZ = %.15f AU/day\n", $x[5]);

// C reference from test_oscu_nodes debug for i=1 (tjd=2451545.0):
// DEBUG C swi_plan_for_osc_elem INPUT: xx=[4.0011736486, 2.7365835202, 1.0755136167, -0.0045683228, 0.0058814572, 0.0026323019]
$cRef = [4.0011736486, 2.7365835202, 1.0755136167, -0.0045683228, 0.0058814572, 0.0026323019];

echo "\nC reference (from osculating debug):\n";
printf("  X = %.15f AU\n", $cRef[0]);
printf("  Y = %.15f AU\n", $cRef[1]);
printf("  Z = %.15f AU\n", $cRef[2]);
printf("  vX = %.15f AU/day\n", $cRef[3]);
printf("  vY = %.15f AU/day\n", $cRef[4]);
printf("  vZ = %.15f AU/day\n", $cRef[5]);

echo "\nDifferences:\n";
for ($i = 0; $i < 6; $i++) {
    $diff = $x[$i] - $cRef[$i];
    $diffKm = $diff * 149597870.7;
    $label = $i < 3 ? ['X', 'Y', 'Z'][$i] : ['vX', 'vY', 'vZ'][$i - 3];
    printf("  %s: diff = %.15f AU = %.2f km\n", $label, $diff, $diffKm);
}

// Calculate total position error
$dx = $x[0] - $cRef[0];
$dy = $x[1] - $cRef[1];
$dz = $x[2] - $cRef[2];
$totalErr = sqrt($dx*$dx + $dy*$dy + $dz*$dz) * 149597870.7;
echo sprintf("\nTotal position error: %.2f km\n", $totalErr);
