<?php

declare(strict_types=1);

/**
 * Test swe_sol_eclipse_when_loc() implementation
 *
 * Tests the function with the total solar eclipse of April 8, 2024
 * visible from Dallas, Texas, USA.
 *
 * Location: Dallas, TX
 * - Longitude: -96.8° (96.8° W)
 * - Latitude: 32.8° (32.8° N)
 * - Altitude: 0 m
 *
 * Expected: Total solar eclipse with all contact times
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Test: swe_sol_eclipse_when_loc() ===\n";
echo "Eclipse: 2024-04-08 Total Solar Eclipse\n";
echo "Location: Dallas, Texas (96.8°W, 32.8°N)\n\n";

// Location: Dallas, Texas
$geopos = [
    -96.8,  // longitude (west is negative)
    32.8,   // latitude (north is positive)
    0.0     // altitude in meters
];

// Start search around March 2024
$tjd_start = 2460400.5; // ~2024-03-15

// Output arrays
$tret = [];
$attr = [];
$serr = '';

echo "Searching for next solar eclipse visible from Dallas...\n";
echo "Start time: JD " . number_format($tjd_start, 6) . "\n\n";

// Search for eclipse
$retflag = swe_sol_eclipse_when_loc(
    $tjd_start,
    \Swisseph\Constants::SEFLG_SWIEPH,
    $geopos,
    $tret,
    $attr,
    0,  // forward search
    $serr
);

if ($retflag === \Swisseph\Constants::SE_ERR) {
    echo "ERROR: $serr\n";
    exit(1);
}

if ($retflag === 0) {
    echo "No eclipse found\n";
    exit(1);
}

echo "✅ Eclipse found!\n\n";

// Decode eclipse type
echo "Eclipse Type: ";
$types = [];
if ($retflag & \Swisseph\Constants::SE_ECL_TOTAL) $types[] = "TOTAL";
if ($retflag & \Swisseph\Constants::SE_ECL_ANNULAR) $types[] = "ANNULAR";
if ($retflag & \Swisseph\Constants::SE_ECL_PARTIAL) $types[] = "PARTIAL";
if ($retflag & \Swisseph\Constants::SE_ECL_CENTRAL) $types[] = "CENTRAL";
if ($retflag & \Swisseph\Constants::SE_ECL_NONCENTRAL) $types[] = "NONCENTRAL";
echo implode(" | ", $types) . "\n";

// Visibility flags
echo "Visibility: ";
$vis = [];
if ($retflag & \Swisseph\Constants::SE_ECL_VISIBLE) $vis[] = "VISIBLE";
if ($retflag & \Swisseph\Constants::SE_ECL_MAX_VISIBLE) $vis[] = "MAX_VISIBLE";
if ($retflag & \Swisseph\Constants::SE_ECL_1ST_VISIBLE) $vis[] = "1ST";
if ($retflag & \Swisseph\Constants::SE_ECL_2ND_VISIBLE) $vis[] = "2ND";
if ($retflag & \Swisseph\Constants::SE_ECL_3RD_VISIBLE) $vis[] = "3RD";
if ($retflag & \Swisseph\Constants::SE_ECL_4TH_VISIBLE) $vis[] = "4TH";
echo implode(" | ", $vis) . "\n\n";

// Convert JD to calendar date/time
function jd_to_datetime($jd) {
    $result = swe_revjul($jd, 1); // Gregorian calendar
    $year = $result['y'];
    $month = $result['m'];
    $day = $result['d'];
    $hour = $result['ut'];

    $h = (int)$hour;
    $m = (int)(($hour - $h) * 60);
    $s = (int)((($hour - $h) * 60 - $m) * 60);

    return sprintf("%04d-%02d-%02d %02d:%02d:%02d UT", $year, $month, $day, $h, $m, $s);
}

// Display eclipse times
echo "=== Eclipse Times (UT) ===\n";
echo "Maximum:        JD " . number_format($tret[0], 6) . " = " . jd_to_datetime($tret[0]) . "\n";
echo "1st contact:    JD " . number_format($tret[1], 6) . " = " . jd_to_datetime($tret[1]) . "\n";
if ($tret[2] > 0) {
    echo "2nd contact:    JD " . number_format($tret[2], 6) . " = " . jd_to_datetime($tret[2]) . "\n";
}
if ($tret[3] > 0) {
    echo "3rd contact:    JD " . number_format($tret[3], 6) . " = " . jd_to_datetime($tret[3]) . "\n";
}
echo "4th contact:    JD " . number_format($tret[4], 6) . " = " . jd_to_datetime($tret[4]) . "\n";

if ($tret[5] > 0) {
    echo "Sunrise:        JD " . number_format($tret[5], 6) . " = " . jd_to_datetime($tret[5]) . "\n";
}
if ($tret[6] > 0) {
    echo "Sunset:         JD " . number_format($tret[6], 6) . " = " . jd_to_datetime($tret[6]) . "\n";
}

echo "\n=== Eclipse Attributes ===\n";
echo "Magnitude:      " . number_format($attr[0], 4) . " (fraction of diameter covered)\n";
echo "Diameter ratio: " . number_format($attr[1], 4) . " (moon/sun)\n";
echo "Obscuration:    " . number_format($attr[2], 4) . " (fraction of area covered)\n";
echo "Core shadow:    " . number_format($attr[3], 2) . " km (TODO: needs eclipse_where)\n";
echo "Azimuth:        " . number_format($attr[4], 2) . "°\n";
echo "True altitude:  " . number_format($attr[5], 2) . "°\n";
echo "App. altitude:  " . number_format($attr[6], 2) . "°\n";
echo "Elongation:     " . number_format($attr[7], 2) . "°\n";
echo "NASA magnitude: " . number_format($attr[8], 4) . "\n";

if ($attr[9] > -99999) {
    echo "Saros series:   " . (int)$attr[9] . "\n";
    echo "Saros member:   " . (int)$attr[10] . "\n";
} else {
    echo "Saros series:   Not found\n";
}

// Calculate eclipse duration
if ($tret[2] > 0 && $tret[3] > 0) {
    $totality_duration = ($tret[3] - $tret[2]) * 24 * 60; // minutes
    echo "\n=== Eclipse Duration ===\n";
    echo "Totality: " . number_format($totality_duration, 2) . " minutes\n";
}

$partial_duration = ($tret[4] - $tret[1]) * 24 * 60; // minutes
echo "Partial phase: " . number_format($partial_duration, 2) . " minutes\n";

echo "\n=== Expected vs Actual ===\n";
echo "Expected date: 2024-04-08\n";
echo "Actual date: " . substr(jd_to_datetime($tret[0]), 0, 10) . "\n";

// Check if date matches
if (substr(jd_to_datetime($tret[0]), 0, 10) === "2024-04-08") {
    echo "✅ Date matches!\n";
} else {
    echo "❌ Date mismatch!\n";
}

// Check if it's a total eclipse
if ($retflag & \Swisseph\Constants::SE_ECL_TOTAL) {
    echo "✅ Eclipse is TOTAL as expected!\n";
} else {
    echo "❌ Eclipse type mismatch!\n";
}

// Check if maximum is visible
if ($retflag & \Swisseph\Constants::SE_ECL_MAX_VISIBLE) {
    echo "✅ Maximum is visible as expected!\n";
} else {
    echo "⚠️  Maximum not visible (might be correct for this location)\n";
}

echo "\n=== Test Complete ===\n";

// Clean up
swe_close();
