<?php
/**
 * Test for swe_jdet_to_utc and swe_jdut1_to_utc functions
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

echo "=== swe_jdet_to_utc Tests ===\n\n";

// Test 1: Date before 1972 (should return UT1)
echo "Test 1: Date before 1972 (1970-01-01, should return UT1)\n";
$tjd_et = 2440588.5; // 1970-01-01 00:00 ET
$y = $m = $d = $h = $min = 0;
$sec = 0.0;
swe_jdet_to_utc($tjd_et, Constants::SE_GREG_CAL, $y, $m, $d, $h, $min, $sec);
printf("  Input JD(ET): %.6f\n", $tjd_et);
printf("  Output: %04d-%02d-%02d %02d:%02d:%06.3f\n", $y, $m, $d, $h, $min, $sec);
printf("  Note: Before 1972, returns UT1 (not UTC)\n\n");

// Test 2: Date after 1972 (with leap seconds)
echo "Test 2: Date in 2000 (2000-01-01 12:00:00 ET)\n";
// JD for 2000-01-01 12:00:00 TT
$tjd_et = 2451545.0; // J2000.0
swe_jdet_to_utc($tjd_et, Constants::SE_GREG_CAL, $y, $m, $d, $h, $min, $sec);
printf("  Input JD(ET): %.6f (J2000.0)\n", $tjd_et);
printf("  Output: %04d-%02d-%02d %02d:%02d:%06.3f UTC\n", $y, $m, $d, $h, $min, $sec);
printf("  Expected: around 2000-01-01 11:58:56 (accounting for Delta-T ~64s)\n\n");

// Test 3: Recent date (2025-12-13 12:00:00 ET)
echo "Test 3: Recent date (2025-12-13 12:00:00 ET)\n";
$tjd_et = swe_julday(2025, 12, 13, 12.0, Constants::SE_GREG_CAL);
// Add Delta-T to convert to ET
$serr = null;
$tjd_et += swe_deltat_ex($tjd_et, -1, $serr);
swe_jdet_to_utc($tjd_et, Constants::SE_GREG_CAL, $y, $m, $d, $h, $min, $sec);
printf("  Input JD(ET): %.6f\n", $tjd_et);
printf("  Output: %04d-%02d-%02d %02d:%02d:%06.3f UTC\n", $y, $m, $d, $h, $min, $sec);
printf("  Note: Should be close to input time (Delta-T ~69s in 2025)\n\n");

// Test 4: Leap second date (2016-12-31 23:59:60 UTC should exist)
echo "Test 4: Around leap second (2017-01-01 00:00:00 ET)\n";
$tjd_et = swe_julday(2017, 1, 1, 0.0, Constants::SE_GREG_CAL);
$tjd_et += swe_deltat_ex($tjd_et, -1, $serr);
swe_jdet_to_utc($tjd_et, Constants::SE_GREG_CAL, $y, $m, $d, $h, $min, $sec);
printf("  Input JD(ET): %.6f\n", $tjd_et);
printf("  Output: %04d-%02d-%02d %02d:%02d:%06.3f UTC\n", $y, $m, $d, $h, $min, $sec);
printf("  Note: Leap second was inserted at end of 2016-12-31\n\n");

echo "\n=== swe_jdut1_to_utc Tests ===\n\n";

// Test 5: UT1 to UTC conversion (2000-01-01 12:00 UT1)
echo "Test 5: UT1 to UTC (2000-01-01 12:00:00 UT1)\n";
$tjd_ut = 2451545.0;
swe_jdut1_to_utc($tjd_ut, Constants::SE_GREG_CAL, $y, $m, $d, $h, $min, $sec);
printf("  Input JD(UT1): %.6f\n", $tjd_ut);
printf("  Output: %04d-%02d-%02d %02d:%02d:%06.3f UTC\n", $y, $m, $d, $h, $min, $sec);
printf("  Note: UT1 ≈ UTC for practical purposes in 2000\n\n");

// Test 6: Julian calendar conversion
echo "Test 6: Julian calendar (1582-10-04, before Gregorian reform)\n";
$tjd_et = swe_julday(1582, 10, 4, 12.0, Constants::SE_JUL_CAL);
$tjd_et += swe_deltat_ex($tjd_et, -1, $serr);
swe_jdet_to_utc($tjd_et, Constants::SE_JUL_CAL, $y, $m, $d, $h, $min, $sec);
printf("  Input: 1582-10-04 12:00:00 (Julian)\n");
printf("  Output: %04d-%02d-%02d %02d:%02d:%06.3f (Julian calendar)\n", $y, $m, $d, $h, $min, $sec);
printf("  Note: Julian calendar used before Gregorian reform\n\n");

echo "All UTC conversion tests completed!\n";
