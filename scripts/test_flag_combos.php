<?php
/**
 * Test flag combinations in swe_calc
 */
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_et = 2451545.0007387600; // J2000.0 ET
$serr = '';

echo "Testing swe_calc with different flag combinations\n";
echo "==================================================\n\n";

// Test 1: Default (ecliptic)
$xx1 = array_fill(0, 6, 0.0);
$flag1 = Constants::SEFLG_SPEED;
$ret1 = swe_calc($tjd_et, Constants::SE_JUPITER, $flag1, $xx1, $serr);
echo "1. SEFLG_SPEED (default ecliptic):\n";
printf("   Pos: [%.10f, %.10f, %.10f]\n", $xx1[0], $xx1[1], $xx1[2]);
printf("   Vel: [%.10f, %.10f, %.10f]\n\n", $xx1[3], $xx1[4], $xx1[5]);

// Test 2: Equatorial
$xx2 = array_fill(0, 6, 0.0);
$flag2 = Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL;
$ret2 = swe_calc($tjd_et, Constants::SE_JUPITER, $flag2, $xx2, $serr);
echo "2. SEFLG_SPEED | SEFLG_EQUATORIAL:\n";
printf("   Pos: [%.10f, %.10f, %.10f]\n", $xx2[0], $xx2[1], $xx2[2]);
printf("   Vel: [%.10f, %.10f, %.10f]\n\n", $xx2[3], $xx2[4], $xx2[5]);

// Test 3: XYZ
$xx3 = array_fill(0, 6, 0.0);
$flag3 = Constants::SEFLG_SPEED | Constants::SEFLG_XYZ;
$ret3 = swe_calc($tjd_et, Constants::SE_JUPITER, $flag3, $xx3, $serr);
echo "3. SEFLG_SPEED | SEFLG_XYZ:\n";
printf("   Pos: [%.10f, %.10f, %.10f]\n", $xx3[0], $xx3[1], $xx3[2]);
printf("   Vel: [%.10f, %.10f, %.10f]\n\n", $xx3[3], $xx3[4], $xx3[5]);

// Test 4: Equatorial XYZ
$xx4 = array_fill(0, 6, 0.0);
$flag4 = Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ;
$ret4 = swe_calc($tjd_et, Constants::SE_JUPITER, $flag4, $xx4, $serr);
echo "4. SEFLG_SPEED | SEFLG_EQUATORIAL | SEFLG_XYZ:\n";
printf("   Pos: [%.10f, %.10f, %.10f]\n", $xx4[0], $xx4[1], $xx4[2]);
printf("   Vel: [%.10f, %.10f, %.10f]\n\n", $xx4[3], $xx4[4], $xx4[5]);

// Test 5: Equatorial XYZ J2000
$xx5 = array_fill(0, 6, 0.0);
$flag5 = Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_J2000;
$ret5 = swe_calc($tjd_et, Constants::SE_JUPITER, $flag5, $xx5, $serr);
echo "5. SEFLG_SPEED | SEFLG_EQUATORIAL | SEFLG_XYZ | SEFLG_J2000:\n";
printf("   Pos: [%.10f, %.10f, %.10f]\n", $xx5[0], $xx5[1], $xx5[2]);
printf("   Vel: [%.10f, %.10f, %.10f]\n\n", $xx5[3], $xx5[4], $xx5[5]);

// Test 6: Full osculating flags
$xx6 = array_fill(0, 6, 0.0);
$flag6 = Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS |
         Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ |
         Constants::SEFLG_J2000;
$ret6 = swe_calc($tjd_et, Constants::SE_JUPITER, $flag6, $xx6, $serr);
echo "6. Full osculating flags (SEFLG_SPEED|TRUEPOS|EQUATORIAL|XYZ|J2000):\n";
printf("   Flag: 0x%X\n", $flag6);
printf("   Pos: [%.10f, %.10f, %.10f]\n", $xx6[0], $xx6[1], $xx6[2]);
printf("   Vel: [%.10f, %.10f, %.10f]\n\n", $xx6[3], $xx6[4], $xx6[5]);

echo "C reference for test 6:\n";
echo "   Pos: [4.0011736486, 2.7365835202, 1.0755136167]\n";
echo "   Vel: [-0.0045683228, 0.0058814572, 0.0026323019]\n";
