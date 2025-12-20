<?php
/**
 * Debug swe_calc for exact comparison with C
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

$tjd = 2451545.0007387600; // Use same tjd as PHP now generates
$ipl = Constants::SE_JUPITER;

// Exact same flags as C: SEFLG_HELCTR | SEFLG_J2000 | SEFLG_TRUEPOS | SEFLG_NONUT | SEFLG_SPEED | SEFLG_XYZ | SEFLG_EQUATORIAL
$iflag = 0x197A;
$xx = [];
$serr = '';
$ret = swe_calc($tjd, $ipl, $iflag, $xx, $serr);
printf("PHP swe_calc tjd=%.16f iflag=0x%04X:\n", $tjd, $iflag);
printf("  x=[%.15f, %.15f, %.15f]\n", $xx[0], $xx[1], $xx[2]);
printf("  v=[%.15f, %.15f, %.15f]\n", $xx[3], $xx[4], $xx[5]);

// Compare with C reference:
// DEBUG C swe_calc [i=1] tjd=2451545.0007387605, iflJ2000=0x197A: xpos=[4.001173648563419, 2.736583520234065, 1.075513616723703, -0.004568322760009, 0.005881457158647, 0.002632301855211]
$c_x = [4.001173648563419, 2.736583520234065, 1.075513616723703];
$c_v = [-0.004568322760009, 0.005881457158647, 0.002632301855211];

echo "\nC reference:\n";
printf("  x=[%.15f, %.15f, %.15f]\n", $c_x[0], $c_x[1], $c_x[2]);
printf("  v=[%.15f, %.15f, %.15f]\n", $c_v[0], $c_v[1], $c_v[2]);

echo "\nDifference (PHP - C) in km / m/day:\n";
$AU_KM = 149597870.7;
for ($i = 0; $i < 3; $i++) {
    $diff_pos = ($xx[$i] - $c_x[$i]) * $AU_KM;
    $diff_vel = ($xx[$i + 3] - $c_v[$i]) * $AU_KM;
    printf("  [%d] pos: %.2f km, vel: %.2f m/day\n", $i, $diff_pos, $diff_vel);
}
