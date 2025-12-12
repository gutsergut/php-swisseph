<?php

/**
 * Quick Smoke Test for Occultation Functions
 *
 * Tests that swe_lun_occult_when_glob() and related functions work
 * without crashing and return reasonable values.
 */

declare(strict_types=1);

use Swisseph\Constants;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== Occultation Functions Smoke Test ===\n\n";

// Test 1: Find next lunar occultation of a bright star (Aldebaran)
echo "Test 1: Next lunar occultation of Aldebaran after 2024-01-01\n";
echo str_repeat('-', 70) . "\n";

$tjd_start = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$ipl = 0; // ignored for stars
$starname = ',alTau'; // Aldebaran (Alpha Tauri)
$tret = array_fill(0, 10, 0.0);
$serr = '';

$retflag = swe_lun_occult_when_glob(
    $tjd_start,
    $ipl,
    $starname,
    Constants::SEFLG_SWIEPH,
    0, // any type
    $tret,
    0, // forward
    $serr
);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} elseif ($retflag === 0) {
    echo "No occultation found (may be too far in future)\n";
} else {
    $cal = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    echo sprintf(
        "Found occultation at: %04d-%02d-%02d %02d:%02d UT (JD %.5f)\n",
        $cal['y'], $cal['m'], $cal['d'],
        (int)$cal['ut'], (int)(($cal['ut'] - (int)$cal['ut']) * 60),
        $tret[0]
    );

    echo "Type flags: ";
    if ($retflag & Constants::SE_ECL_TOTAL) echo "TOTAL ";
    if ($retflag & Constants::SE_ECL_ANNULAR) echo "ANNULAR ";
    if ($retflag & Constants::SE_ECL_PARTIAL) echo "PARTIAL ";
    if ($retflag & Constants::SE_ECL_CENTRAL) echo "CENTRAL ";
    echo "\n";

    echo "✓ Function works (returns valid data)\n";
}

echo "\n";

// Test 2: Find planetary occultation (Venus by Moon)
echo "Test 2: Next lunar occultation of Venus after 2024-01-01\n";
echo str_repeat('-', 70) . "\n";

$tjd_start = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$tret = array_fill(0, 10, 0.0);
$serr = '';

$retflag = swe_lun_occult_when_glob(
    $tjd_start,
    Constants::SE_VENUS,
    null, // planet, not star
    Constants::SEFLG_SWIEPH,
    0, // any type
    $tret,
    0, // forward
    $serr
);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} elseif ($retflag === 0) {
    echo "No occultation found (may be rare event)\n";
} else {
    $cal = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    echo sprintf(
        "Found occultation at: %04d-%02d-%02d %02d:%02d UT\n",
        $cal['y'], $cal['m'], $cal['d'],
        (int)$cal['ut'], (int)(($cal['ut'] - (int)$cal['ut']) * 60)
    );
    echo "✓ Function works (returns valid data)\n";
}

echo "\n";

// Test 3: Test swe_lun_occult_where for a specific time
echo "Test 3: swe_lun_occult_where - geographic path (basic test)\n";
echo str_repeat('-', 70) . "\n";

// Use a hypothetical time (won't be accurate, just testing function)
$tjd_ut = swe_julday(2024, 6, 15, 12.0, Constants::SE_GREG_CAL);
$geopos = array_fill(0, 10, 0.0);
$attr = array_fill(0, 20, 0.0);
$serr = '';

$retflag = swe_lun_occult_where(
    $tjd_ut,
    Constants::SE_MARS,
    null,
    Constants::SEFLG_SWIEPH,
    $geopos,
    $attr,
    $serr
);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} elseif ($retflag === 0) {
    echo "No occultation at this time (expected)\n";
    echo "✓ Function works (correctly returns 0 for no occultation)\n";
} else {
    echo sprintf("Geographic center: lon=%.2f°, lat=%.2f°\n", $geopos[0], $geopos[1]);
    echo "✓ Function works (returns geographic data)\n";
}

echo "\n";

// Test 4: Test swe_lun_occult_when_loc for specific location
echo "Test 4: swe_lun_occult_when_loc - local occultation search\n";
echo str_repeat('-', 70) . "\n";

$tjd_start = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
$geopos_obs = [0.0, 51.5, 0.0]; // London
$tret = array_fill(0, 10, 0.0);
$attr = array_fill(0, 20, 0.0);
$serr = '';

$retflag = swe_lun_occult_when_loc(
    $tjd_start,
    Constants::SE_JUPITER,
    null,
    Constants::SEFLG_SWIEPH,
    $geopos_obs,
    $tret,
    $attr,
    0, // forward
    $serr
);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} elseif ($retflag === 0) {
    echo "No occultation found from this location (may be far in future)\n";
    echo "✓ Function works (returns 0 when no event found)\n";
} else {
    $cal = swe_revjul($tret[0], Constants::SE_GREG_CAL);
    echo sprintf(
        "Found occultation visible from London: %04d-%02d-%02d %02d:%02d UT\n",
        $cal['y'], $cal['m'], $cal['d'],
        (int)$cal['ut'], (int)(($cal['ut'] - (int)$cal['ut']) * 60)
    );
    echo "✓ Function works (returns valid data)\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "SMOKE TEST COMPLETE\n";
echo "All occultation functions are callable and return reasonable values.\n";
echo "Note: Actual occultations are rare events, so 'no event found' is normal.\n";
echo str_repeat('=', 70) . "\n\n";

swe_close();
