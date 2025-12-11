<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Time\DeltaTFull;
use Swisseph\DeltaT;

// Compare DeltaTFull (Bessel interpolation) vs old DeltaT (polynomial)
$test_dates = [
    ['date' => '2000-01-01', 'tjd' => 2451544.5],
    ['date' => '2006-01-01', 'tjd' => 2453736.5],
    ['date' => '2014-01-01', 'tjd' => 2456658.5],
    ['date' => '2020-01-01', 'tjd' => 2458849.5],
    ['date' => '2024-04-08', 'tjd' => 2460409.2630702],
];

echo "Comparing DeltaTFull (Bessel) vs DeltaT (Polynomial)\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($test_dates as $test) {
    $tjd = $test['tjd'];
    $Y = 2000.0 + ($tjd - 2451544.5) / 365.25;

    echo "Date: {$test['date']} (JD $tjd, Year $Y)\n";

    // Full Bessel implementation
    $dt_full_days = DeltaTFull::deltaTAA($tjd, -1);
    $dt_full_sec = $dt_full_days * 86400.0;

    // Old polynomial implementation
    $dt_old_sec = DeltaT::deltaTSecondsFromJd($tjd);
    $dt_old_days = $dt_old_sec / 86400.0;

    $diff_sec = $dt_full_sec - $dt_old_sec;

    printf("  DeltaTFull (Bessel):  %12.6f sec  (%14.10f days)\n", $dt_full_sec, $dt_full_days);
    printf("  DeltaT (Polynomial):  %12.6f sec  (%14.10f days)\n", $dt_old_sec, $dt_old_days);
    printf("  Difference:           %12.6f sec\n", $diff_sec);
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "DeltaTFull uses exact C algorithm (Bessel interpolation + tables)\n";
echo "DeltaT uses simplified polynomial approximation\n";
