<?php

/**
 * Rise/Set Accuracy Test - Compare with swetest64.exe reference values
 *
 * Test date: 2025-01-01 12:00 UT (JD 2460677.0)
 * Location: Berlin (13.4°E, 52.5°N, 0m)
 *
 * NOTE: Julian Day starts at NOON (12:00 UT), not midnight!
 * JD 2460677.0 = 2025-01-01 12:00 UT
 * JD 2460676.5 = 2025-01-01 00:00 UT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);
echo "Ephemeris path: $ephePath\n\n";

echo "=== Rise/Set Accuracy Test ===\n";
echo "Comparing PHP implementation with swetest64.exe reference values\n\n";

$jd_ut = 2460677.0;  // 2025-01-01 12:00 UT (CORRECTED!)
$geopos = [13.4, 52.5, 0.0];  // Berlin
$atpress = 1013.25;  // mbar
$attemp = 15.0;      // °C

// Verify JD is correct
$date_check = swe_revjul($jd_ut, Constants::SE_GREG_CAL);
printf("Test date: JD %.1f = %04d-%02d-%02d %02d:%02d UT\n",
    $jd_ut, $date_check['y'], $date_check['m'], $date_check['d'],
    (int)$date_check['ut'], (int)(($date_check['ut'] - (int)$date_check['ut']) * 60));
echo "Location: Berlin (lon={$geopos[0]}°, lat={$geopos[1]}°, alt={$geopos[2]}m)\n";
echo "Atmospheric: {$atpress}mbar, {$attemp}°C\n\n";

// Helper to convert JD to readable time
function jd_to_time(float $jd): string {
    $date = swe_revjul($jd, Constants::SE_GREG_CAL);
    $year = $date['y'];
    $month = $date['m'];
    $day = $date['d'];
    $hour = $date['ut'];
    $h = (int)$hour;
    $m = (int)(($hour - $h) * 60);
    $s = (($hour - $h) * 60 - $m) * 60;
    return sprintf("%02d.%02d.%04d %02d:%02d:%05.2f", $day, $month, $year, $h, $m, $s);
}

// Test 1: Moon rise/set
echo "--- Test 1: Moon (planet 1) ---\n";
$trise = 0.0; $tset = 0.0; $serr = null;

// Rise
$ret_rise = swe_rise_trans($jd_ut, Constants::SE_MOON, null,
    Constants::SEFLG_SWIEPH, Constants::SE_CALC_RISE,
    $geopos, $atpress, $attemp, null, $trise, $serr);

// Set
$ret_set = swe_rise_trans($jd_ut, Constants::SE_MOON, null,
    Constants::SEFLG_SWIEPH, Constants::SE_CALC_SET,
    $geopos, $atpress, $attemp, null, $tset, $serr);

echo "PHP Implementation:\n";
if ($ret_rise >= 0) {
    echo "  Rise: " . jd_to_time($trise) . " (JD $trise)\n";
} else {
    echo "  Rise: NOT FOUND (ret=$ret_rise)\n";
}

if ($ret_set >= 0) {
    echo "  Set:  " . jd_to_time($tset) . " (JD $tset)\n";
} else {
    echo "  Set:  NOT FOUND (ret=$ret_set)\n";
}

echo "\nReference (swetest64.exe):\n";
echo "  Rise: NOT FOUND\n";
echo "  Set:  01.01.2025 16:26:27.6\n";

// Convert reference to JD for comparison (01.01.2025 00:00 = JD 2460675.5)
$ref_set_jd = 2460675.5 + (16.0 + 26.0/60.0 + 27.6/3600.0) / 24.0;
echo "\nComparison:\n";
if ($ret_set >= 0) {
    $diff_sec = ($tset - $ref_set_jd) * 86400.0;
    echo "  Set time diff: " . sprintf("%+.2f", $diff_sec) . " seconds\n";
    if (abs($diff_sec) < 60.0) {
        echo "  ✅ PASS: Difference < 60 seconds\n";
    } else {
        echo "  ❌ FAIL: Difference > 60 seconds\n";
    }
}

// Test 2: Mars rise/set
echo "\n--- Test 2: Mars (planet 4) ---\n";
$trise = 0.0; $tset = 0.0; $serr = null;

// Rise
$ret_rise = swe_rise_trans($jd_ut, Constants::SE_MARS, null,
    Constants::SEFLG_SWIEPH, Constants::SE_CALC_RISE,
    $geopos, $atpress, $attemp, null, $trise, $serr);

// Set
$ret_set = swe_rise_trans($jd_ut, Constants::SE_MARS, null,
    Constants::SEFLG_SWIEPH, Constants::SE_CALC_SET,
    $geopos, $atpress, $attemp, null, $tset, $serr);echo "PHP Implementation:\n";
if ($ret_rise >= 0) {
    echo "  Rise: " . jd_to_time($trise) . " (JD $trise)\n";
} else {
    echo "  Rise: NOT FOUND (ret=$ret_rise)\n";
}

if ($ret_set >= 0) {
    echo "  Set:  " . jd_to_time($tset) . " (JD $tset)\n";
} else {
    echo "  Set:  NOT FOUND (ret=$ret_set)\n";
}

echo "\nReference (swetest64.exe):\n";
echo "  Rise: 01.01.2025 16:15:49.4\n";
echo "  Set:  02.01.2025 09:00:27.2\n";

// Convert references to JD (01.01.2025 00:00 = JD 2460675.5, 02.01.2025 00:00 = JD 2460676.5)
$ref_rise_jd = 2460675.5 + (16.0 + 15.0/60.0 + 49.4/3600.0) / 24.0;
$ref_set_jd = 2460676.5 + (9.0 + 0.0/60.0 + 27.2/3600.0) / 24.0;

echo "\nComparison:\n";
if ($ret_rise >= 0) {
    $diff_sec = ($trise - $ref_rise_jd) * 86400.0;
    echo "  Rise time diff: " . sprintf("%+.2f", $diff_sec) . " seconds\n";
    if (abs($diff_sec) < 60.0) {
        echo "  ✅ PASS: Rise difference < 60 seconds\n";
    } else {
        echo "  ❌ FAIL: Rise difference > 60 seconds\n";
    }
}

if ($ret_set >= 0) {
    $diff_sec = ($tset - $ref_set_jd) * 86400.0;
    echo "  Set time diff: " . sprintf("%+.2f", $diff_sec) . " seconds\n";
    if (abs($diff_sec) < 60.0) {
        echo "  ✅ PASS: Set difference < 60 seconds\n";
    } else {
        echo "  ❌ FAIL: Set difference > 60 seconds\n";
    }
}

// Test 3: Sun rise/set
echo "\n--- Test 3: Sun (planet 0) ---\n";
$trise = 0.0; $tset = 0.0; $serr = null;

// Rise
$ret_rise = swe_rise_trans($jd_ut, Constants::SE_SUN, null,
    Constants::SEFLG_SWIEPH, Constants::SE_CALC_RISE,
    $geopos, $atpress, $attemp, null, $trise, $serr);

// Set
$ret_set = swe_rise_trans($jd_ut, Constants::SE_SUN, null,
    Constants::SEFLG_SWIEPH, Constants::SE_CALC_SET,
    $geopos, $atpress, $attemp, null, $tset, $serr);

echo "PHP Implementation:\n";
if ($ret_rise >= 0) {
    echo "  Rise: " . jd_to_time($trise) . " (JD $trise)\n";
} else {
    echo "  Rise: NOT FOUND (ret=$ret_rise)\n";
}

if ($ret_set >= 0) {
    echo "  Set:  " . jd_to_time($tset) . " (JD $tset)\n";
} else {
    echo "  Set:  NOT FOUND (ret=$ret_set)\n";
}

echo "\n=== Test completed ===\n";
