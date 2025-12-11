<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Time\DeltaTFull;

// Test DeltaTFull across different time periods
$test_cases = [
    // Year 2000
    [
        'date' => '2000-01-01',
        'tjd' => 2451544.5,
        'expected_sec' => 63.8285,
        'tolerance_sec' => 0.01
    ],
    // Year 2006
    [
        'date' => '2006-01-01',
        'tjd' => 2453736.5,
        'expected_sec' => 65.19,
        'tolerance_sec' => 0.05
    ],
    // Year 2014
    [
        'date' => '2014-01-01',
        'tjd' => 2456658.5,
        'expected_sec' => 67.64,
        'tolerance_sec' => 0.05
    ],
    // Year 2020
    [
        'date' => '2020-01-01',
        'tjd' => 2458849.5,
        'expected_sec' => 69.36,
        'tolerance_sec' => 0.05
    ],
    // Year 2024 (our problematic case)
    [
        'date' => '2024-04-08',
        'tjd' => 2460409.2630702,
        'expected_sec' => 69.074,
        'tolerance_sec' => 0.01
    ],
];

echo "Testing DeltaTFull across multiple years\n";
echo str_repeat("=", 70) . "\n\n";

$passed = 0;
$total = count($test_cases);

foreach ($test_cases as $test) {
    $tjd = $test['tjd'];
    $Y = 2000.0 + ($tjd - 2451544.5) / 365.25;

    echo "Date: {$test['date']} (JD $tjd, Year $Y)\n";

    $dt_days = DeltaTFull::deltaTAA($tjd, -1);
    $dt_seconds = $dt_days * 86400.0;

    $error_sec = abs($dt_seconds - $test['expected_sec']);

    echo "  PHP:      $dt_seconds sec\n";
    echo "  Expected: {$test['expected_sec']} sec\n";
    echo "  Error:    $error_sec sec\n";

    if ($error_sec < $test['tolerance_sec']) {
        echo "  ✅ PASS (tolerance: {$test['tolerance_sec']} sec)\n";
        $passed++;
    } else {
        echo "  ❌ FAIL (tolerance: {$test['tolerance_sec']} sec)\n";
    }
    echo "\n";
}

echo str_repeat("=", 70) . "\n";
echo "Results: $passed / $total tests passed\n";

if ($passed === $total) {
    echo "✅ ALL TESTS PASSED - DeltaTFull is accurate!\n";
} else {
    echo "❌ SOME TESTS FAILED - Need investigation\n";
}
