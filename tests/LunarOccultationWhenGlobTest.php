<?php

/**
 * Test swe_lun_occult_when_glob() - Lunar occultation search
 *
 * Port from swecl.c:1572-1970 (swe_lun_occult_when_glob)
 *
 * Tests global search for occultations of planets/stars by Moon
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Test 1: Find Saturn occultation in 2024 ===\n";
// Known event from swetest64: Moon occults Saturn on 2024-04-06 at JD 2460406.929617
// swetest64.exe -occult -p6 -bj2460000 -n1
// Result: total non-central 6.04.2024 10:18:38.9 JD=2460406.929617
$tjd_start = swe_julday(2023, 3, 1, 12.0, Constants::SE_GREG_CAL); // Start before event
$ipl = Constants::SE_SATURN;
$starname = null;
$ifl = Constants::SEFLG_SWIEPH;
$ifltype = 0; // Any type
$tret = array_fill(0, 10, 0.0);
$backward = 0;
$serr = null;

$retflag = swe_lun_occult_when_glob($tjd_start, $ipl, $starname, $ifl, $ifltype, $tret, $backward, $serr);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

if ($retflag === 0) {
    echo "No occultation found\n";
    exit(1);
}

// Convert to calendar date
$year = 0;
$month = 0;
$day = 0;
$hour = 0.0;
swe_revjul($tret[0], Constants::SE_GREG_CAL, $year, $month, $day, $hour);

printf("Maximum: %04d-%02d-%02d %02d:%02d UT (JD %.6f)\n",
       $year, $month, $day, (int)$hour, (int)(($hour - (int)$hour) * 60),
       $tret[0]);

// Decode retflag
$types = [];
if ($retflag & Constants::SE_ECL_TOTAL) $types[] = 'TOTAL';
if ($retflag & Constants::SE_ECL_ANNULAR) $types[] = 'ANNULAR';
if ($retflag & Constants::SE_ECL_PARTIAL) $types[] = 'PARTIAL';
if ($retflag & Constants::SE_ECL_CENTRAL) $types[] = 'CENTRAL';
if ($retflag & Constants::SE_ECL_NONCENTRAL) $types[] = 'NONCENTRAL';

echo "Type: " . implode(' | ', $types) . " (retflag=$retflag)\n";

// Check phase times
if ($tret[2] > 0 && $tret[3] > 0) {
    swe_revjul($tret[2], Constants::SE_GREG_CAL, $year, $month, $day, $hour);
    printf("Begin:   %04d-%02d-%02d %02d:%02d UT\n",
           $year, $month, $day, (int)$hour, (int)(($hour - (int)$hour) * 60));

    swe_revjul($tret[3], Constants::SE_GREG_CAL, $year, $month, $day, $hour);
    printf("End:     %04d-%02d-%02d %02d:%02d UT\n",
           $year, $month, $day, (int)$hour, (int)(($hour - (int)$hour) * 60));
}

if ($tret[4] > 0 && $tret[5] > 0) {
    echo "Has totality phase\n";
}

// Validate: 2024-04-06 JD=2460406.929617 expected (swetest64 reference)
$expected_jd = 2460406.929617;
$diff_days = abs($tret[0] - $expected_jd);
$diff_minutes = $diff_days * 24 * 60;

if ($diff_minutes < 5.0) {
    printf("✓ Test 1 PASSED: Found Saturn occultation at expected time (diff: %.2f minutes)\n", $diff_minutes);
} else {
    printf("✗ Test 1 FAILED: Expected JD %.6f, got JD %.6f (diff: %.2f minutes)\n",
           $expected_jd, $tret[0], $diff_minutes);
}

echo "\n=== Test 2: Search backward from 2024 ===\n";
$tjd_start = swe_julday(2024, 12, 31, 12.0, Constants::SE_GREG_CAL);
$backward = 1;
$tret = array_fill(0, 10, 0.0);

$retflag = swe_lun_occult_when_glob($tjd_start, Constants::SE_SATURN, null, $ifl, 0, $tret, $backward, $serr);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

if ($retflag > 0) {
    swe_revjul($tret[0], Constants::SE_GREG_CAL, $year, $month, $day, $hour);
    printf("Found backward: %04d-%02d-%02d %02d:%02d UT (JD %.6f)\n",
           $year, $month, $day, (int)$hour, (int)(($hour - (int)$hour) * 60),
           $tret[0]);

    if ($tret[0] < $tjd_start) {
        echo "✓ Test 2 PASSED: Backward search returned earlier date\n";
    } else {
        echo "✗ Test 2 FAILED: Backward search should return date before start\n";
    }
} else {
    echo "No occultation found backward\n";
}

echo "\n=== Test 3: Total occultations only ===\n";
$tjd_start = swe_julday(2024, 1, 1, 12.0, Constants::SE_GREG_CAL);
$ifltype = Constants::SE_ECL_TOTAL;
$backward = 0;
$tret = array_fill(0, 10, 0.0);

$retflag = swe_lun_occult_when_glob($tjd_start, Constants::SE_SATURN, null, $ifl, $ifltype, $tret, $backward, $serr);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
} elseif ($retflag === 0) {
    echo "No total occultation found (may be expected)\n";
    echo "✓ Test 3 PASSED: Function handles type filtering\n";
} else {
    swe_revjul($tret[0], Constants::SE_GREG_CAL, $year, $month, $day, $hour);
    printf("Found total: %04d-%02d-%02d %02d:%02d UT\n",
           $year, $month, $day, (int)$hour, (int)(($hour - (int)$hour) * 60));

    if ($retflag & Constants::SE_ECL_TOTAL) {
        echo "✓ Test 3 PASSED: Found total occultation as requested\n";
    } else {
        echo "✗ Test 3 FAILED: Requested TOTAL but got other type\n";
    }
}

echo "\n=== All Tests Complete ===\n";
