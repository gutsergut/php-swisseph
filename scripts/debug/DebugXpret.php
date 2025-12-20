<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

// Enable full debug mode
putenv('DEBUG_OSCU=1');

$jd_ut = 2460677.0; // 2025-01-01 12:00 UT
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH, $serr);

// Calculate Moon
$xx = [];
$serr = '';
$ret = swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xx, $serr);

printf("Moon result:\n");
printf("  Return code: %d\n", $ret);
printf("  Longitude: %.6f°\n", $xx[0]);
printf("  Latitude: %.6f°\n", $xx[1]);
printf("  Distance: %.9f AU (%.0f km)\n", $xx[2], $xx[2] * 149597870.7);

// Calculate Sun
$xx_sun = [];
$serr_sun = '';
$ret_sun = swe_calc($jd_et, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx_sun, $serr_sun);

printf("\nSun result:\n");
printf("  Return code: %d\n", $ret_sun);
printf("  Longitude: %.6f°\n", $xx_sun[0]);
printf("  Latitude: %.6f°\n", $xx_sun[1]);
printf("  Distance: %.9f AU (%.0f km)\n", $xx_sun[2], $xx_sun[2] * 149597870.7);
