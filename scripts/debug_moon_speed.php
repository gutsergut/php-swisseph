<?php
/**
 * Debug Moon velocity calculation step by step
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path('C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe');

$jd = swe_julday(2025, 1, 1, 12.0, Constants::SE_GREG_CAL);
echo "JD: $jd\n\n";

// Test 1: Standard calculation
echo "=== Test 1: Standard (SWIEPH + SPEED) ===\n";
$xx = [];
$serr = '';
swe_calc_ut($jd, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);
$refSpeed = 13 + 32/60 + 39.1136/3600;
printf("Longitude speed: %.10f°/day (ref: %.10f, diff: %.4f\")\n", $xx[3], $refSpeed, ($xx[3] - $refSpeed) * 3600);

// Test 2: J2000 (no precession)
echo "\n=== Test 2: J2000 (no precession) ===\n";
$xx2 = [];
swe_calc_ut($jd, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000, $xx2, $serr);
printf("Longitude speed: %.10f°/day\n", $xx2[3]);

// Test 3: NONUT (no nutation)
echo "\n=== Test 3: NONUT (no nutation) ===\n";
$xx3 = [];
swe_calc_ut($jd, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NONUT, $xx3, $serr);
printf("Longitude speed: %.10f°/day\n", $xx3[3]);

// Test 4: J2000 + NONUT
echo "\n=== Test 4: J2000 + NONUT ===\n";
$xx4 = [];
swe_calc_ut($jd, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 | Constants::SEFLG_NONUT, $xx4, $serr);
printf("Longitude speed: %.10f°/day\n", $xx4[3]);

// Test 5: TRUEPOS (no light-time, no aberration)
echo "\n=== Test 5: TRUEPOS ===\n";
$xx5 = [];
swe_calc_ut($jd, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS, $xx5, $serr);
printf("Longitude speed: %.10f°/day\n", $xx5[3]);

// Test 6: TRUEPOS + NONUT + J2000 (minimal transformations)
echo "\n=== Test 6: TRUEPOS + NONUT + J2000 ===\n";
$xx6 = [];
swe_calc_ut($jd, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_J2000, $xx6, $serr);
printf("Longitude speed: %.10f°/day\n", $xx6[3]);
