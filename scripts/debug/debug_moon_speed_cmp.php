<?php
/**
 * Debug Moon speed - compare with C test
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd_ut = swe_julday(2025, 1, 1, 12.0, Constants::SE_GREG_CAL);
$deltat = swe_deltat($jd_ut);
$jd_tt = $jd_ut + $deltat;
echo "JD UT: $jd_ut\n";
echo sprintf("JD TT: %.10f\n\n", $jd_tt);

// Test 1: Standard
echo "=== Test 1: Standard (SWIEPH + SPEED) ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$xx = [];
$ret = swe_calc_ut($jd_ut, Constants::SE_MOON, $iflag, $xx, $serr);
echo sprintf("Longitude: %.10f deg\n", $xx[0]);
echo sprintf("Speed:     %.10f deg/day\n", $xx[3]);

// Test 5: TRUEPOS
echo "\n=== Test 5: TRUEPOS ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS;
$xx = [];
$ret = swe_calc_ut($jd_ut, Constants::SE_MOON, $iflag, $xx, $serr);
echo sprintf("Speed:     %.10f deg/day\n", $xx[3]);

// Test 7: XYZ speeds
echo "\n=== Test 7: XYZ speeds ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_XYZ;
$xx = [];
$ret = swe_calc_ut($jd_ut, Constants::SE_MOON, $iflag, $xx, $serr);
echo sprintf("X speed:   %.15e AU/day\n", $xx[3]);
echo sprintf("Y speed:   %.15e AU/day\n", $xx[4]);
echo sprintf("Z speed:   %.15e AU/day\n", $xx[5]);

// Test 8: Earth positions at t and t-dt
echo "\n=== Test 8: Earth positions for speed correction ===\n";

// Get Moon geocentric XYZ
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_XYZ;
$xx = [];
$ret = swe_calc($jd_tt, Constants::SE_MOON, $iflag, $xx, $serr);
echo sprintf("Moon geocentric XYZ: [%.15e, %.15e, %.15e]\n", $xx[0], $xx[1], $xx[2]);

// dt = distance / c
$dist = sqrt($xx[0]**2 + $xx[1]**2 + $xx[2]**2);
$dt = $dist * 1.49597870700e8 / 299792.458 / 86400.0;
echo sprintf("Moon distance: %.10f AU\n", $dist);
echo sprintf("Light-time dt: %.15e days (%.6f seconds)\n", $dt, $dt * 86400.0);

// Earth heliocentric at t
echo "\n--- Earth heliocentric ---\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_XYZ | Constants::SEFLG_HELCTR;
$xearth = [];
$ret = swe_calc($jd_tt, Constants::SE_EARTH, $iflag, $xearth, $serr);
echo sprintf("Earth heliocentric at t:\n");
echo sprintf("  pos: [%.15e, %.15e, %.15e]\n", $xearth[0], $xearth[1], $xearth[2]);
echo sprintf("  spd: [%.15e, %.15e, %.15e]\n", $xearth[3], $xearth[4], $xearth[5]);

$xearth2 = [];
$ret = swe_calc($jd_tt - $dt, Constants::SE_EARTH, $iflag, $xearth2, $serr);
echo sprintf("Earth heliocentric at t-dt:\n");
echo sprintf("  pos: [%.15e, %.15e, %.15e]\n", $xearth2[0], $xearth2[1], $xearth2[2]);
echo sprintf("  spd: [%.15e, %.15e, %.15e]\n", $xearth2[3], $xearth2[4], $xearth2[5]);

echo sprintf("\nxobs - xobs2 (speed difference):\n");
echo sprintf("  [%.15e, %.15e, %.15e] AU/day\n",
    $xearth[3] - $xearth2[3],
    $xearth[4] - $xearth2[4],
    $xearth[5] - $xearth2[5]);

$speed_diff_x = $xearth[3] - $xearth2[3];
$speed_diff_y = $xearth[4] - $xearth2[4];
$speed_diff_z = $xearth[5] - $xearth2[5];
$speed_diff_mag = sqrt($speed_diff_x**2 + $speed_diff_y**2 + $speed_diff_z**2);
$angular_effect = $speed_diff_mag / $dist * 206265.0;
echo sprintf("\nAngular effect of xobs-xobs2: %.4f arcsec/day\n", $angular_effect);

// Now compare speed difference
$c_standard = 13.5441982199;
$c_truepos = 13.5441552199;

echo "\n=== Speed difference analysis ===\n";
echo sprintf("C: Standard - TRUEPOS = %.10f deg/day = %.4f arcsec/day\n",
    $c_standard - $c_truepos,
    ($c_standard - $c_truepos) * 3600);
