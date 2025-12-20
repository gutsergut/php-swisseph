<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Testing single conjunction search iteration ===\n";

$t = swe_julday(2023, 3, 1, 12.0, Constants::SE_GREG_CAL);
$ipl = Constants::SE_SATURN;
$starname = null;
$ifl = Constants::SEFLG_SWIEPH;

echo "Initial t: $t\n";

// Get initial positions
$ls = [];
$lm = [];
$serr = '';

swe_calc($t, $ipl, $ifl, $ls, $serr);
swe_calc($t, Constants::SE_MOON, $ifl, $lm, $serr);

echo sprintf("Saturn: lon=%.3f°, lat=%.3f°\n", $ls[0], $ls[1]);
echo sprintf("Moon: lon=%.3f°, lat=%.3f°\n", $lm[0], $lm[1]);

$dl = swe_degnorm($ls[0] - $lm[0]);
echo sprintf("dl (normalized): %.3f°\n", $dl);

// Simulate one iteration
$dt = $dl / 13;
$t += $dt;

echo sprintf("dt = dl/13 = %.6f days\n", $dt);
echo sprintf("New t: %.6f\n", $t);

// Get new positions
swe_calc($t, $ipl, $ifl, $ls, $serr);
swe_calc($t, Constants::SE_MOON, $ifl, $lm, $serr);

echo sprintf("Saturn: lon=%.3f°, lat=%.3f°\n", $ls[0], $ls[1]);
echo sprintf("Moon: lon=%.3f°, lat=%.3f°\n", $lm[0], $lm[1]);

$dl_new = swe_degnorm($ls[0] - $lm[0]);
if ($dl_new > 180) {
    $dl_new -= 360;
}
echo sprintf("New dl: %.3f°\n", $dl_new);
echo sprintf("dl reduction: %.3f° → %.3f° (reduced by %.3f°)\n", $dl, $dl_new, $dl - $dl_new);

swe_close();
