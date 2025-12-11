<?php

declare(strict_types=1);

/**
 * Lunar Eclipse When Test
 *
 * Tests swe_lun_eclipse_when() - search for next lunar eclipse globally.
 * WITHOUT SIMPLIFICATIONS - validates full algorithm against known eclipses.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== Lunar Eclipse When Test ===\n";
echo "Testing swe_lun_eclipse_when() - find next lunar eclipse\n\n";

// Test 1: Find the partial lunar eclipse of 2024-09-18
// Start search from 2024-01-01
echo "=== Test 1: Search forward from 2024-01-01 ===\n";
$tjd_start = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$tret = [];
$serr = '';

echo "Start JD: $tjd_start (2024-01-01)\n";
echo "Searching for partial or total lunar eclipse (skip penumbral)...\n\n";

$retflag = swe_lun_eclipse_when(
    $tjd_start,
    Constants::SEFLG_SWIEPH,
    Constants::SE_ECL_PARTIAL | Constants::SE_ECL_TOTAL,  // Skip penumbral
    $tret,
    0,  // forward
    $serr
);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

if ($retflag === 0) {
    echo "FAIL: No eclipse found\n";
    exit(1);
}

echo "Eclipse found!\n";
echo "Eclipse Type: ";
if ($retflag & Constants::SE_ECL_TOTAL) {
    echo "TOTAL ";
}
if ($retflag & Constants::SE_ECL_PARTIAL) {
    echo "PARTIAL ";
}
if ($retflag & Constants::SE_ECL_PENUMBRAL) {
    echo "PENUMBRAL ";
}
echo "\n\n";

echo "=== Eclipse Times ===\n";
printf("Maximum:         JD %.6f (UT)\n", $tret[0]);

// Convert to calendar date
$date = swe_revjul($tret[0], Constants::SE_GREG_CAL);
$year = $date['y'];
$month = $date['m'];
$day = $date['d'];
$ut = $date['ut'];
$hour = floor($ut);
$min = floor(($ut - $hour) * 60);
$sec = (($ut - $hour) * 60 - $min) * 60;
printf("                 %04d-%02d-%02d %02d:%02d:%02.0f UT\n", $year, $month, $day, $hour, $min, $sec);

if ($tret[6] > 0) {
    printf("Penumbral begin: JD %.6f\n", $tret[6]);
    $date = swe_revjul($tret[6], Constants::SE_GREG_CAL);
    $year = $date['y'];
    $month = $date['m'];
    $day = $date['d'];
    $ut = $date['ut'];
    $hour = floor($ut);
    $min = floor(($ut - $hour) * 60);
    $sec = (($ut - $hour) * 60 - $min) * 60;
    printf("                 %04d-%02d-%02d %02d:%02d:%02.0f UT\n", $year, $month, $day, $hour, $min, $sec);
}

if ($tret[2] > 0) {
    printf("Partial begin:   JD %.6f\n", $tret[2]);
    $date = swe_revjul($tret[2], Constants::SE_GREG_CAL);
    $year = $date['y'];
    $month = $date['m'];
    $day = $date['d'];
    $ut = $date['ut'];
    $hour = floor($ut);
    $min = floor(($ut - $hour) * 60);
    $sec = (($ut - $hour) * 60 - $min) * 60;
    printf("                 %04d-%02d-%02d %02d:%02d:%02.0f UT\n", $year, $month, $day, $hour, $min, $sec);
}

if ($tret[4] > 0) {
    printf("Totality begin:  JD %.6f\n", $tret[4]);
}

if ($tret[5] > 0) {
    printf("Totality end:    JD %.6f\n", $tret[5]);
}

if ($tret[3] > 0) {
    printf("Partial end:     JD %.6f\n", $tret[3]);
    $date = swe_revjul($tret[3], Constants::SE_GREG_CAL);
    $year = $date['y'];
    $month = $date['m'];
    $day = $date['d'];
    $ut = $date['ut'];
    $hour = floor($ut);
    $min = floor(($ut - $hour) * 60);
    $sec = (($ut - $hour) * 60 - $min) * 60;
    printf("                 %04d-%02d-%02d %02d:%02d:%02.0f UT\n", $year, $month, $day, $hour, $min, $sec);
}

if ($tret[7] > 0) {
    printf("Penumbral end:   JD %.6f\n", $tret[7]);
    $date = swe_revjul($tret[7], Constants::SE_GREG_CAL);
    $year = $date['y'];
    $month = $date['m'];
    $day = $date['d'];
    $ut = $date['ut'];
    $hour = floor($ut);
    $min = floor(($ut - $hour) * 60);
    $sec = (($ut - $hour) * 60 - $min) * 60;
    printf("                 %04d-%02d-%02d %02d:%02d:%02.0f UT\n", $year, $month, $day, $hour, $min, $sec);
}

echo "\n=== Validation ===\n";
$success = true;

// Expected: 2024-09-18 partial lunar eclipse
$date = swe_revjul($tret[0], Constants::SE_GREG_CAL);
$year = $date['y'];
$month = $date['m'];
$day = $date['d'];
$ut = $date['ut'];
if ($year !== 2024 || $month !== 9 || $day !== 18) {
    echo sprintf("FAIL: Expected 2024-09-18, got %04d-%02d-%02d\n", $year, $month, $day);
    $success = false;
}

// Expected: partial eclipse
if (!($retflag & Constants::SE_ECL_PARTIAL)) {
    echo "FAIL: Expected PARTIAL eclipse flag\n";
    $success = false;
}

// Maximum should be around 02:44 UT (±10 min tolerance)
$expected_ut = 2.7369444444444;  // 02:44:13
if (abs($ut - $expected_ut) > 0.007) {  // ~10 minutes
    echo sprintf("FAIL: Expected maximum at ~02:44 UT, got %.2f UT\n", $ut);
    $success = false;
}

if ($success) {
    echo "✓ All validations PASSED\n";
} else {
    echo "✗ Some validations FAILED\n";
    exit(1);
}

// Test 2: Search backward from 2024-12-31
echo "\n=== Test 2: Search backward from 2024-12-31 ===\n";
$tjd_start2 = swe_julday(2024, 12, 31, 0.0, Constants::SE_GREG_CAL);
$tret2 = [];
$serr2 = '';

echo "Start JD: $tjd_start2 (2024-12-31)\n";
echo "Searching backward for partial or total lunar eclipse...\n\n";

$retflag2 = swe_lun_eclipse_when(
    $tjd_start2,
    Constants::SEFLG_SWIEPH,
    Constants::SE_ECL_PARTIAL | Constants::SE_ECL_TOTAL,  // Skip penumbral
    $tret2,
    1,  // backward
    $serr2
);

if ($retflag2 === Constants::SE_ERR) {
    echo "ERROR: $serr2\n";
    exit(1);
}

$date = swe_revjul($tret2[0], Constants::SE_GREG_CAL);
$year = $date['y'];
$month = $date['m'];
$day = $date['d'];
printf("Found eclipse: %04d-%02d-%02d\n", $year, $month, $day);

// Should also find 2024-09-18
if ($year !== 2024 || $month !== 9 || $day !== 18) {
    echo sprintf("FAIL: Expected 2024-09-18, got %04d-%02d-%02d\n", $year, $month, $day);
    exit(1);
}

echo "✓ Backward search PASSED\n";

// Test 3: Search for specific type (total eclipse only)
echo "\n=== Test 3: Search for TOTAL eclipse only ===\n";
$tjd_start3 = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$tret3 = [];
$serr3 = '';

$retflag3 = swe_lun_eclipse_when(
    $tjd_start3,
    Constants::SEFLG_SWIEPH,
    Constants::SE_ECL_TOTAL,
    $tret3,
    0,
    $serr3
);

if ($retflag3 === Constants::SE_ERR) {
    echo "ERROR: $serr3\n";
    exit(1);
}

$date = swe_revjul($tret3[0], Constants::SE_GREG_CAL);
$year = $date['y'];
$month = $date['m'];
$day = $date['d'];
printf("Found total eclipse: %04d-%02d-%02d\n", $year, $month, $day);

if (!($retflag3 & Constants::SE_ECL_TOTAL)) {
    echo "FAIL: Expected TOTAL eclipse flag\n";
    exit(1);
}

// Should skip 2024-09-18 (partial) and find next total
if ($year === 2024 && $month === 9 && $day === 18) {
    echo "FAIL: Should have skipped partial eclipse 2024-09-18\n";
    exit(1);
}

echo "✓ Total eclipse search PASSED (skipped partial)\n";

echo "\n✓ ALL TESTS PASSED: Lunar eclipse when search successful\n";
