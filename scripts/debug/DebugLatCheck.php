<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Testing conjunction + latitude check ===\n";

$t_start = swe_julday(2023, 3, 1, 12.0, Constants::SE_GREG_CAL);
$ipl = Constants::SE_SATURN;
$ifl = Constants::SEFLG_SWIEPH;

$t = $t_start;
$ls = [];
$lm = [];
$serr = '';

// Get rough conjunction
swe_calc($t, $ipl, $ifl, $ls, $serr);
swe_calc($t, Constants::SE_MOON, $ifl, $lm, $serr);

$dl = swe_degnorm($ls[0] - $lm[0]);

echo "Searching for conjunction...\n";
$iter = 0;
while (abs($dl) > 0.1) {
    $t += $dl / 13;
    swe_calc($t, $ipl, $ifl, $ls, $serr);
    swe_calc($t, Constants::SE_MOON, $ifl, $lm, $serr);
    $dl = swe_degnorm($ls[0] - $lm[0]);
    if ($dl > 180) {
        $dl -= 360;
    }
    if (++$iter > 100) {
        echo "Convergence failed!\n";
        break;
    }
}

echo sprintf("Converged after %d iterations at JD %.6f\n", $iter, $t);
echo sprintf("Saturn: lon=%.6f°, lat=%.6f°\n", $ls[0], $ls[1]);
echo sprintf("Moon: lon=%.6f°, lat=%.6f°\n", $lm[0], $lm[1]);
echo sprintf("Final dl: %.6f°\n", $dl);

// Check latitude difference
$drad = abs($ls[1] - $lm[1]);
echo sprintf("\nLatitude difference: %.6f°\n", $drad);

if ($drad > 2) {
    echo "REJECT: Latitude difference > 2°, would skip to next month\n";
    echo sprintf("Next try would start at JD %.6f (t + 20 days)\n", $t + 20);
} else {
    echo "ACCEPT: Latitude difference <= 2°, would proceed to refinement\n";
}

swe_close();
