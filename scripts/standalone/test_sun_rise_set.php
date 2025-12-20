<?php

declare(strict_types=1);

/**
 * Sun Rise/Set Test
 *
 * Validates rise/set calculations against swetest64.exe reference:
 * Location: Berlin (13.41°E, 52.52°N, 0m)
 * Date: 2025-01-01
 * Body: Sun
 *
 * Reference from swetest64:
 * swetest64.exe -b1.1.2025 -ut00:00 -p0 -geopos13.41,52.52,0 -roundsec -eswe -edir.\eph\ephe -rise
 * rise  1.01.2025 07:17:10.2
 * set   1.01.2025 15:03:04.5
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

echo "=== Sun Rise/Set Test ===\n";
echo "Location: Berlin (13.41°E, 52.52°N, 0m)\n";
echo "Date: 2025-01-01 00:00:00 UT\n";
echo "Body: Sun\n";
echo "JD: " . number_format($jd_ut, 6) . "\n\n";

// Ephemeris flags
$epheflag = Constants::SEFLG_SWIEPH;
$atpress = 1013.25;
$attemp = 15.0;

// Test 1: Sunrise (SE_CALC_RISE)
echo "Test 1: Sunrise\n";
echo str_repeat('-', 50) . "\n";

$tret = 0.0;
$serr = null;

$rsmi = Constants::SE_CALC_RISE;
$rc = swe_rise_trans(
    $jd_ut,
    Constants::SE_SUN,
    null,
    $epheflag,
    $rsmi,
    $geopos,
    $atpress,
    $attemp,
    0.0,  // horhgt (default for Sun)
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
printf("Reference: 01.01.2025 07:17:10.2 UT\n");
printf("JD Result: %.6f\n", $tret);

// Expected: 2025-01-01 07:17:10.2 UT
$jd_expected_rise = swe_julday(2025, 1, 1, 7 + 17/60 + 10.2/3600, Constants::SE_GREG_CAL);
$diff_rise_sec = ($tret - $jd_expected_rise) * 86400;

printf("Difference: %.2f seconds\n", $diff_rise_sec);

if (abs($diff_rise_sec) > 5.0) {
    echo "FAIL: Difference exceeds 5 seconds\n";
    $test1_pass = false;
} else {
    echo "PASS: Within 5 seconds of reference\n";
    $test1_pass = true;
}

echo "\n";

// Test 2: Sunset (SE_CALC_SET)
echo "Test 2: Sunset\n";
echo str_repeat('-', 50) . "\n";

$tret = 0.0;
$serr = null;

$rsmi = Constants::SE_CALC_SET;
$rc = swe_rise_trans(
    $jd_ut,
    Constants::SE_SUN,
    null,
    $epheflag,
    $rsmi,
    $geopos,
    $atpress,
    $attemp,
    0.0,  // horhgt (default for Sun)
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
printf("Reference: 01.01.2025 15:03:04.5 UT\n");
printf("JD Result: %.6f\n", $tret);

// Expected: 2025-01-01 15:03:04.5 UT
$jd_expected_set = swe_julday(2025, 1, 1, 15 + 3/60 + 4.5/3600, Constants::SE_GREG_CAL);
$diff_set_sec = ($tret - $jd_expected_set) * 86400;

printf("Difference: %.2f seconds\n", $diff_set_sec);

if (abs($diff_set_sec) > 5.0) {
    echo "FAIL: Difference exceeds 5 seconds\n";
    $test2_pass = false;
} else {
    echo "PASS: Within 5 seconds of reference\n";
    $test2_pass = true;
}

echo "\n";

// Summary
echo "=== Summary ===\n";
echo "Sunrise: " . ($test1_pass ? "PASS" : "FAIL") . "\n";
echo "Sunset:  " . ($test2_pass ? "PASS" : "FAIL") . "\n";

if ($test1_pass && $test2_pass) {
    echo "\n✓ ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED\n";
    exit(1);
}
