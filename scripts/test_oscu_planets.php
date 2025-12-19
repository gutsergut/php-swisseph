<?php
/**
 * Test osculating nodes for multiple planets against C reference (swetest64.exe)
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Setup
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_ut = 2451545.0; // J2000.0
$tjd_et = $tjd_ut + swe_deltat($tjd_ut);
$flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$method = Constants::SE_NODBIT_OSCU;

// Reference values from swetest64.exe (1.1.2000 12:00 UT)
$planets = [
    Constants::SE_MARS    => ['name' => 'Mars',    'asc' => 7.6769472, 'desc' => 248.8829597],
    Constants::SE_JUPITER => ['name' => 'Jupiter', 'asc' => 100.5194687, 'desc' => 280.4645626],
    Constants::SE_SATURN  => ['name' => 'Saturn',  'asc' => 115.2334251, 'desc' => 292.4603933],
];

echo "=== Osculating Nodes Test: Multiple Planets ===\n";
echo "Date: J2000.0 (2000-01-01 12:00 TT)\n";
echo "Reference: swetest64.exe v2.10.03\n\n";

$maxDiff = 0;
$allPass = true;

foreach ($planets as $ipl => $ref) {
    $xnasc = [];
    $xndsc = [];
    $xperi = [];
    $xaphe = [];
    $serr = '';

    swe_nod_aps($tjd_et, $ipl, $flags, $method, $xnasc, $xndsc, $xperi, $xaphe, $serr);

    $asc = $xnasc[0];
    $desc = $xndsc[0];

    $dAsc = ($asc - $ref['asc']) * 3600; // arcseconds
    $dDesc = ($desc - $ref['desc']) * 3600;

    $maxDiff = max($maxDiff, abs($dAsc), abs($dDesc));
    $pass = abs($dAsc) < 1.0 && abs($dDesc) < 1.0; // < 1"

    if (!$pass) $allPass = false;

    printf("%s:\n", $ref['name']);
    printf("  Asc:  PHP=%.7f° C=%.7f° diff=%+.2f\"\n", $asc, $ref['asc'], $dAsc);
    printf("  Desc: PHP=%.7f° C=%.7f° diff=%+.2f\"\n", $desc, $ref['desc'], $dDesc);
    printf("  %s\n\n", $pass ? "✓ PASS" : "✗ FAIL");
}

echo "========================================\n";
printf("Maximum difference: %.2f\"\n", $maxDiff);
printf("Result: %s\n", $allPass ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED");
