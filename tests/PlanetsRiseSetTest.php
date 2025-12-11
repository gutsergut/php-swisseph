<?php
/**
 * Test planets rise/set times using rise_set_fast() algorithm
 * Reference values from swetest64.exe for Berlin 2025-01-01
 *
 * Fast algorithm should be used for planets Sun-True_Node at lat <60° (Moon) or <65° (Sun)
 * Expected accuracy: ±2 seconds
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Test configuration
$tests = [
    'Venus' => [
        'planet' => Constants::SE_VENUS,  // 3
        'geopos' => [13.41, 52.52, 0.0],  // Berlin (lon, lat, alt)
        'date' => '2025-01-01',
        'jd_base' => 2460676.5,
        'reference' => [
            'rise' => '09:32:53.3',  // swetest -p3
            'set' => '19:16:55.5',
        ],
    ],
    'Mars' => [
        'planet' => Constants::SE_MARS,  // 4
        'geopos' => [13.41, 52.52, 0.0],
        'date' => '2025-01-01',
        'jd_base' => 2460676.5,
        'reference' => [
            'rise' => null,  // No rise on this day (already above horizon)
            'set' => '09:05:00.2',  // swetest -p4
        ],
    ],
    'Jupiter' => [
        'planet' => Constants::SE_JUPITER,  // 5
        'geopos' => [13.41, 52.52, 0.0],
        'date' => '2025-01-01',
        'jd_base' => 2460676.5,
        'reference' => [
            'rise' => null,  // No rise on this day
            'set' => '05:19:41.9',  // swetest -p5
        ],
    ],
    'Saturn' => [
        'planet' => Constants::SE_SATURN,  // 6
        'geopos' => [13.41, 52.52, 0.0],
        'date' => '2025-01-01',
        'jd_base' => 2460676.5,
        'reference' => [
            'rise' => '10:05:13.5',  // swetest -p6
            'set' => '20:47:47.5',
        ],
    ],
];

/**
 * Parse HH:MM:SS.S time string to JD offset
 */
function parseTime(string $time): float {
    list($h, $m, $s) = explode(':', $time);
    return ((int)$h * 3600 + (int)$m * 60 + (float)$s) / 86400.0;
}

/**
 * Format JD to HH:MM:SS.SS UT string
 */
function formatTime(float $jd): string {
    $dayFrac = fmod($jd + 0.5, 1.0);
    $seconds = $dayFrac * 86400.0;
    $h = floor($seconds / 3600);
    $m = floor(($seconds - $h * 3600) / 60);
    $s = $seconds - $h * 3600 - $m * 60;
    return sprintf('%02d:%02d:%05.2f', $h, $m, $s);
}

echo "\n=== Planets Rise/Set Comprehensive Test ===\n";
echo "Testing rise_set_fast() algorithm for planets\n";
echo "Reference: swetest64.exe for Berlin (52.52°N, 13.41°E) 2025-01-01\n";
echo "Tolerance: ±2.0 seconds\n\n";

$passCount = 0;
$failCount = 0;
$tolerance = 2.0 / 86400.0; // 2 seconds in days

foreach ($tests as $name => $config) {
    echo "Test: $name\n";
    echo "  Planet: {$config['planet']}\n";
    echo "  Location: {$config['geopos'][0]}°E, {$config['geopos'][1]}°N\n";
    echo "  Date: {$config['date']}\n";

    $geopos = $config['geopos'];
    $jd_start = $config['jd_base'];
    $planet = $config['planet'];

    // Calculate rise
    if ($config['reference']['rise'] !== null) {
        $serr = '';
        $tret_rise = 0.0;

        $result_rise = swe_rise_trans(
            $jd_start,
            $planet,
            '',
            Constants::SEFLG_SWIEPH,
            Constants::SE_CALC_RISE,  // Rise of upper limb (default)
            $geopos,
            1013.25,
            15.0,
            null,
            $tret_rise,
            $serr
        );

        if ($result_rise < 0) {
            echo "  ERROR (rise): $serr\n";
            $failCount++;
            continue;
        }

        // Check rise time
        $rise_expected_jd = $jd_start + parseTime($config['reference']['rise']);
        $rise_php = $tret_rise;
        $rise_diff = abs($rise_php - $rise_expected_jd);
        $rise_diff_sec = $rise_diff * 86400.0;
        $rise_pass = ($rise_diff <= $tolerance);

        echo "  Rise:\n";
        echo "    Reference: {$config['reference']['rise']} UT (JD " . number_format($rise_expected_jd, 6) . ")\n";
        echo "    PHP:       " . formatTime($rise_php) . " UT (JD " . number_format($rise_php, 6) . ")\n";
        echo "    Difference: " . sprintf("%+.2f", $rise_diff_sec) . " seconds [" . ($rise_pass ? 'PASS' : 'FAIL') . "]\n";

        if ($rise_pass) {
            $passCount++;
        } else {
            $failCount++;
        }
    } else {
        echo "  Rise: Not expected on this day\n";
    }

    // Calculate set
    if ($config['reference']['set'] !== null) {
        $serr = '';
        $tret_set = 0.0;

        $result_set = swe_rise_trans(
            $jd_start,
            $planet,
            '',
            Constants::SEFLG_SWIEPH,
            Constants::SE_CALC_SET,  // Set of upper limb (default)
            $geopos,
            1013.25,
            15.0,
            null,
            $tret_set,
            $serr
        );

        if ($result_set < 0) {
            echo "  ERROR (set): $serr\n";
            $failCount++;
            continue;
        }

        // Check set time
        $set_expected_jd = $jd_start + parseTime($config['reference']['set']);
        $set_php = $tret_set;
        $set_diff = abs($set_php - $set_expected_jd);
        $set_diff_sec = $set_diff * 86400.0;
        $set_pass = ($set_diff <= $tolerance);

        echo "  Set:\n";
        echo "    Reference: {$config['reference']['set']} UT (JD " . number_format($set_expected_jd, 6) . ")\n";
        echo "    PHP:       " . formatTime($set_php) . " UT (JD " . number_format($set_php, 6) . ")\n";
        echo "    Difference: " . sprintf("%+.2f", $set_diff_sec) . " seconds [" . ($set_pass ? 'PASS' : 'FAIL') . "]\n";

        if ($set_pass) {
            $passCount++;
        } else {
            $failCount++;
        }
    }

    echo "\n";
}

echo "=== Summary ===\n";
echo "PASS: $passCount\n";
echo "FAIL: $failCount\n";
echo "Total: " . ($passCount + $failCount) . "\n";

if ($failCount > 0) {
    echo "\n✗ Some tests FAILED\n";
    exit(1);
} else {
    echo "\n✓ All tests PASSED\n";
    exit(0);
}
