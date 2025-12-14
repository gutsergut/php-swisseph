<?php
/**
 * Debug Mercury speed error
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd_ut = swe_julday(2025, 1, 1, 12.0, Constants::SE_GREG_CAL);
echo "JD UT: $jd_ut\n\n";

$c_ref_speed = 1.2990637828;

// Test with different flags
$tests = [
    'Standard' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
    'TRUEPOS' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS,
    'NOABERR' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR,
    'NOGDEFL' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOGDEFL,
    'J2000' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000,
    'NONUT' => Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NONUT,
];

echo sprintf("%-20s %18s %12s\n", "Test", "Speed (deg/day)", "Error (\"/day)");
echo str_repeat("-", 52) . "\n";

foreach ($tests as $name => $iflag) {
    $xx = [];
    $ret = swe_calc_ut($jd_ut, Constants::SE_MERCURY, $iflag, $xx, $serr);

    // Compare with C reference for Standard
    if ($name === 'Standard') {
        $error = ($xx[3] - $c_ref_speed) * 3600;
    } else {
        $error = 0;
    }

    echo sprintf("%-20s %18.10f %+12.4f\n", $name, $xx[3], $error);
}
