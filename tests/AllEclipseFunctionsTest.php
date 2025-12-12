<?php

/**
 * Comprehensive Eclipse Functions Test
 *
 * Tests all implemented eclipse functions:
 * - swe_sol_eclipse_when_glob() - global solar eclipse search
 * - swe_sol_eclipse_when_loc() - local solar eclipse search
 * - swe_sol_eclipse_how() - solar eclipse attributes at location
 * - swe_lun_eclipse_when() - lunar eclipse search
 * - swe_lun_eclipse_how() - lunar eclipse attributes
 *
 * Reference data: NASA Eclipse Catalog + swetest64.exe
 */

declare(strict_types=1);

use Swisseph\Constants;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n" . str_repeat('=', 80) . "\n";
echo "COMPREHENSIVE ECLIPSE FUNCTIONS TEST\n";
echo str_repeat('=', 80) . "\n\n";

$tests_passed = 0;
$tests_total = 0;

// ============================================================================
// TEST 1: swe_sol_eclipse_when_glob() - Global solar eclipse search
// ============================================================================
echo "TEST 1: swe_sol_eclipse_when_glob() - Global Solar Eclipse Search\n";
echo str_repeat('-', 80) . "\n";
$tests_total++;

$tjd_start = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$tret = array_fill(0, 10, 0.0);
$serr = '';

$retflag = swe_sol_eclipse_when_glob(
    $tjd_start,
    Constants::SEFLG_SWIEPH,
    Constants::SE_ECL_TOTAL, // Search for total eclipses only
    $tret,
    0, // forward
    $serr
);

if ($retflag < 0) {
    echo "  ✗ FAILED: $serr\n\n";
} else {
    $cal = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    echo sprintf(
        "  Found: %04d-%02d-%02d %02d:%02d UT (JD %.5f)\n",
        $cal['y'], $cal['m'], $cal['d'],
        (int)$cal['ut'], (int)(($cal['ut'] - (int)$cal['ut']) * 60),
        $tret[0]
    );

    $is_total = ($retflag & Constants::SE_ECL_TOTAL) !== 0;
    $is_central = ($retflag & Constants::SE_ECL_CENTRAL) !== 0;

    if ($cal['y'] === 2024 && $cal['m'] === 4 && $cal['d'] === 8 && $is_total && $is_central) {
        echo "  ✓ PASSED: Correctly found 2024-04-08 total central eclipse\n\n";
        $tests_passed++;
    } else {
        echo "  ✗ FAILED: Expected 2024-04-08 total central eclipse\n\n";
    }
}

// ============================================================================
// TEST 2: swe_sol_eclipse_when_glob() - Backward search
// ============================================================================
echo "TEST 2: swe_sol_eclipse_when_glob() - Backward Search\n";
echo str_repeat('-', 80) . "\n";
$tests_total++;

$tjd_start = swe_julday(2024, 4, 7, 0.0, Constants::SE_GREG_CAL);
$tret = array_fill(0, 10, 0.0);
$serr = '';

$retflag = swe_sol_eclipse_when_glob(
    $tjd_start,
    Constants::SEFLG_SWIEPH,
    Constants::SE_ECL_ALLTYPES_SOLAR,
    $tret,
    1, // backward
    $serr
);

if ($retflag < 0) {
    echo "  ✗ FAILED: $serr\n\n";
} else {
    $cal = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    echo sprintf(
        "  Found: %04d-%02d-%02d (JD %.5f)\n",
        $cal['y'], $cal['m'], $cal['d'], $tret[0]
    );

    // Should find 2023-10-14 annular eclipse
    if ($cal['y'] === 2023 && $cal['m'] === 10 && $cal['d'] === 14) {
        echo "  ✓ PASSED: Correctly found 2023-10-14 annular eclipse (backward)\n\n";
        $tests_passed++;
    } else {
        echo "  ✗ FAILED: Expected 2023-10-14 eclipse\n\n";
    }
}

// ============================================================================
// TEST 3: swe_sol_eclipse_how() - Eclipse attributes at location
// ============================================================================
echo "TEST 3: swe_sol_eclipse_how() - Solar Eclipse Attributes\n";
echo str_repeat('-', 80) . "\n";
$tests_total++;

// Dallas, TX during 2024-04-08 total eclipse
// Use maximum time: 18:42:37 UT = 18.71 hours
$tjd_ut = swe_julday(2024, 4, 8, 18.71, Constants::SE_GREG_CAL);
$geopos = [-96.8, 32.8, 0.0];
$attr = array_fill(0, 20, 0.0);
$serr = '';

$retflag = swe_sol_eclipse_how(
    $tjd_ut,
    Constants::SEFLG_SWIEPH,
    $geopos,
    $attr,
    $serr
);

if ($retflag < 0) {
    echo "  ✗ FAILED: $serr\n\n";
} else {
    echo sprintf("  Magnitude: %.4f\n", $attr[0]);
    echo sprintf("  Saros series: %d, member: %d\n", (int)$attr[9], (int)$attr[10]);

    $is_total = ($retflag & Constants::SE_ECL_TOTAL) !== 0;
    $magnitude = $attr[0];
    $saros = (int)$attr[9];

    // Expected: magnitude ~1.013, Saros 139
    if ($is_total && $magnitude > 1.0 && $magnitude < 1.02 && $saros === 139) {
        echo "  ✓ PASSED: Eclipse attributes correct (total, magnitude > 1.0, Saros 139)\n\n";
        $tests_passed++;
    } else {
        echo "  ✗ FAILED: Unexpected attributes\n\n";
    }
}

