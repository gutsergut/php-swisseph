<?php

declare(strict_types=1);

/**
 * Test swe_houses_armc() and swe_houses_armc_ex2()
 *
 * These functions calculate houses from ARMC (sidereal time) without a date,
 * useful for composite charts and progressive charts.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "======================================================================\n";
echo "Testing Houses ARMC Functions\n";
echo "======================================================================\n\n";

// Test 1: Compare swe_houses_armc with swe_houses_ex2 for Placidus
echo "Test 1: Verify swe_houses_armc matches swe_houses_ex2 (Placidus)\n";
echo "----------------------------------------------------------------------\n";

$jd_ut = swe_julday(2025, 1, 13, 12.0, 1); // 2025-01-13 12:00 UT
$geolat = 52.52; // Berlin latitude
$geolon = 13.41; // Berlin longitude
$hsys = 'P'; // Placidus

// Calculate using swe_houses_ex2 (normal method)
$cusp1 = [];
$ascmc1 = [];
swe_houses_ex2($jd_ut, 0, $geolat, $geolon, $hsys, $cusp1, $ascmc1);

// Get ARMC and epsilon from ascmc1
$armc = $ascmc1[2]; // ARMC in degrees
$eps = 23.44; // Approximate obliquity

echo "From swe_houses_ex2:\n";
echo "  ARMC: " . number_format($armc, 6) . "°\n";
echo "  Asc:  " . number_format($ascmc1[0], 6) . "°\n";
echo "  MC:   " . number_format($ascmc1[1], 6) . "°\n";
echo "  Cusp 1: " . number_format($cusp1[1], 6) . "°\n";
echo "  Cusp 10: " . number_format($cusp1[10], 6) . "°\n\n";

// Calculate using swe_houses_armc
$cusp2 = [];
$ascmc2 = [];
swe_houses_armc($armc, $geolat, $eps, $hsys, $cusp2, $ascmc2);

echo "From swe_houses_armc:\n";
echo "  Asc:  " . number_format($ascmc2[0], 6) . "°\n";
echo "  MC:   " . number_format($ascmc2[1], 6) . "°\n";
echo "  ARMC: " . number_format($ascmc2[2], 6) . "°\n";
echo "  Cusp 1: " . number_format($cusp2[1], 6) . "°\n";
echo "  Cusp 10: " . number_format($cusp2[10], 6) . "°\n\n";

// Compare differences
$diff_asc = abs($ascmc1[0] - $ascmc2[0]);
$diff_mc = abs($ascmc1[1] - $ascmc2[1]);
$diff_c1 = abs($cusp1[1] - $cusp2[1]);
$diff_c10 = abs($cusp1[10] - $cusp2[10]);

echo "Differences:\n";
echo "  Asc: " . number_format($diff_asc, 8) . "° (should be <0.1°)\n";
echo "  MC:  " . number_format($diff_mc, 8) . "° (should be <0.1°)\n";
echo "  Cusp 1:  " . number_format($diff_c1, 8) . "°\n";
echo "  Cusp 10: " . number_format($diff_c10, 8) . "°\n";

if ($diff_asc < 0.1 && $diff_mc < 0.1 && $diff_c1 < 0.1 && $diff_c10 < 0.1) {
    echo "  ✓ Test 1 PASSED\n\n";
} else {
    echo "  ✗ Test 1 FAILED\n\n";
}

// Test 2: Gauquelin sectors (36 cusps)
echo "Test 2: Gauquelin sectors with swe_houses_armc\n";
echo "----------------------------------------------------------------------\n";

$cusp_g = [];
$ascmc_g = [];
swe_houses_armc($armc, $geolat, $eps, 'G', $cusp_g, $ascmc_g);

echo "Gauquelin sectors (first 6 and last 6):\n";
for ($i = 1; $i <= 6; $i++) {
    echo "  Sector $i: " . number_format($cusp_g[$i], 4) . "°\n";
}
echo "  ...\n";
for ($i = 31; $i <= 36; $i++) {
    echo "  Sector $i: " . number_format($cusp_g[$i], 4) . "°\n";
}

$all_valid = true;
for ($i = 1; $i <= 36; $i++) {
    if ($cusp_g[$i] < 0 || $cusp_g[$i] >= 360) {
        $all_valid = false;
        break;
    }
}

if ($all_valid && count($cusp_g) == 37) {
    echo "  ✓ Test 2 PASSED (36 sectors, all in valid range)\n\n";
} else {
    echo "  ✗ Test 2 FAILED\n\n";
}

// Test 3: swe_houses_armc_ex2 with speed calculation
echo "Test 3: swe_houses_armc_ex2 with speed arrays\n";
echo "----------------------------------------------------------------------\n";

$cusp3 = [];
$ascmc3 = [];
$cusp_speed = [];
$ascmc_speed = [];
$serr = null;

$ret = swe_houses_armc_ex2($armc, $geolat, $eps, $hsys, $cusp3, $ascmc3, $cusp_speed, $ascmc_speed, $serr);

echo "Return code: $ret\n";
if ($serr) {
    echo "Error: $serr\n";
}

echo "Cusps:\n";
for ($i = 1; $i <= 12; $i++) {
    echo "  Cusp $i: " . number_format($cusp3[$i], 4) . "° (speed: " . number_format($cusp_speed[$i], 6) . "°/day)\n";
}

echo "\nAdditional points:\n";
echo "  Asc: " . number_format($ascmc3[0], 4) . "° (speed: " . number_format($ascmc_speed[0], 6) . "°/day)\n";
echo "  MC:  " . number_format($ascmc3[1], 4) . "° (speed: " . number_format($ascmc_speed[1], 6) . "°/day)\n";

if ($ret == 0 && count($cusp_speed) == 13 && count($ascmc_speed) == 10) {
    echo "  ✓ Test 3 PASSED\n\n";
} else {
    echo "  ✗ Test 3 FAILED\n\n";
}

// Test 4: Equal houses (system 'E') - should be simple
echo "Test 4: Equal houses (Asc-based) with swe_houses_armc\n";
echo "----------------------------------------------------------------------\n";

$cusp_e = [];
$ascmc_e = [];
swe_houses_armc($armc, $geolat, $eps, 'E', $cusp_e, $ascmc_e);

echo "Equal houses cusps:\n";
for ($i = 1; $i <= 12; $i++) {
    $expected = fmod($ascmc_e[0] + ($i - 1) * 30.0, 360.0);
    $diff = abs($cusp_e[$i] - $expected);
    if ($diff > 180) $diff = 360 - $diff;

    echo "  Cusp $i: " . number_format($cusp_e[$i], 4) . "° ";
    echo "(expected: " . number_format($expected, 4) . "°, diff: " . number_format($diff, 6) . "°)\n";
}

// All cusps should be Asc + n*30°
$all_equal = true;
for ($i = 1; $i <= 12; $i++) {
    $expected = fmod($ascmc_e[0] + ($i - 1) * 30.0, 360.0);
    $diff = abs($cusp_e[$i] - $expected);
    if ($diff > 180) $diff = 360 - $diff;
    if ($diff > 0.01) {
        $all_equal = false;
        break;
    }
}

if ($all_equal) {
    echo "  ✓ Test 4 PASSED (all cusps = Asc + n*30°)\n\n";
} else {
    echo "  ✗ Test 4 FAILED\n\n";
}

echo "======================================================================\n";
echo "ALL TESTS COMPLETED\n";
echo "======================================================================\n";
