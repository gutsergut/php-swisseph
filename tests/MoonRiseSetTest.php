<?php

declare(strict_types=1);

/**
 * Moon Rise/Set comprehensive test
 *
 * Tests Moon rise/set calculations for different latitudes:
 * - Berlin (52.52°N) - mid-latitude
 * - Singapore (1.29°N) - equatorial
 * - Tromsø (69.65°N) - polar
 *
 * Reference data from swetest64.exe
 * Tolerance: ±2 seconds
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');/**
 * Parse time string "HH:MM:SS.S" to JD fraction
 */
function parseTime(string $time): float {
    $parts = explode(':', $time);
    $hours = (float)$parts[0];
    $minutes = (float)$parts[1];
    $seconds = (float)$parts[2];
    return ($hours + $minutes / 60.0 + $seconds / 3600.0) / 24.0;
}

/**
 * Convert JD to time string "HH:MM:SS"
 */
function jdToTime(float $jd): string {
    $frac = $jd - floor($jd);
    $seconds = $frac * 86400.0;
    $hours = floor($seconds / 3600.0);
    $minutes = floor(($seconds - $hours * 3600.0) / 60.0);
    $secs = $seconds - $hours * 3600.0 - $minutes * 60.0;
    return sprintf("%02d:%02d:%05.2f", $hours, $minutes, $secs);
}

/**
 * Test configuration
 */
$tests = [
    'Berlin mid-latitude' => [
        'geopos' => [13.41, 52.52, 0.0],
        'date' => '2025-01-01',
        'jd_base' => 2460676.5, // JD for 2025-01-01 00:00 UT
        'reference' => [
            'rise' => '08:58:14.8',
            'set' => '16:26:17.8',
        ],
    ],
    'Singapore equatorial' => [
        'geopos' => [103.85, 1.29, 0.0],
        'date' => '2025-01-01',
        'jd_base' => 2460676.5,
        'reference' => [
            'rise' => '00:11:15.2',
            'set' => '12:32:54.4',
        ],
    ],
    'Tromsø polar (summer)' => [
        'geopos' => [18.96, 69.65, 0.0],
        'date' => '2025-06-16',
        'jd_base' => 2460842.5, // JD for 2025-06-16 00:00 UT (event day)
        'reference' => [
            'rise' => '00:30:39.3',  // 16.06.2025 00:30:39
            'set' => '05:20:50.8',   // 16.06.2025 05:20:50
        ],
        'note' => 'Polar latitude - no rise/set on 15.06, events occur on 16.06',
    ],
];

echo "\n=== Moon Rise/Set Comprehensive Test ===\n";
echo "Testing against swetest64.exe reference data\n";
echo "Tolerance: ±2.0 seconds\n\n";

$passCount = 0;
$failCount = 0;
$tolerance = 2.0 / 86400.0; // 2 seconds in days

foreach ($tests as $name => $config) {
    echo "Test: $name\n";
    echo "  Location: {$config['geopos'][0]}°E, {$config['geopos'][1]}°N\n";
    echo "  Date: {$config['date']}\n";

    if (isset($config['note'])) {
        echo "  Note: {$config['note']}\n";
    }

    $geopos = $config['geopos'];
    $jd_start = $config['jd_base'];

    // Calculate rise
    $serr = '';
    $tret_rise = 0.0;

    $result_rise = swe_rise_trans(
        $jd_start,
        Constants::SE_MOON,
        '',
        Constants::SEFLG_SWIEPH,
        Constants::SE_CALC_RISE,  // Rise of upper limb (default, matches swetest64)
        $geopos,
        1013.25, // atmospheric pressure (standard)
        15.0,    // temperature (standard)
        null,    // horhgt (default for Moon)
        $tret_rise,
        $serr
    );

    if ($result_rise < 0) {
        echo "  ERROR (rise): $serr\n";
        $failCount++;
        continue;
    }

    // Calculate set
    $tret_set = 0.0;

    $result_set = swe_rise_trans(
        $jd_start,
        Constants::SE_MOON,
        '',
        Constants::SEFLG_SWIEPH,
        Constants::SE_CALC_SET,  // Set of upper limb (default, matches swetest64)
        $geopos,
        1013.25, // atmospheric pressure (standard)
        15.0,    // temperature (standard)
        null,    // horhgt (default for Moon)
        $tret_set,
        $serr
    );

    if ($result_set < 0) {
        echo "  ERROR (set): $serr\n";
        $failCount++;
        continue;
    }

    // Check rise time
    $rise_expected_jd = $jd_start + parseTime($config['reference']['rise']);
    $rise_php = $tret_rise;

    if ($rise_php > 0) {
        $rise_diff = abs($rise_php - $rise_expected_jd);
        $rise_diff_sec = $rise_diff * 86400.0;
        $rise_status = $rise_diff <= $tolerance ? 'PASS' : 'FAIL';

        echo "  Rise:\n";
        echo "    Reference: {$config['reference']['rise']} UT (JD " . number_format($rise_expected_jd, 6) . ")\n";
        echo "    PHP:       " . jdToTime($rise_php) . " UT (JD " . number_format($rise_php, 6) . ")\n";
        echo "    Difference: " . sprintf("%+.2f", $rise_diff_sec) . " seconds [$rise_status]\n";

        if ($rise_status === 'PASS') {
            $passCount++;
        } else {
            $failCount++;
        }
    } else {
        echo "  Rise: NOT FOUND (expected {$config['reference']['rise']})\n";
        $failCount++;
    }

    // Check set time
    $set_expected_jd = $jd_start + parseTime($config['reference']['set']);
    $set_php = $tret_set;

    if ($set_php > 0) {
        $set_diff = abs($set_php - $set_expected_jd);
        $set_diff_sec = $set_diff * 86400.0;
        $set_status = $set_diff <= $tolerance ? 'PASS' : 'FAIL';

        echo "  Set:\n";
        echo "    Reference: {$config['reference']['set']} UT (JD " . number_format($set_expected_jd, 6) . ")\n";
        echo "    PHP:       " . jdToTime($set_php) . " UT (JD " . number_format($set_php, 6) . ")\n";
        echo "    Difference: " . sprintf("%+.2f", $set_diff_sec) . " seconds [$set_status]\n";

        if ($set_status === 'PASS') {
            $passCount++;
        } else {
            $failCount++;
        }
    } else {
        echo "  Set: NOT FOUND (expected {$config['reference']['set']})\n";
        $failCount++;
    }

    echo "\n";
}

// Summary
echo "=== Summary ===\n";
echo "PASS: $passCount\n";
echo "FAIL: $failCount\n";
echo "Total: " . ($passCount + $failCount) . "\n";

if ($failCount === 0) {
    echo "\n✓ All tests PASSED!\n";
    exit(0);
} else {
    echo "\n✗ Some tests FAILED\n";
    exit(1);
}
