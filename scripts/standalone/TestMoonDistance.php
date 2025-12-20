<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

$jd_ut = 2460677.0;
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH, $serr);

echo "=== Test Moon distance calculation ===\n\n";

// Test 1: Geocentric
$xc1 = [];
swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL, $xc1, $serr);
printf("Geocentric (no TOPOCTR):\n");
printf("  RA  = %.6f°\n", $xc1[0]);
printf("  Dec = %.6f°\n", $xc1[1]);
printf("  Distance = %.9f AU = %.0f km\n\n", $xc1[2], $xc1[2] * 149597870.7);

// Test 2: Topocentric (Berlin)
swe_set_topo(13.4, 52.5, 0.0);
$xc2 = [];
swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR, $xc2, $serr);
printf("Topocentric (Berlin, TOPOCTR set):\n");
printf("  RA  = %.6f°\n", $xc2[0]);
printf("  Dec = %.6f°\n", $xc2[1]);
printf("  Distance = %.9f AU = %.0f km\n", $xc2[2], $xc2[2] * 149597870.7);
printf("  Full xc array: [%.6f, %.6f, %.9f, %.6f, %.6f, %.6f]\n\n",
    $xc2[0], $xc2[1], $xc2[2], $xc2[3], $xc2[4], $xc2[5]);

printf("Expected Moon distance: ~0.00257 AU = ~384,400 km\n");
printf("Difference: %.9f AU (geocentric - topocentric)\n", abs($xc1[2] - $xc2[2]));
