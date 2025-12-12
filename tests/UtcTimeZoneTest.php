<?php
/**
 * Simple test for swe_utc_time_zone function
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Test 1: Simple timezone conversion (no day rollover)
echo "Test 1: UTC 12:30:45 to MSK (UTC+3)\n";
$y_out = $m_out = $d_out = $h_out = $min_out = 0;
$sec_out = 0.0;
// For UTC→local conversion, use NEGATIVE timezone
swe_utc_time_zone(2025, 12, 13, 12, 30, 45.0, -3.0,
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Input:  2025-12-13 12:30:45 UTC\n");
printf("  Output: %04d-%02d-%02d %02d:%02d:%05.2f (MSK)\n",
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Expected: 2025-12-13 15:30:45\n\n");

// Test 2: Day rollover forward (23:00 + 3 hours = 02:00 next day)
echo "Test 2: UTC 23:00:00 to MSK (UTC+3, crosses midnight)\n";
swe_utc_time_zone(2025, 12, 13, 23, 0, 0.0, -3.0,
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Input:  2025-12-13 23:00:00 UTC\n");
printf("  Output: %04d-%02d-%02d %02d:%02d:%05.2f (MSK)\n",
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Expected: 2025-12-14 02:00:00\n\n");

// Test 3: Day rollover backward (02:00 - 5 hours = 21:00 previous day)
echo "Test 3: UTC 02:00:00 to EST (UTC-5, crosses midnight backward)\n";
swe_utc_time_zone(2025, 12, 13, 2, 0, 0.0, 5.0,
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Input:  2025-12-13 02:00:00 UTC\n");
printf("  Output: %04d-%02d-%02d %02d:%02d:%05.2f (EST)\n",
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Expected: 2025-12-12 21:00:00\n\n");

// Test 4: Month rollover (2025-01-01 01:00:00 - 5 hours = 2024-12-31 20:00:00)
echo "Test 4: UTC 2025-01-01 01:00:00 to EST (UTC-5, crosses year)\n";
swe_utc_time_zone(2025, 1, 1, 1, 0, 0.0, 5.0,
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Input:  2025-01-01 01:00:00 UTC\n");
printf("  Output: %04d-%02d-%02d %02d:%02d:%05.2f (EST)\n",
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Expected: 2024-12-31 20:00:00\n\n");

// Test 5: Leap second handling (60 seconds)
echo "Test 5: Leap second (23:59:60 UTC to MSK)\n";
swe_utc_time_zone(2016, 12, 31, 23, 59, 60.0, -3.0,
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Input:  2016-12-31 23:59:60 UTC\n");
printf("  Output: %04d-%02d-%02d %02d:%02d:%05.2f (MSK)\n",
    $y_out, $m_out, $d_out, $h_out, $min_out, $sec_out);
printf("  Expected: 2017-01-01 02:59:60\n\n");

echo "All tests completed!\n";