// ============================================================================
// TEST 4: swe_lun_eclipse_when() - Lunar eclipse search
// ============================================================================
echo "TEST 4: swe_lun_eclipse_when() - Lunar Eclipse Search\n";
echo str_repeat('-', 80) . "\n";
$tests_total++;

$tjd_start = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$tret = array_fill(0, 10, 0.0);
$serr = '';

$retflag = swe_lun_eclipse_when(
    $tjd_start,
    Constants::SEFLG_SWIEPH,
    Constants::SE_ECL_ALLTYPES_LUNAR,
    $tret,
    0, // forward
    $serr
);

if ($retflag < 0) {
    echo "  ✗ FAILED: $serr\n\n";
} else {
    $cal = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    echo sprintf(
        "  Found: %04d-%02d-%02d (JD %.5f)\n",
        $cal['y'], $cal['m'], $cal['d'], $tret[0]
    );

    // First lunar eclipse of 2024 is 2024-03-25 penumbral
    if ($cal['y'] === 2024 && $cal['m'] === 3 && $cal['d'] === 25) {
        echo "  ✓ PASSED: Correctly found 2024-03-25 lunar eclipse\n\n";
        $tests_passed++;
    } else {
        echo "  ✗ FAILED: Expected 2024-03-25 eclipse\n\n";
    }
}

// ============================================================================
// TEST 5: swe_lun_eclipse_how() - Lunar eclipse attributes
// ============================================================================
echo "TEST 5: swe_lun_eclipse_how() - Lunar Eclipse Attributes\n";
echo str_repeat('-', 80) . "\n";
$tests_total++;

// 2024-09-18 partial lunar eclipse
// Maximum at 02:44 UT = 2.73 hours
$tjd_ut = swe_julday(2024, 9, 18, 2.73, Constants::SE_GREG_CAL);
$attr = array_fill(0, 20, 0.0);
$serr = '';

$retflag = swe_lun_eclipse_how(
    $tjd_ut,
    Constants::SEFLG_SWIEPH,
    null, // no geopos needed for lunar
    $attr,
    $serr
);

if ($retflag < 0) {
    echo "  ✗ FAILED: $serr\n\n";
} else {
    echo sprintf("  Umbral magnitude: %.4f\n", $attr[0]);
    echo sprintf("  Penumbral magnitude: %.4f\n", $attr[1]);
    echo sprintf("  Saros series: %d, member: %d\n", (int)$attr[9], (int)$attr[10]);

    $is_partial = ($retflag & Constants::SE_ECL_PARTIAL) !== 0;
    $umbral = $attr[0];
    $penumbral = $attr[1];

    // Expected: partial eclipse, umbral ~0.08, penumbral ~1.04
    // Note: at maximum, umbral should be higher
    if ($is_partial && $umbral > 0.04 && $umbral < 0.12 && $penumbral > 1.0) {
        $tests_passed++;
    } else {
        echo "  ✗ FAILED: Unexpected attributes\n\n";
    }
}

// ============================================================================
// TEST 6: swe_sol_eclipse_when_loc() - Local solar eclipse search
// ============================================================================
echo "TEST 6: swe_sol_eclipse_when_loc() - Local Solar Eclipse Search\n";
echo str_repeat('-', 80) . "\n";
$tests_total++;

$tjd_start = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$geopos = [-96.8, 32.8, 0.0]; // Dallas, TX
$tret = array_fill(0, 10, 0.0);
$attr = array_fill(0, 20, 0.0);
$serr = '';

$retflag = swe_sol_eclipse_when_loc(
    $tjd_start,
    Constants::SEFLG_SWIEPH,
    $geopos,
    $tret,
    $attr,
    0, // forward
    $serr
);

if ($retflag < 0) {
    echo "  ✗ FAILED: $serr\n\n";
} else {
    $cal = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    echo sprintf(
        "  Found: %04d-%02d-%02d %02d:%02d UT (maximum)\n",
        $cal['y'], $cal['m'], $cal['d'],
        (int)$cal['ut'], (int)(($cal['ut'] - (int)$cal['ut']) * 60)
    );
    echo sprintf("  Magnitude: %.4f\n", $attr[0]);

    $is_total = ($retflag & Constants::SE_ECL_TOTAL) !== 0;

    // Should find 2024-04-08 total eclipse in Dallas
    if ($cal['y'] === 2024 && $cal['m'] === 4 && $cal['d'] === 8 && $is_total) {
        echo "  ✓ PASSED: Correctly found 2024-04-08 eclipse visible from Dallas\n\n";
        $tests_passed++;
    } else {
        echo "  ✗ FAILED: Expected 2024-04-08 eclipse\n\n";
    }
}

// ============================================================================
// SUMMARY
// ============================================================================
echo str_repeat('=', 80) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 80) . "\n";
echo sprintf("Tests passed: %d / %d (%.1f%%)\n", $tests_passed, $tests_total, ($tests_passed / $tests_total) * 100);

if ($tests_passed === $tests_total) {
    echo "\n✓ ALL TESTS PASSED! All eclipse functions work correctly.\n\n";
    echo "Validated functions:\n";
    echo "  ✓ swe_sol_eclipse_when_glob() - global solar eclipse search (forward/backward)\n";
    echo "  ✓ swe_sol_eclipse_how() - solar eclipse attributes at location\n";
    echo "  ✓ swe_sol_eclipse_when_loc() - local solar eclipse search\n";
    echo "  ✓ swe_lun_eclipse_when() - lunar eclipse search\n";
    echo "  ✓ swe_lun_eclipse_how() - lunar eclipse attributes\n\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED\n\n";
    exit(1);
}

swe_close();
