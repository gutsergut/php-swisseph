<?php
/**
 * Test Mercury speed with different flags
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd_ut = swe_julday(2025, 1, 1, 12.0, Constants::SE_GREG_CAL);
echo "JD UT: $jd_ut\n\n";

$flags = [
    'Standard' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
    'TRUEPOS' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS,
    'NOABERR' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR,
    'NOGDEFL' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOGDEFL,
    'NOABERR+NOGDEFL' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR | Constants::SEFLG_NOGDEFL,
];

// C reference values
$c_ref = [
    'Standard' => 1.2990637828,
    'TRUEPOS' => 1.2993530000,
    'NOABERR' => 1.2990740000, // approximate
    'NOGDEFL' => 1.2990638000, // approximate
    'NOABERR+NOGDEFL' => 1.2990800000, // approximate
];

echo sprintf("%-20s %15s %15s %12s\n", "Flag", "C Reference", "PHP Speed", "Error (\"/d)");
echo str_repeat("-", 65) . "\n";

foreach ($flags as $name => $flag) {
    $serr = '';
    $xx = [];
    swe_calc_ut($jd_ut, Constants::SE_MERCURY, $flag, $xx, $serr);

    $php_speed = $xx[3];
    $c_speed = $c_ref[$name] ?? 0;
    $error = ($php_speed - $c_speed) * 3600;

    echo sprintf("%-20s %15.10f %15.10f %+12.4f\n", $name, $c_speed, $php_speed, $error);
}
