<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Quick test to verify eclipse finding after coordinate fix
// Expected: 2024-04-08 total solar eclipse visible from Dallas

$geopos = [-96.8, 32.8, 0.0]; // Dallas, TX
$tjd_start = 2460400.0; // Around 2024-04-01

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
swe_set_topo($geopos[0], $geopos[1], $geopos[2]);

echo "Testing swe_sol_eclipse_when_loc for Dallas, TX\n";
echo "Start date: " . swe_julday(2024, 4, 1, 12.0, Constants::SE_GREG_CAL) . " (2024-04-01)\n";
echo "Searching for next total solar eclipse...\n\n";

$tret = array_fill(0, 10, 0.0);
$attr = array_fill(0, 20, 0.0);
$serr = null;

$iflag = Constants::SEFLG_SWIEPH;
$ifltype = Constants::SE_ECL_ALLTYPES_SOLAR;

$retval = swe_sol_eclipse_when_loc($tjd_start, $iflag, $geopos, $tret, $attr, false, $serr);

if ($retval < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Eclipse found!\n";
echo "Return value: $retval\n";
echo "Eclipse type: ";
if ($retval & Constants::SE_ECL_TOTAL) {
    echo "TOTAL\n";
} elseif ($retval & Constants::SE_ECL_ANNULAR) {
    echo "ANNULAR\n";
} elseif ($retval & Constants::SE_ECL_PARTIAL) {
    echo "PARTIAL\n";
} else {
    echo "UNKNOWN ($retval)\n";
}

// Convert to calendar date
$year = $month = $day = $hour = 0;
swe_revjul($tret[0], 1, $year, $month, $day, $hour);

echo "\nEclipse date: " . sprintf("%04d-%02d-%02d %02d:%02d UT",
    $year, $month, $day, floor($hour), floor(($hour - floor($hour)) * 60));
echo "\nJD: {$tret[0]}\n";

echo "\nExpected: 2024-04-08 (TOTAL)\n";

if ($year == 2024 && $month == 4 && $day == 8 && ($retval & Constants::SE_ECL_TOTAL)) {
    echo "\n✅ TEST PASSED: Found correct eclipse!\n";
} else {
    echo "\n❌ TEST FAILED: Wrong eclipse date or type\n";
}
