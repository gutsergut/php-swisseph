<?php

declare(strict_types=1);

/**
 * Meridian Transit Test
 *
 * Validates calcMerTrans() implementation against swetest64.exe reference:
 * Location: Berlin (13.41°E, 52.52°N, 0m)
 * Date: 2025-01-01
 * Body: Sun
 *
 * Reference from swetest64:
 * swetest64.exe -b1.1.2025 -ut00:00 -p0 -geopos13.41,52.52,0 -roundsec -eswe -edir.\eph\ephe -metr
 * mtransit  1.01.2025 11:10:01.3
 * itransit  1.01.2025 23:10:15.4
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Berlin coordinates
$lon = 13.41;
$lat = 52.52;
$alt = 0.0;
$geopos = [$lon, $lat, $alt];

// 2025-01-01 00:00:00 UT
$year = 2025;
$month = 1;
$day = 1;
$hour = 0.0;

$jd_ut = swe_julday($year, $month, $day, $hour, Constants::SE_GREG_CAL);

echo "=== Meridian Transit Test ===\n";
echo "Location: Berlin (13.41°E, 52.52°N, 0m)\n";
echo "Date: 2025-01-01 00:00:00 UT\n";
echo "Body: Sun\n";
echo "JD: " . number_format($jd_ut, 6) . "\n\n";

// Ephemeris flags
$epheflag = Constants::SEFLG_SWIEPH;
$atpress = 1013.25;
$attemp = 15.0;

// Test 1: Upper Transit (SE_CALC_MTRANSIT)
echo "Test 1: Upper Transit (Meridian Transit)\n";
echo str_repeat('-', 50) . "\n";

$tret = 0.0;
$serr = null;

$rsmi = Constants::SE_CALC_MTRANSIT;
$rc = swe_rise_trans(
    $jd_ut,
    Constants::SE_SUN,
    null,
    $epheflag,
    $rsmi,
    $geopos,
    $atpress,
    $attemp,
    0.0,  // horhgt (not used for transits)
    $tret,
    $serr
);

if ($rc < 0) {
    echo "ERROR: " . ($serr ?? 'Unknown error') . "\n";
    exit(1);
}

// Convert JD to calendar date
$cal = swe_revjul($tret, Constants::SE_GREG_CAL);

$hr = $cal['ut'];
$mn = ($hr - floor($hr)) * 60;
$sc = ($mn - floor($mn)) * 60;

printf("Result:    %02d.%02d.%04d %02d:%02d:%05.2f UT\n",
    $cal['d'], $cal['m'], $cal['y'], (int)$hr, (int)$mn, $sc);
printf("Reference: 01.01.2025 11:10:01.3 UT\n");
printf("JD Result: %.6f\n", $tret);

// Expected: 2025-01-01 11:10:01.3 UT
$jd_expected_mt = swe_julday(2025, 1, 1, 11 + 10/60 + 1.3/3600, Constants::SE_GREG_CAL);
$diff_mt_sec = ($tret - $jd_expected_mt) * 86400;

printf("Difference: %.2f seconds\n", $diff_mt_sec);

if (abs($diff_mt_sec) > 2.0) {
    echo "FAIL: Difference exceeds 2 seconds\n";
    $test1_pass = false;
} else {
    echo "PASS: Within 2 seconds of reference\n";
    $test1_pass = true;
}

echo "\n";

// Test 2: Lower Transit (SE_CALC_ITRANSIT)
echo "Test 2: Lower Transit (Inferior Transit)\n";
echo str_repeat('-', 50) . "\n";

$tret = 0.0;
$serr = null;

$rsmi = Constants::SE_CALC_ITRANSIT;
$rc = swe_rise_trans(
    $jd_ut,
    Constants::SE_SUN,
    null,
    $epheflag,
    $rsmi,
    $geopos,
    $atpress,
    $attemp,
    0.0,  // horhgt (not used for transits)
    $tret,
    $serr
);

if ($rc < 0) {
    echo "ERROR: " . ($serr ?? 'Unknown error') . "\n";
    exit(1);
}

// Convert JD to calendar date
$cal = swe_revjul($tret, Constants::SE_GREG_CAL);

$hr = $cal['ut'];
$mn = ($hr - floor($hr)) * 60;
$sc = ($mn - floor($mn)) * 60;

printf("Result:    %02d.%02d.%04d %02d:%02d:%05.2f UT\n",
    $cal['d'], $cal['m'], $cal['y'], (int)$hr, (int)$mn, $sc);
printf("Reference: 01.01.2025 23:10:15.4 UT\n");
printf("JD Result: %.6f\n", $tret);

// Expected: 2025-01-01 23:10:15.4 UT
$jd_expected_it = swe_julday(2025, 1, 1, 23 + 10/60 + 15.4/3600, Constants::SE_GREG_CAL);
$diff_it_sec = ($tret - $jd_expected_it) * 86400;

printf("Difference: %.2f seconds\n", $diff_it_sec);

if (abs($diff_it_sec) > 2.0) {
    echo "FAIL: Difference exceeds 2 seconds\n";
    $test2_pass = false;
} else {
    echo "PASS: Within 2 seconds of reference\n";
    $test2_pass = true;
}

echo "\n";

// Summary
echo "=== Summary ===\n";
echo "Upper Transit: " . ($test1_pass ? "PASS" : "FAIL") . "\n";
echo "Lower Transit: " . ($test2_pass ? "PASS" : "FAIL") . "\n";

if ($test1_pass && $test2_pass) {
    echo "\n✓ ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED\n";
    exit(1);
}
