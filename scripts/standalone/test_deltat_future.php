<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Time\DeltaTFull;

// Test delta T for various years (including problem dates)
$testYears = [
    '1957.82' => 2436138.89,
    '1973.24' => 2441770.99,
    '2100' => 2488069.5,
    '2050' => 2469807.5,
    '2030' => 2462502.5,
];

echo "Delta T Test\n";
echo "============\n\n";

foreach ($testYears as $year => $jd) {
    $dtDays = DeltaTFull::deltaTAA($jd);
    $dtSec = $dtDays * 86400.0;

    printf("Year %s (JD %.2f): %.3f seconds\n", $year, $jd, $dtSec);
}

echo "\nC Reference values:\n";
echo "Year 1957: ~33 seconds\n";
echo "Year 1973: ~43 seconds\n";
echo "Year 2100: 93.183 seconds\n";
