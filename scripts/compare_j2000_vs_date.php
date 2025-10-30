<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\NodesApsides;
use Swisseph\Constants;

// No need to set ephe path for mean nodes

$jd = 2451545.0; // J2000

echo "=== Comparing J2000 vs Date Equinox ===\n\n";

// Test Jupiter mean ascending node
$ipl = Constants::SE_JUPITER;
$method = Constants::SE_NODBIT_MEAN;
$serr = '';

echo "Jupiter mean ascending node at JD $jd (J2000.0)\n\n";

// Without J2000 flag (mean equinox of date)
$serr = '';
$ret_date = NodesApsides::compute($jd, $ipl, $method, $serr);
$results_date = NodesApsides::getResults();
$xx_date = $results_date[0]; // ascending node

if ($ret_date >= 0) {
    echo "WITHOUT SEFLG_J2000 (mean equinox of date):\n";
    printf("  Ascending node longitude: %.6f°\n", $xx_date[0]);
    printf("  Ascending node latitude:  %.6f°\n", $xx_date[1]);
} else {
    echo "Error (without J2000): $serr\n";
}

echo "\n";

// With J2000 flag (J2000 equinox)
$serr = '';
$ret_j2000 = NodesApsides::compute($jd, $ipl, Constants::SEFLG_J2000 | $method, $serr);
$results_j2000 = NodesApsides::getResults();
$xx_j2000 = $results_j2000[0]; // ascending node

if ($ret_j2000 >= 0) {
    echo "WITH SEFLG_J2000 (J2000 equinox):\n";
    printf("  Ascending node longitude: %.6f°\n", $xx_j2000[0]);
    printf("  Ascending node latitude:  %.6f°\n", $xx_j2000[1]);
} else {
    echo "Error (with J2000): $serr\n";
}

echo "\n";

if ($ret_date >= 0 && $ret_j2000 >= 0) {
    $diff = $xx_j2000[0] - $xx_date[0];
    echo "Difference (J2000 - date): {$diff}° (" . ($diff * 3600) . "\")\n\n";

    if (abs($diff) < 0.001) {
        echo "✓ Same result (within 0.001°) - as expected for J2000 date\n";
    } else {
        echo "✗ Different results - unexpected!\n";
    }
}

// Also test with swetest
echo "\n=== Comparing with swetest ===\n\n";

$cmd = 'cd "C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph" ; & "с-swisseph\\swisseph\\windows\\programs\\swetest64.exe" -bJ2000 -p5 -fn 2>&1';
$output = shell_exec("powershell -Command \"$cmd\"");

if ($output) {
    echo "swetest output:\n";
    echo $output;
}
