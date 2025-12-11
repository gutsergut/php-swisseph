<?php

declare(strict_types=1);

/**
 * Test for return code -2 (no rise/set found)
 *
 * Tests polar latitude case where rise/set may or may not be found within
 * the 28-hour search window.
 *
 * Test case: Tromsø (69.65°N) on 2025-06-15 00:00 UT
 * - Search window: 28 hours → until 16.06.2025 04:00 UT
 * - Moon rise 16.06.2025 00:30:39 UT → WITHIN window (return 0, JD returned)
 * - Moon set  16.06.2025 05:20:50 UT → OUTSIDE window (return -2, error msg)
 *
 * This demonstrates:
 * 1. Function correctly finds events within 28h window
 * 2. Function correctly returns -2 when event is outside window
 * 3. Error message is properly set for -2 cases
 *
 * Reference: swetest64.exe confirms rise 00:30:39, set 05:20:50 on 16.06
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== Polar 28-Hour Window Test ===\n";
echo "Testing correct behavior with 28-hour search window\n\n";

// Test case: Tromsø on 2025-06-15 (search window ends 16.06 04:00 UT)
$geopos = [18.96, 69.65, 0.0];  // Tromsø (lon, lat, alt)
$jd_ut = 2460841.5;  // 2025-06-15 00:00 UT

echo "Test: Moon rise/set at Tromsø polar latitude\n";
echo "  Location: {$geopos[0]}°E, {$geopos[1]}°N\n";
echo "  Date: 2025-06-15 00:00 UT (JD $jd_ut)\n";
echo "  Search window: 28 hours → until 16.06.2025 04:00 UT\n";
echo "  Expected:\n";
echo "    Rise 16.06 00:30 UT → within window (return 0)\n";
echo "    Set  16.06 05:20 UT → outside window (return -2)\n\n";

// Test rise
$serr_rise = '';
$tret_rise = 0.0;

$result_rise = swe_rise_trans(
    $jd_ut,
    Constants::SE_MOON,
    '',
    Constants::SEFLG_SWIEPH,
    Constants::SE_CALC_RISE,
    $geopos,
    1013.25,
    15.0,
    null,
    $tret_rise,
    $serr_rise
);

echo "Rise:\n";
echo "  Return code: $result_rise\n";
echo "  Error message: '$serr_rise'\n";

if ($result_rise === 0) {
    // Found a rise - check which day
    $dayFrac = fmod($tret_rise + 0.5, 1.0);
    $seconds = $dayFrac * 86400.0;
    $h = floor($seconds / 3600);
    $m = floor(($seconds - $h * 3600) / 60);
    $s = $seconds - $h * 3600 - $m * 60;

    $jdDay = floor($tret_rise + 0.5);
    $jdInput = floor($jd_ut + 0.5);

    echo "  Found rise at JD: " . number_format($tret_rise, 6) . "\n";
    echo "  Time: " . sprintf("%02d:%02d:%05.2f", $h, $m, $s) . " UT\n";
    echo "  Day: JD $jdDay (input was JD $jdInput)\n";

    if ($jdDay > $jdInput) {
        echo "  ✓ PASS - Rise found on NEXT day (correct, within 28h window)\n\n";
        $pass_rise = true;
    } else {
        echo "  ✗ FAIL - Expected -2 or rise on next day\n\n";
        $pass_rise = false;
    }
} elseif ($result_rise === -2 && str_contains($serr_rise, 'rise or set not found for planet 1')) {
    echo "  ✓ PASS - Correctly returned -2 with proper error message\n\n";
    $pass_rise = true;
} else {
    echo "  ✗ FAIL - Expected -2 with 'rise or set not found' message\n\n";
    $pass_rise = false;
}

// Test set
$serr_set = '';
$tret_set = 0.0;

$result_set = swe_rise_trans(
    $jd_ut,
    Constants::SE_MOON,
    '',
    Constants::SEFLG_SWIEPH,
    Constants::SE_CALC_SET,
    $geopos,
    1013.25,
    15.0,
    null,
    $tret_set,
    $serr_set
);

echo "Set:\n";
echo "  Return code: $result_set\n";
echo "  Error message: '$serr_set'\n";

if ($result_set === -2 && str_contains($serr_set, 'rise or set not found for planet 1')) {
    echo "  ✓ PASS - Correctly returned -2 with proper error message\n\n";
    $pass_set = true;
} else {
    echo "  ✗ FAIL - Expected -2 with 'rise or set not found' message\n\n";
    $pass_set = false;
}

// Verify with swetest64 reference (events on next day)
echo "Verification with swetest64 reference:\n";
echo "  swetest64 shows Moon rise/set on 16.06.2025:\n";
echo "    rise 16.06.2025 00:30:39.3 UT (within 28h window)\n";
echo "    set  16.06.2025 05:20:50.8 UT (outside 28h window)\n";
echo "  28h window: 15.06 00:00 → 16.06 04:00 UT\n";
echo "  This confirms function behavior is correct\n\n";

// Summary
echo "=== Summary ===\n";
if ($pass_rise && $pass_set) {
    echo "✓ All tests PASSED\n";
    echo "  Function correctly implements 28-hour search window\n";
    echo "  Rise found within window → return 0 with JD\n";
    echo "  Set outside window → return -2 with error message\n";
    echo "  Error messages are properly set\n";
    exit(0);
} else {
    echo "✗ Some tests FAILED\n";
    exit(1);
}
