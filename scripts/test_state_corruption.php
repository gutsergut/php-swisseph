<?php
/**
 * Test JPL vs SWIEPH - check if JPL corrupts state
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// === Test 1: SWIEPH only (should work) ===
echo "=== Test 1: SWIEPH only ===\n";
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);
printf("SWIEPH Mercury: lon=%.6f, lat=%.6f, dist=%.6f AU\n\n", $xx[0], $xx[1], $xx[2]);

// === Test 2: JPL only ===
echo "=== Test 2: JPL only ===\n";
swe_close();  // Reset state
swe_set_ephe_path(__DIR__ . '/../../eph/data/ephemerides/jpl');
swe_set_jpl_file('de441.eph');
$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_JPLEPH | Constants::SEFLG_SPEED, $xx, $serr);
printf("JPL Mercury: lon=%.6f, lat=%.6f, dist=%.6f AU\n\n", $xx[0], $xx[1], $xx[2]);

// === Test 3: SWIEPH after JPL (may be corrupted?) ===
echo "=== Test 3: SWIEPH after JPL (same session) ===\n";
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);
printf("SWIEPH Mercury after JPL: lon=%.6f, lat=%.6f, dist=%.6f AU\n\n", $xx[0], $xx[1], $xx[2]);

// === Test 4: SWIEPH after swe_close ===
echo "=== Test 4: SWIEPH after swe_close ===\n";
swe_close();  // Full reset
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);
printf("SWIEPH Mercury after close: lon=%.6f, lat=%.6f, dist=%.6f AU\n", $xx[0], $xx[1], $xx[2]);
