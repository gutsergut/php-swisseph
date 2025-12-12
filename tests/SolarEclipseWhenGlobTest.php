<?php

/**
 * Test swe_sol_eclipse_when_glob() - Find next solar eclipse globally
 *
 * Compare PHP implementation with C reference (swetest64.exe)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\SolarEclipseWhenGlobFunctions;
use Swisseph\Constants;

// Initialize ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo str_repeat('=', 70) . "\n";
echo "Testing swe_sol_eclipse_when_glob() - Solar Eclipse Global Search\n";
echo str_repeat('=', 70) . "\n\n";

// Test 1: Find next total eclipse after 2024-01-01
echo "Test 1: Find next total eclipse after 2024-01-01\n";
echo str_repeat('-', 70) . "\n";

$tjdStart = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$tret = array_fill(0, 10, 0.0); // Must pre-allocate array
$serr = '';

$retflag = swe_sol_eclipse_when_glob(
    $tjdStart,
    Constants::SEFLG_SWIEPH,
    Constants::SE_ECL_TOTAL,  // Search for total eclipses only
    $tret,
    0,  // forward search
    $serr
);

if ($retflag === Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

// Convert to calendar date
if (isset($tret[0]) && $tret[0] > 0) {
    $cal = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    $year = $cal['y'];
    $month = $cal['m'];
    $day = $cal['d'];
    $hour = $cal['ut'];

    $hours = (int)floor($hour);
    $minutes = (int)floor(($hour - $hours) * 60);
    $seconds = (int)floor((($hour - $hours) * 60 - $minutes) * 60);

    echo sprintf("Found eclipse at: %04d-%02d-%02d %02d:%02d:%02d UT\n",
        $year, $month, $day, $hours, $minutes, $seconds);
    echo sprintf("Julian Day: %.8f\n", $tret[0]);

    // Decode flags
    echo "Eclipse type: ";
    if ($retflag & Constants::SE_ECL_TOTAL) echo "TOTAL ";
    if ($retflag & Constants::SE_ECL_ANNULAR) echo "ANNULAR ";
    if ($retflag & Constants::SE_ECL_PARTIAL) echo "PARTIAL ";
    if ($retflag & Constants::SE_ECL_ANNULAR_TOTAL) echo "ANNULAR-TOTAL ";
    if ($retflag & Constants::SE_ECL_CENTRAL) echo "CENTRAL ";
    if ($retflag & Constants::SE_ECL_NONCENTRAL) echo "NONCENTRAL ";
    echo "\n";

    echo "\nExpected (from NASA): 2024-04-08 (next total after 2024-01-01)\n";

    // Verify it's close to 2024-04-08
    $expectedJd = swe_julday(2024, 4, 8, 18.0, Constants::SE_GREG_CAL);
    $diff = abs($tret[0] - $expectedJd);

    if ($diff < 1.0) {
        echo "✓ Result matches NASA data (within 1 day)\n";
    } else {
        echo "✗ Result differs from NASA data by " . sprintf("%.2f", $diff) . " days\n";
    }
} else {
    echo "No eclipse found (unexpected)\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Test completed\n";
echo str_repeat('=', 70) . "\n";
