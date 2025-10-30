#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0; // J2000.0

echo "Jupiter Coordinate Systems Comparison\n";
echo "======================================\n\n";

// 1. BARYCENTRIC (with SEFLG_BARYCTR)
$iflag_bary = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ |
              Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_SPEED |
              Constants::SEFLG_BARYCTR;
$xx_bary = [];
$serr_bary = null;
$rc_bary = swe_calc($jd, Constants::SE_JUPITER, $iflag_bary, $xx_bary, $serr_bary);

echo "1. BARYCENTRIC (with SEFLG_BARYCTR flag):\n";
if ($rc_bary >= 0) {
    printf("   X = %.15f AU\n", $xx_bary[0]);
    printf("   Y = %.15f AU\n", $xx_bary[1]);
    printf("   Z = %.15f AU\n", $xx_bary[2]);
} else {
    echo "   Error: $serr_bary\n";
}
echo "\n";

// 2. HELIOCENTRIC (with SEFLG_HELCTR)
$iflag_helio = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ |
               Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_SPEED |
               Constants::SEFLG_HELCTR;
$xx_helio = [];
$serr_helio = null;
$rc_helio = swe_calc($jd, Constants::SE_JUPITER, $iflag_helio, $xx_helio, $serr_helio);

echo "2. HELIOCENTRIC (with SEFLG_HELCTR flag):\n";
if ($rc_helio >= 0) {
    printf("   X = %.15f AU\n", $xx_helio[0]);
    printf("   Y = %.15f AU\n", $xx_helio[1]);
    printf("   Z = %.15f AU\n", $xx_helio[2]);
} else {
    echo "   Error: $serr_helio\n";
}
echo "\n";

// 3. GEOCENTRIC (without BARYCTR and HELCTR flags)
$iflag_geo = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ |
             Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_SPEED;
$xx_geo = [];
$serr_geo = null;
$rc_geo = swe_calc($jd, Constants::SE_JUPITER, $iflag_geo, $xx_geo, $serr_geo);

echo "3. GEOCENTRIC (default, without BARYCTR/HELCTR flags):\n";
if ($rc_geo >= 0) {
    printf("   X = %.15f AU\n", $xx_geo[0]);
    printf("   Y = %.15f AU\n", $xx_geo[1]);
    printf("   Z = %.15f AU\n", $xx_geo[2]);
} else {
    echo "   Error: $serr_geo\n";
}
echo "\n";

// Calculate differences
if ($rc_bary >= 0 && $rc_helio >= 0 && $rc_geo >= 0) {
    echo "DIFFERENCES:\n";
    echo "============\n";
    printf("Heliocentric - Barycentric (should be ~Sun position):\n");
    printf("   ΔX = %.15f AU\n", $xx_helio[0] - $xx_bary[0]);
    printf("   ΔY = %.15f AU\n", $xx_helio[1] - $xx_bary[1]);
    printf("   ΔZ = %.15f AU\n", $xx_helio[2] - $xx_bary[2]);
    echo "\n";

    printf("Geocentric - Barycentric (should be ~-Earth position):\n");
    printf("   ΔX = %.15f AU\n", $xx_geo[0] - $xx_bary[0]);
    printf("   ΔY = %.15f AU\n", $xx_geo[1] - $xx_bary[1]);
    printf("   ΔZ = %.15f AU\n", $xx_geo[2] - $xx_bary[2]);
    echo "\n";
}

echo "\nC REFERENCE VALUES (from trace_full.exe):\n";
echo "==========================================\n";
echo "Barycentric: X = 3.994040571161513 AU\n";
echo "Heliocentric: X = 4.001177129987498 AU\n";
echo "Geocentric: X = 4.178312157164416 AU\n";
