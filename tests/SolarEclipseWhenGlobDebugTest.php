<?php

/**
 * Debug test for swe_sol_eclipse_when_glob()
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Initialize ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== DEBUG: swe_sol_eclipse_when_glob() ===\n\n";

// Test: Find any eclipse after 2024-01-01
$tjdStart = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
echo "Start JD: $tjdStart\n";
list($y, $m, $d, $h) = swe_revjul($tjdStart, Constants::SE_GREG_CAL);
echo "Start date: $y-$m-$d\n\n";

// Search for ANY type of eclipse
$tret = [];
$serr = '';

$retflag = swe_sol_eclipse_when_glob(
    $tjdStart,
    Constants::SEFLG_SWIEPH,
    Constants::SE_ECL_ALLTYPES_SOLAR,  // ANY eclipse type
    $tret,
    0,  // forward search
    $serr
);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

if ($tret[0] > 0) {
    list($year, $month, $day, $hour) = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    $hours = floor($hour);
    $minutes = floor(($hour - $hours) * 60);
    $seconds = floor((($hour - $hours) * 60 - $minutes) * 60);

    echo sprintf("Found eclipse at: %04d-%02d-%02d %02d:%02d:%02d UT\n",
        $year, $month, $day, $hours, $minutes, $seconds);
    echo sprintf("Julian Day: %.8f\n", $tret[0]);

    // Decode flags
    echo "Eclipse type flags: ";
    if ($retflag & Constants::SE_ECL_TOTAL) echo "TOTAL ";
    if ($retflag & Constants::SE_ECL_ANNULAR) echo "ANNULAR ";
    if ($retflag & Constants::SE_ECL_PARTIAL) echo "PARTIAL ";
    if ($retflag & Constants::SE_ECL_ANNULAR_TOTAL) echo "ANNULAR-TOTAL ";
    if ($retflag & Constants::SE_ECL_CENTRAL) echo "CENTRAL ";
    if ($retflag & Constants::SE_ECL_NONCENTRAL) echo "NONCENTRAL ";
    echo "\n";
} else {
    echo "No eclipse found (unexpected)\n";
}
