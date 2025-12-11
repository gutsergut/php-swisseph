<?php
/**
 * Test suite for miscellaneous utility functions
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Miscellaneous Utility Functions Test ===\n\n";

// Test 1: swe_d2l - double to long with rounding
echo "--- Test 1: swe_d2l (double to int32 with rounding) ---\n";
$tests = [
    [0.0, 0],
    [0.4, 0],
    [0.5, 1],
    [0.6, 1],
    [1.5, 2],
    [123.49, 123],
    [123.50, 124],
    [-0.4, 0],
    [-0.5, -1],
    [-0.6, -1],
    [-1.5, -2],
    [-123.49, -123],
    [-123.50, -124],
];

foreach ($tests as [$input, $expected]) {
    $result = swe_d2l($input);
    $ok = ($result === $expected) ? '✓' : '✗';
    echo sprintf("  %s %.2f → %d (expected %d)\n", $ok, $input, $result, $expected);
}
echo "\n";

// Test 2: swe_day_of_week - get day of week from JD
echo "--- Test 2: swe_day_of_week (0=Mon, 6=Sun) ---\n";

// Known dates with days of week:
// 2000-01-01 (Saturday) = JD 2451544.5
// 2000-01-03 (Monday) = JD 2451546.5
// 2023-01-01 (Sunday) = JD 2459945.5
// 2023-01-02 (Monday) = JD 2459946.5
$tests = [
    [2451544.5, 5, '2000-01-01 (Saturday)'],      // Saturday = 5
    [2451545.5, 6, '2000-01-02 (Sunday)'],        // Sunday = 6
    [2451546.5, 0, '2000-01-03 (Monday)'],        // Monday = 0
    [2451547.5, 1, '2000-01-04 (Tuesday)'],       // Tuesday = 1
    [2459945.5, 6, '2023-01-01 (Sunday)'],        // Sunday = 6
    [2459946.5, 0, '2023-01-02 (Monday)'],        // Monday = 0
    [2459952.5, 6, '2023-01-08 (Sunday)'],        // Sunday = 6
];

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

foreach ($tests as [$jd, $expected, $desc]) {
    $result = swe_day_of_week($jd);
    $ok = ($result === $expected) ? '✓' : '✗';
    echo sprintf("  %s JD %.1f (%s) → %d (%s, expected %d=%s)\n",
        $ok, $jd, $desc, $result, $days[$result], $expected, $days[$expected]);
}
echo "\n";

// Test 3: swe_date_conversion - validate dates
echo "--- Test 3: swe_date_conversion (date validation) ---\n";

$tests = [
    // [year, month, day, time, calendar, expected_result, description]
    [2023, 1, 1, 12.0, 'g', 0, 'Valid: 2023-01-01 12:00 (Gregorian)'],
    [2000, 2, 29, 0.0, 'g', 0, 'Valid: 2000-02-29 (leap year)'],
    [1900, 2, 29, 0.0, 'g', -1, 'Invalid: 1900-02-29 (not leap year in Gregorian)'],
    [2023, 2, 30, 12.0, 'g', -1, 'Invalid: 2023-02-30 (no such date)'],
    [2023, 13, 1, 0.0, 'g', -1, 'Invalid: month 13'],
    [2023, 12, 32, 0.0, 'g', -1, 'Invalid: day 32'],
    [1582, 10, 15, 0.0, 'g', 0, 'Valid: 1582-10-15 (Gregorian calendar start)'],
    [1582, 10, 4, 0.0, 'j', 0, 'Valid: 1582-10-04 (Julian)'],
    [-4712, 1, 1, 12.0, 'j', 0, 'Valid: -4712-01-01 12:00 (JD epoch)'],
];

foreach ($tests as [$year, $month, $day, $time, $cal, $expected_result, $desc]) {
    $tjd = 0.0;
    $result = swe_date_conversion($year, $month, $day, $time, $cal, $tjd);
    $ok = ($result === $expected_result) ? '✓' : '✗';

    echo sprintf("  %s %s → Result: %d, JD: %.5f\n",
        $ok, $desc, $result, $tjd);
}
echo "\n";

// Test 4: Cross-validation with swe_julday
echo "--- Test 4: Cross-validation (swe_date_conversion vs swe_julday) ---\n";

$tests = [
    [2023, 6, 15, 18.5, 'g'],
    [2000, 1, 1, 0.0, 'g'],
    [1900, 12, 31, 23.99, 'g'],
];

foreach ($tests as [$year, $month, $day, $time, $cal]) {
    $tjd = 0.0;
    $gregflag = ($cal === 'g') ? 1 : 0;

    // Method 1: swe_date_conversion
    swe_date_conversion($year, $month, $day, $time, $cal, $tjd);

    // Method 2: swe_julday
    $jd_direct = swe_julday($year, $month, $day, $time, $gregflag);

    $diff = abs($tjd - $jd_direct);
    $ok = ($diff < 0.000001) ? '✓' : '✗';

    echo sprintf("  %s %04d-%02d-%02d %.2fh: date_conversion=%.6f, julday=%.6f, diff=%.9f\n",
        $ok, $year, $month, $day, $time, $tjd, $jd_direct, $diff);
}
echo "\n";

// Test 5: Tidal acceleration get/set
echo "--- Test 5: swe_get_tid_acc / swe_set_tid_acc ---\n";

// Get default value
$default_tid = swe_get_tid_acc();
echo sprintf("  ✓ Default tidal acceleration: %.2f arcsec/cy^2\n", $default_tid);

// Set custom value
swe_set_tid_acc(-23.8946); // DE200
$custom_tid = swe_get_tid_acc();
$ok = (abs($custom_tid - (-23.8946)) < 0.0001) ? '✓' : '✗';
echo sprintf("  %s Set to -23.8946 (DE200): %.4f\n", $ok, $custom_tid);

// Set another value
swe_set_tid_acc(-25.826); // DE405
$custom_tid = swe_get_tid_acc();
$ok = (abs($custom_tid - (-25.826)) < 0.0001) ? '✓' : '✗';
echo sprintf("  %s Set to -25.826 (DE405): %.4f\n", $ok, $custom_tid);

// Reset to automatic (default)
swe_set_tid_acc(999999.0); // SE_TIDAL_AUTOMATIC
$reset_tid = swe_get_tid_acc();
$ok = (abs($reset_tid - (-25.80)) < 0.01) ? '✓' : '✗';
echo sprintf("  %s Reset to automatic: %.2f (DE431 default)\n", $ok, $reset_tid);
echo "\n";

// Test 6: Delta-T user-defined
echo "--- Test 6: swe_set_delta_t_userdef ---\n";

// Set user-defined Delta-T
swe_set_delta_t_userdef(0.001); // ~86.4 seconds
echo "  ✓ Set user-defined Delta-T to 0.001 days (~86.4 sec)\n";

// Note: We can't test the effect directly without recalculating Delta-T
// but we can verify the function accepts values without errors

// Reset to automatic
swe_set_delta_t_userdef(-1e-10); // SE_DELTAT_AUTOMATIC
echo "  ✓ Reset Delta-T to automatic calculation\n";
echo "\n";

echo "=== All misc utility tests completed ===\n";
