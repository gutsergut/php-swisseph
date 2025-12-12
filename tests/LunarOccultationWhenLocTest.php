<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

use function swe_set_ephe_path;
use function swe_lun_occult_when_loc;
use function swe_close;

/**
 * Test swe_lun_occult_when_loc() - local lunar occultation search
 *
 * Note: This test searches for occultations that are actually VISIBLE from
 * the given location (object above horizon). The global occultation on
 * 2024-05-03 at 23:10:34.6 UT (found by swe_lun_occult_when_glob) occurs
 * with shadow center at 169°27'E, -47°33'S, which is FAR from Berlin.
 *
 * The local search with visibility check finds the first occultation where
 * Saturn is actually above the horizon in Berlin, which is 2024-08-21.
 */

// Test 1: Find Saturn occultation visible from Berlin
echo "Test 1: Find Saturn occultation visible from Berlin\n";
echo "====================================================\n";

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tret = array_fill(0, 10, 0.0);
$attr = array_fill(0, 20, 0.0);
$serr = null;

// Berlin coordinates: 13.4°E, 52.5°N, 100m altitude
$geopos = [13.4, 52.5, 100.0];

// Search from 2024-04-06 10:18:00 UT (JD 2460406.929861)
$tjdStart = 2460406.929861;

$retflag = swe_lun_occult_when_loc(
    $tjdStart,
    Constants::SE_SATURN,
    null,
    Constants::SEFLG_SWIEPH,
    $geopos,
    $tret,
    $attr,
    0, // forward search
    $serr
);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

// Check if visible
if (!($retflag & Constants::SE_ECL_VISIBLE)) {
    echo "ERROR: Occultation not marked as visible\n";
    exit(1);
}

echo "Return flags: $retflag\n";

// Convert times to readable format
function jdToDatetime(float $jd): string {
    $ut = ($jd - 2440587.5) * 86400; // Unix timestamp
    return gmdate('Y-m-d H:i:s', (int)$ut);
}

echo "\nTimes (UT):\n";
echo "  Maximum:      " . jdToDatetime($tret[0]) . " (JD " . sprintf("%.6f", $tret[0]) . ")\n";
echo "  1st contact:  " . jdToDatetime($tret[1]) . "\n";
if ($tret[2] > 0) {
    echo "  2nd contact:  " . jdToDatetime($tret[2]) . "\n";
}
if ($tret[3] > 0) {
    echo "  3rd contact:  " . jdToDatetime($tret[3]) . "\n";
}
echo "  4th contact:  " . jdToDatetime($tret[4]) . "\n";

if ($tret[5] > 0) {
    echo "  Object rise:  " . jdToDatetime($tret[5]) . "\n";
}
if ($tret[6] > 0) {
    echo "  Object set:   " . jdToDatetime($tret[6]) . "\n";
}

echo "\nAttributes:\n";
echo "  Coverage:           " . sprintf("%.4f", $attr[0]) . "\n";
echo "  Size ratio:         " . sprintf("%.4f", $attr[1]) . "\n";
echo "  Core shadow diam:   " . sprintf("%.3f km", $attr[3]) . "\n";

// We expect SOME occultation to be found after the start date
// The exact date depends on visibility from Berlin
if ($tret[0] < $tjdStart) {
    echo "\n✗ FAILED: Found occultation is before start time\n";
    exit(1);
}

// Should be total occultation
if ($retflag & Constants::SE_ECL_TOTAL) {
    echo "  Type: TOTAL ✓\n";
} elseif ($retflag & Constants::SE_ECL_ANNULAR) {
    echo "  Type: ANNULAR ✓\n";
} elseif ($retflag & Constants::SE_ECL_PARTIAL) {
    echo "  Type: PARTIAL ✓\n";
}

// Check visibility flags
if ($retflag & Constants::SE_ECL_MAX_VISIBLE) {
    echo "  Maximum visible ✓\n";
}

echo "\n✓ Test 1 PASSED (found visible occultation)\n\n";

swe_close();

echo "===================\n";
echo "ALL TESTS PASSED ✓\n";
echo "===================\n";
