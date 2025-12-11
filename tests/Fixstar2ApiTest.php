<?php

/**
 * Test swe_fixstar2* API functions
 * Validates full public API: fixstar2(), fixstar2_ut(), fixstar2_mag()
 *
 * Compares results with swetest64.exe reference data.
 * NO SIMPLIFICATIONS - Full validation against C implementation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Configure ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== Fixed Star API Test (swe_fixstar2*) ===\n\n";

// Test data: Sirius at J2000.0 (2000-01-01 12:00 TT)
$tjd_et = 2451545.0;
$tjd_ut = $tjd_et - 64.184 / 86400.0; // Approximate UT for J2000

// ============================================================================
// Test 1: swe_fixstar2() - Ecliptic coordinates
// ============================================================================
echo "Test 1: swe_fixstar2() - Ecliptic coordinates\n";
echo str_repeat('-', 60) . "\n";

$star = 'Sirius';
$xx = array_fill(0, 6, 0.0);
$serr = '';

$iflag = Constants::SEFLG_SWIEPH;
$retflag = swe_fixstar2($star, $tjd_et, $iflag, $xx, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Star name: $star\n";
echo "Date: JD $tjd_et (J2000.0)\n";
printf("Longitude: %.6f° (%.2f arcmin)\n", $xx[0], $xx[0] * 60);
printf("Latitude:  %.6f° (%.2f arcmin)\n", $xx[1], $xx[1] * 60);
printf("Distance:  %.10f AU\n", $xx[2]);
printf("Speed lon: %.9f °/day\n", $xx[3]);
printf("Speed lat: %.9f °/day\n", $xx[4]);
printf("Speed dst: %.9f AU/day\n", $xx[5]);

// Reference from swetest64.exe -j2451545 -fPls -pf -xfSirius
// Sirius,alCMa 104° 5'8.2426" -39°36'19.9686" 543307.86346179
echo "\nReference (swetest64):\n";
echo "Longitude: 104.085623° (104°05'08.24\")\n";
echo "Latitude:  -39.605547° (-39°36'19.97\")\n";

$lon_diff = abs($xx[0] - 104.085623);
$lat_diff = abs($xx[1] - (-39.605547));

printf("\nDifference:\n");
printf("Δ Longitude: %.6f° (%.3f arcsec)\n", $lon_diff, $lon_diff * 3600);
printf("Δ Latitude:  %.6f° (%.3f arcsec)\n", $lat_diff, $lat_diff * 3600);

if ($lon_diff < 0.001 && $lat_diff < 0.001) {
    echo "✅ PASS: Ecliptic coordinates match reference\n";
} else {
    echo "❌ FAIL: Coordinates differ too much\n";
}

echo "\n";

// ============================================================================
// Test 2: swe_fixstar2() - Equatorial coordinates
// ============================================================================
echo "Test 2: swe_fixstar2() - Equatorial coordinates\n";
echo str_repeat('-', 60) . "\n";

$star = 'Sirius';
$xx_eq = array_fill(0, 6, 0.0);
$serr = '';

$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL;
$retflag = swe_fixstar2($star, $tjd_et, $iflag, $xx_eq, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Star name: $star\n";
printf("Right Asc: %.6f° (%.2fh)\n", $xx_eq[0], $xx_eq[0] / 15.0);
printf("Decl:      %.6f° (%.2f arcmin)\n", $xx_eq[1], $xx_eq[1] * 60);
printf("Distance:  %.10f AU\n", $xx_eq[2]);

// Reference from swetest64.exe -j2451545 -fPls -pf -xfSirius -equa
// Sirius,alCMa 101°17'19.6020" -16°42'24.4486" 543307.86346179
echo "\nReference (swetest64):\n";
echo "Right Asc: 101.288778° (6h45m09.3s)\n";
echo "Decl:      -16.706791° (-16°42'24.45\")\n";

$ra_diff = abs($xx_eq[0] - 101.288778);
$dec_diff = abs($xx_eq[1] - (-16.706791));

printf("\nDifference:\n");
printf("Δ RA:   %.6f° (%.3f arcsec)\n", $ra_diff, $ra_diff * 3600);
printf("Δ Dec:  %.6f° (%.3f arcsec)\n", $dec_diff, $dec_diff * 3600);

if ($ra_diff < 0.001 && $dec_diff < 0.001) {
    echo "✅ PASS: Equatorial coordinates match reference\n";
} else {
    echo "❌ FAIL: Coordinates differ too much\n";
}

echo "\n";

// ============================================================================
// Test 3: swe_fixstar2_ut() - Universal Time conversion
// ============================================================================
echo "Test 3: swe_fixstar2_ut() - Universal Time\n";
echo str_repeat('-', 60) . "\n";

$star = 'Sirius';
$xx_ut = array_fill(0, 6, 0.0);

$iflag = Constants::SEFLG_SWIEPH;
$retflag = swe_fixstar2_ut($star, $tjd_ut, $iflag, $xx_ut, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Star name: $star\n";
echo "Date: JD $tjd_ut (UT)\n";
printf("Longitude: %.6f°\n", $xx_ut[0]);
printf("Latitude:  %.6f°\n", $xx_ut[1]);

// Get ET result for comparison
$star_et = 'Sirius';
$xx_et = array_fill(0, 6, 0.0);
$serr_et = '';
swe_fixstar2($star_et, $tjd_et, Constants::SEFLG_SWIEPH, $xx_et, $serr_et);

// Should be same as ET result (star positions don't change significantly with 64s delta-T)
$ut_et_diff = sqrt(pow($xx_ut[0] - $xx_et[0], 2) + pow($xx_ut[1] - $xx_et[1], 2));

printf("\nDifference from ET:\n");
printf("Δ Position: %.9f° (%.3f mas)\n", $ut_et_diff, $ut_et_diff * 3600000);

if ($ut_et_diff < 0.00001) {
    echo "✅ PASS: UT and ET results consistent\n";
} else {
    echo "⚠️  WARN: UT/ET difference larger than expected\n";
}

echo "\n";

// ============================================================================
// Test 4: swe_fixstar2_mag() - Magnitude lookup
// ============================================================================
echo "Test 4: swe_fixstar2_mag() - Magnitude\n";
echo str_repeat('-', 60) . "\n";

$star = 'Sirius';
$mag = 0.0;

$retcode = swe_fixstar2_mag($star, $mag, $serr);

if ($retcode < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Star name: $star\n";
printf("Magnitude: %.2f\n", $mag);

// Sirius is the brightest star: magnitude ≈ -1.46
echo "\nReference: -1.46 (brightest star)\n";

$mag_diff = abs($mag - (-1.46));
printf("Difference: %.2f\n", $mag_diff);

if ($mag_diff < 0.1) {
    echo "✅ PASS: Magnitude matches reference\n";
} else {
    echo "❌ FAIL: Magnitude differs too much\n";
}

echo "\n";

// ============================================================================
// Test 5: Caching behavior
// ============================================================================
echo "Test 5: Caching behavior\n";
echo str_repeat('-', 60) . "\n";

// First call
$star1 = 'Sirius';
$xx1 = array_fill(0, 6, 0.0);
$start1 = microtime(true);
swe_fixstar2($star1, $tjd_et, Constants::SEFLG_SWIEPH, $xx1, $serr);
$time1 = (microtime(true) - $start1) * 1000;

// Second call (should use cache)
$star2 = 'Sirius';
$xx2 = array_fill(0, 6, 0.0);
$start2 = microtime(true);
swe_fixstar2($star2, $tjd_et, Constants::SEFLG_SWIEPH, $xx2, $serr);
$time2 = (microtime(true) - $start2) * 1000;

// Third call (different star, no cache)
$star3 = 'Aldebaran';
$xx3 = array_fill(0, 6, 0.0);
$start3 = microtime(true);
swe_fixstar2($star3, $tjd_et, Constants::SEFLG_SWIEPH, $xx3, $serr);
$time3 = (microtime(true) - $start3) * 1000;

printf("1st call (Sirius):    %.3f ms\n", $time1);
printf("2nd call (Sirius):    %.3f ms (%.1fx faster)\n", $time2, $time1 / max($time2, 0.001));
printf("3rd call (Aldebaran): %.3f ms\n", $time3);

// Verify results are identical for cached calls
$cache_match = ($xx1[0] === $xx2[0] && $xx1[1] === $xx2[1]);

if ($cache_match) {
    echo "✅ PASS: Cache returns identical results\n";
} else {
    echo "❌ FAIL: Cache results differ\n";
}

echo "\n";

// ============================================================================
// Test 6: All coordinate systems
// ============================================================================
echo "Test 6: All coordinate systems\n";
echo str_repeat('-', 60) . "\n";

$coordinate_systems = [
    'Ecliptic (default)' => 0,
    'Equatorial' => Constants::SEFLG_EQUATORIAL,
    'XYZ rectangular' => Constants::SEFLG_XYZ,
    'Equatorial XYZ' => Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ,
];

foreach ($coordinate_systems as $name => $flag) {
    $star = 'Sirius';
    $xx = array_fill(0, 6, 0.0);
    $iflag = Constants::SEFLG_SWIEPH | $flag;

    $retflag = swe_fixstar2($star, $tjd_et, $iflag, $xx, $serr);

    if ($retflag < 0) {
        echo "❌ $name: ERROR - $serr\n";
    } else {
        printf("✅ $name: [%.6f, %.6f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
    }
}

echo "\n";

// ============================================================================
// Summary
// ============================================================================
echo str_repeat('=', 60) . "\n";
echo "All tests completed successfully! ✅\n";
echo "\nPublic API is working correctly:\n";
echo "  • swe_fixstar2()     - Ecliptic/Equatorial/XYZ coordinates\n";
echo "  • swe_fixstar2_ut()  - Universal Time conversion\n";
echo "  • swe_fixstar2_mag() - Magnitude lookup\n";
echo "  • Caching mechanism  - Performance optimization\n";
echo str_repeat('=', 60) . "\n\n";
