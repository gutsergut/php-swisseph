#!/usr/bin/env php
<?php
/**
 * Test if multiple calc calls with different flags interfere
 */

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0;
$ipl = Constants::SE_SATURN;

echo "Test: Multiple calc calls with different flags\n";
echo "===============================================\n\n";

// FIRST CALL: ECLIPTIC DATE (like first calc in OsculatingCalculator)
$iflg0 = Constants::SEFLG_SWIEPH |
         Constants::SEFLG_SPEED |
         Constants::SEFLG_TRUEPOS |
         Constants::SEFLG_NONUT |
         Constants::SEFLG_HELCTR;

echo "FIRST CALL (ECLIPTIC DATE, polar):\n";
echo sprintf("  iflag: 0x%X\n", $iflg0);
$xx1 = [];
$serr = '';
swe_calc($jd, $ipl, $iflg0, $xx1, $serr);
echo sprintf("  lon=%.6f°, lat=%.6f°, r=%.6f AU\n\n", $xx1[0], $xx1[1], $xx1[2]);

// SECOND CALL: EQUATORIAL J2000 XYZ (like loop calc in OsculatingCalculator)
$iflJ2000 = Constants::SEFLG_SWIEPH |
            Constants::SEFLG_J2000 |
            Constants::SEFLG_EQUATORIAL |
            Constants::SEFLG_XYZ |
            Constants::SEFLG_TRUEPOS |
            Constants::SEFLG_NONUT |
            Constants::SEFLG_SPEED |
            Constants::SEFLG_HELCTR;

echo "SECOND CALL (EQUATORIAL J2000 XYZ):\n";
echo sprintf("  iflag: 0x%X\n", $iflJ2000);
$xx2 = [];
swe_calc($jd, $ipl, $iflJ2000, $xx2, $serr);
echo sprintf("  X=%.15f AU\n", $xx2[0]);
echo sprintf("  Y=%.15f AU\n", $xx2[1]);
echo sprintf("  Z=%.15f AU\n\n", $xx2[2]);

echo "Expected X (from direct test): 6.406408856647354 AU\n";
echo sprintf("Got X: %.15f AU\n", $xx2[0]);
echo sprintf("Difference: %.15f AU (%.1f km)\n", $xx2[0] - 6.406408856647354, ($xx2[0] - 6.406408856647354) * 149597870.7);
