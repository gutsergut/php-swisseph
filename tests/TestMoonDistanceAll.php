<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

$jd_ut = 2460677.0;
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH, $serr);

echo "=== Test Moon distance with different coordinate systems ===\n\n";

// Test 1: Ecliptic geocentric
$xc1 = [];
swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xc1, $serr);
printf("1. Ecliptic Geocentric:\n");
printf("   Lon = %.6f°, Lat = %.6f°, Distance = %.9f AU (%.0f km)\n\n",
    $xc1[0], $xc1[1], $xc1[2], $xc1[2] * 149597870.7);

// Test 2: Ecliptic topocentric (Berlin)
swe_set_topo(13.4, 52.5, 0.0);
$xc2 = [];
swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_TOPOCTR, $xc2, $serr);
printf("2. Ecliptic Topocentric (Berlin):\n");
printf("   Lon = %.6f°, Lat = %.6f°, Distance = %.9f AU (%.0f km)\n\n",
    $xc2[0], $xc2[1], $xc2[2], $xc2[2] * 149597870.7);

// Test 3: Equatorial geocentric
$xc3 = [];
swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL, $xc3, $serr);
printf("3. Equatorial Geocentric:\n");
printf("   RA = %.6f°, Dec = %.6f°, Distance = %.9f AU (%.0f km)\n\n",
    $xc3[0], $xc3[1], $xc3[2], $xc3[2] * 149597870.7);

// Test 4: Equatorial topocentric (Berlin) - THIS IS WHAT RiseSetFunctions USES!
$xc4 = [];
swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR, $xc4, $serr);
printf("4. Equatorial Topocentric (Berlin) - USED BY RiseSetFunctions:\n");
printf("   RA = %.6f°, Dec = %.6f°, Distance = %.9f AU (%.0f km)\n\n",
    $xc4[0], $xc4[1], $xc4[2], $xc4[2] * 149597870.7);

printf("Expected Moon distance: ~0.00257 AU (~384,400 km)\n\n");

printf("Distance comparisons:\n");
printf("  Geocentric vs Topocentric (ecliptic):  Δ = %.9f AU\n", abs($xc1[2] - $xc2[2]));
printf("  Geocentric vs Topocentric (equatorial): Δ = %.9f AU\n", abs($xc3[2] - $xc4[2]));
printf("  Ecliptic vs Equatorial (geocentric):    Δ = %.9f AU\n", abs($xc1[2] - $xc3[2]));
printf("  Ecliptic vs Equatorial (topocentric):   Δ = %.9f AU\n", abs($xc2[2] - $xc4[2]));
