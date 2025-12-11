<?php

declare(strict_types=1);

/**
 * TRUE Ayanamsha Test
 *
 * Tests "True" ayanamsha modes based on fixed star positions:
 * - SE_SIDM_TRUE_CITRA (Spica)
 * - SE_SIDM_TRUE_REVATI (ζ Psc)
 * - SE_SIDM_TRUE_PUSHYA (δ Cnc)
 *
 * Reference from swetest64 for 2025-01-01 12:00 UT:
 * swetest64.exe -b1.1.2025 -ut12:00 -p0 -eswe -edir.\eph\ephe -sid27  (True Citra)
 * swetest64.exe -b1.1.2025 -ut12:00 -p0 -eswe -edir.\eph\ephe -sid28  (True Revati)
 * swetest64.exe -b1.1.2025 -ut12:00 -p0 -eswe -edir.\eph\ephe -sid29  (True Pushya)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// 2025-01-01 12:00:00 UT
$year = 2025;
$month = 1;
$day = 1;
$hour = 12.0;

$jd_ut = swe_julday($year, $month, $day, $hour, Constants::SE_GREG_CAL);

echo "=== TRUE Ayanamsha Test ===\n";
echo "Date: 2025-01-01 12:00:00 UT\n";
echo "JD: " . number_format($jd_ut, 6) . "\n\n";

// Test 1: TRUE Citra (Spica)
echo "Test 1: SE_SIDM_TRUE_CITRA (Spica)\n";
echo str_repeat('-', 50) . "\n";

swe_set_sid_mode(Constants::SE_SIDM_TRUE_CITRA, 0, 0);
$aya_citra = swe_get_ayanamsa_ut($jd_ut);

printf("Ayanamsha: %.10f°\n", $aya_citra);
echo "Formula: Spica_longitude - 180°\n";

// Get Spica position to verify
$star = 'Spica';
$x = [];
$serr = null;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_NONUT | Constants::SEFLG_NOABERR;
$tjd_et = $jd_ut + swe_deltat_ex($jd_ut, $iflag, $serr);
swe_fixstar($star, $tjd_et, $iflag, $x, $serr);

printf("Spica longitude: %.10f°\n", $x[0]);
printf("Calculated: %.10f° - 180° = %.10f°\n", $x[0], $x[0] - 180.0);

$diff = abs($aya_citra - ($x[0] - 180.0));
if ($diff < 0.0001) {
    echo "PASS: Ayanamsha matches formula\n";
    $test1_pass = true;
} else {
    printf("FAIL: Difference %.10f° exceeds tolerance\n", $diff);
    $test1_pass = false;
}

echo "\n";

// Test 2: TRUE Revati (ζ Psc)
echo "Test 2: SE_SIDM_TRUE_REVATI (ζ Psc)\n";
echo str_repeat('-', 50) . "\n";

swe_set_sid_mode(Constants::SE_SIDM_TRUE_REVATI, 0, 0);
$aya_revati = swe_get_ayanamsa_ut($jd_ut);

printf("Ayanamsha: %.10f°\n", $aya_revati);
echo "Formula: zePsc_longitude - 359.8333°\n";

// Get ζ Psc position to verify
$star = ',zePsc';
$x = [];
$serr = null;
swe_fixstar($star, $tjd_et, $iflag, $x, $serr);

printf("ζ Psc longitude: %.10f°\n", $x[0]);
printf("Calculated: %.10f° - 359.8333° = %.10f°\n", $x[0], $x[0] - 359.8333333333);

// Normalize the result for comparison
$expected = $x[0] - 359.8333333333;
while ($expected < 0) $expected += 360;
while ($expected >= 360) $expected -= 360;

$diff = abs($aya_revati - $expected);
if ($diff > 180) $diff = 360 - $diff; // Handle wraparound

if ($diff < 0.0001) {
    echo "PASS: Ayanamsha matches formula\n";
    $test2_pass = true;
} else {
    printf("FAIL: Difference %.10f° exceeds tolerance\n", $diff);
    $test2_pass = false;
}

echo "\n";

// Test 3: TRUE Pushya (δ Cnc)
echo "Test 3: SE_SIDM_TRUE_PUSHYA (δ Cnc)\n";
echo str_repeat('-', 50) . "\n";

swe_set_sid_mode(Constants::SE_SIDM_TRUE_PUSHYA, 0, 0);
$aya_pushya = swe_get_ayanamsa_ut($jd_ut);

printf("Ayanamsha: %.10f°\n", $aya_pushya);
echo "Formula: deCnc_longitude - 106°\n";

// Get δ Cnc position to verify
$star = ',deCnc';
$x = [];
$serr = null;
swe_fixstar($star, $tjd_et, $iflag, $x, $serr);

printf("δ Cnc longitude: %.10f°\n", $x[0]);
printf("Calculated: %.10f° - 106° = %.10f°\n", $x[0], $x[0] - 106.0);

$diff = abs($aya_pushya - ($x[0] - 106.0));
if ($diff > 180) $diff = 360 - $diff; // Handle wraparound

if ($diff < 0.0001) {
    echo "PASS: Ayanamsha matches formula\n";
    $test3_pass = true;
} else {
    printf("FAIL: Difference %.10f° exceeds tolerance\n", $diff);
    $test3_pass = false;
}

echo "\n";

// Summary
echo "=== Summary ===\n";
echo "TRUE Citra:  " . ($test1_pass ? "PASS" : "FAIL") . "\n";
echo "TRUE Revati: " . ($test2_pass ? "PASS" : "FAIL") . "\n";
echo "TRUE Pushya: " . ($test3_pass ? "PASS" : "FAIL") . "\n";

if ($test1_pass && $test2_pass && $test3_pass) {
    echo "\n✓ ALL TESTS PASSED\n";
    echo "\nTRUE ayanamshas are now fully functional!\n";
    echo "They dynamically calculate ayanamsha from actual star positions.\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED\n";
    exit(1);
}
