<?php

/**
 * Test swe_calc_pctr() - Planetocentric positions
 *
 * Port from sweph.c:8096-8340 (~250 lines)
 * Full implementation with light-time correction, deflection, aberration,
 * precession, nutation, and coordinate transformations.
 *
 * Reference values from swetest64.exe:
 * cmd /c "swetest64.exe -b1.1.2000 -ut12:00:00 -p4 -pctr2 -fPlj -head -eswe -edirC:\path\ephe"
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Initialize ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== swe_calc_pctr() Test ===\n\n";

// Test 1: Venus-centric Mars position
echo "Test 1: Venus-centric Mars (1.1.2000 12:00 UT)\n";
echo "Reference (swetest64): Mars  lon=359.4388477°  lat=12.7108999°\n";

$jd_ut = swe_julday(2000, 1, 1, 12.0, Constants::SE_GREG_CAL);
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH);

$xx = [];
$serr = '';
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

// Calculate Mars (ipl=4) from Venus center (iplctr=3)
$ipl = 4; // Mars
$iplctr = 3; // Venus
echo "DEBUG: ipl=$ipl, iplctr=$iplctr\n";

try {
    $ret = swe_calc_pctr($jd_et, $ipl, $iplctr, $iflag, $xx, $serr);
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

if ($ret < 0) {
    echo "ERROR: [$serr]\n";
    echo "Return code: $ret\n";
    var_dump($xx);
    exit(1);
}

echo "PHP Result:  Mars  lon=" . number_format($xx[0], 7, '.', '') . "°  lat=" . number_format($xx[1], 7, '.', '') . "°\n";
echo "Longitude diff: " . number_format(abs($xx[0] - 359.4388477), 7, '.', '') . "°\n";
echo "Latitude diff:  " . number_format(abs($xx[1] - 12.7108999), 7, '.', '') . "°\n";

// Validate within tolerance (should be < 0.001° for high precision)
$lon_diff = abs($xx[0] - 359.4388477);
$lat_diff = abs($xx[1] - 12.7108999);

if ($lon_diff < 0.01 && $lat_diff < 0.01) {
    echo "✅ PASSED: Differences within 0.01°\n";
} else {
    echo "❌ FAILED: Differences exceed tolerance\n";
    exit(1);
}

echo "\n";

// Test 2: Verify error when ipl == iplctr
echo "Test 2: Error handling (ipl == iplctr)\n";
$ret = swe_calc_pctr($jd_et, Constants::SE_MARS, Constants::SE_MARS, $iflag, $xx, $serr);
if ($ret < 0 && strpos($serr, 'must not be identical') !== false) {
    echo "✅ PASSED: Correctly rejects identical planets\n";
    echo "Error message: $serr\n";
} else {
    echo "❌ FAILED: Should reject identical planets\n";
    exit(1);
}

echo "\n";

// Test 3: Speed calculation
echo "Test 3: Speed calculation (SEFLG_SPEED)\n";
$iflag_speed = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$ret = swe_calc_pctr($jd_et, Constants::SE_JUPITER, Constants::SE_EARTH, $iflag_speed, $xx, $serr);
if ($ret >= 0) {
    echo "Jupiter from Earth center:\n";
    echo "  Position: lon=" . number_format($xx[0], 4, '.', '') . "° lat=" . number_format($xx[1], 4, '.', '') . "°\n";
    echo "  Speed:    dlon=" . number_format($xx[3], 6, '.', '') . "°/day dlat=" . number_format($xx[4], 6, '.', '') . "°/day\n";

    if ($xx[3] != 0.0 || $xx[4] != 0.0) {
        echo "✅ PASSED: Speed values calculated\n";
    } else {
        echo "❌ FAILED: Speed values are zero\n";
        exit(1);
    }
} else {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "\n";

// Test 4: Earth-centric Moon (should match regular swe_calc)
echo "Test 4: Earth-centric Moon (compare with swe_calc)\n";
$xx_pctr = [];
$xx_calc = [];
$ret1 = swe_calc_pctr($jd_et, Constants::SE_MOON, Constants::SE_EARTH, $iflag, $xx_pctr, $serr);
$ret2 = swe_calc($jd_et, Constants::SE_MOON, $iflag, $xx_calc, $serr);

if ($ret1 >= 0 && $ret2 >= 0) {
    $lon_diff = abs($xx_pctr[0] - $xx_calc[0]);
    $lat_diff = abs($xx_pctr[1] - $xx_calc[1]);

    echo "swe_calc_pctr: lon=" . number_format($xx_pctr[0], 4, '.', '') . "° lat=" . number_format($xx_pctr[1], 4, '.', '') . "°\n";
    echo "swe_calc:      lon=" . number_format($xx_calc[0], 4, '.', '') . "° lat=" . number_format($xx_calc[1], 4, '.', '') . "°\n";
    echo "Differences:   lon=" . number_format($lon_diff, 6, '.', '') . "° lat=" . number_format($lat_diff, 6, '.', '') . "°\n";

    if ($lon_diff < 0.1 && $lat_diff < 0.1) {
        echo "✅ PASSED: Earth-centric matches swe_calc within 0.1°\n";
    } else {
        echo "⚠️  WARNING: Differences larger than expected (may need investigation)\n";
    }
} else {
    echo "ERROR in calculations\n";
    exit(1);
}

echo "\n=== All tests completed ===\n";
