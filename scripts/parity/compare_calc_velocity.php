<?php
/**
 * Direct comparison of swe_calc velocities between PHP and C
 */
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_ut = 2451545.0; // J2000.0 UT
$deltaT = swe_deltat($tjd_ut);
$tjd_et = $tjd_ut + $deltaT;

echo "Testing swe_calc velocity accuracy\n";
echo "==================================\n\n";
echo "DeltaT: " . ($deltaT * 86400) . " seconds\n";
echo "tjd_et: $tjd_et\n\n";

$serr = '';
$xx = array_fill(0, 6, 0.0);

// SEFLG for osculating calculation (J2000 equatorial XYZ with speed)
$iflag = Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS |
         Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ |
         Constants::SEFLG_J2000;

$ret = swe_calc($tjd_et, Constants::SE_JUPITER, $iflag, $xx, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "PHP swe_calc result:\n";
printf("  Position: [%.15f, %.15f, %.15f]\n", $xx[0], $xx[1], $xx[2]);
printf("  Velocity: [%.15f, %.15f, %.15f]\n", $xx[3], $xx[4], $xx[5]);

echo "\nC reference (from test_oscu_nodes.exe):\n";
echo "  Position: [4.001173648563419, 2.736583520234065, 1.075513616723703]\n";
echo "  Velocity: [-0.004568322760009, 0.005881457158647, 0.002632301855211]\n";

echo "\nDifferences:\n";
$c_pos = [4.001173648563419, 2.736583520234065, 1.075513616723703];
$c_vel = [-0.004568322760009, 0.005881457158647, 0.002632301855211];

for ($i = 0; $i < 3; $i++) {
    $diff_pos = ($xx[$i] - $c_pos[$i]) * 149597870.7; // km
    $diff_vel = ($xx[$i+3] - $c_vel[$i]) * 149597870.7 * 1000; // m/day
    printf("  [%d] Pos diff: %.3f km, Vel diff: %.3f m/day\n", $i, $diff_pos, $diff_vel);
}
