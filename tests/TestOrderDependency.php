<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

$jd_ut = 2460677.0;
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH, $serr);

echo "=== Test Moon/Sun order dependency ===\n\n";

// Test 1: Calculate MOON FIRST, then SUN
echo "Test 1: Moon FIRST, Sun SECOND:\n";
$xx_moon = [];
$ret_moon = swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xx_moon, $serr);
printf("  Moon: %.9f AU (%.0f km)\n", $xx_moon[2], $xx_moon[2] * 149597870.7);

$xx_sun = [];
$ret_sun = swe_calc($jd_et, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx_sun, $serr);
printf("  Sun:  %.9f AU (%.0f km)\n", $xx_sun[2], $xx_sun[2] * 149597870.7);

// Test 2: Calculate SUN FIRST, then MOON
echo "\nTest 2: Sun FIRST, Moon SECOND:\n";

// Clear cache
swe_close();
swe_set_ephe_path($ephePath);

$xx_sun2 = [];
$ret_sun2 = swe_calc($jd_et, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx_sun2, $serr);
printf("  Sun:  %.9f AU (%.0f km)\n", $xx_sun2[2], $xx_sun2[2] * 149597870.7);

$xx_moon2 = [];
$ret_moon2 = swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xx_moon2, $serr);
printf("  Moon: %.9f AU (%.0f km)\n", $xx_moon2[2], $xx_moon2[2] * 149597870.7);

echo "\nExpected values:\n";
echo "  Sun:  0.983 AU\n";
echo "  Moon: 0.003 AU\n";
