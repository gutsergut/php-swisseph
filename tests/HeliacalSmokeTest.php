<?php

declare(strict_types=1);

/**
 * Heliacal Functions Smoke Test
 * Quick verification that all PUBLIC APIs are callable
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Heliacal Functions Smoke Test ===\n\n";

// Setup
$jd_ut = 2451545.0; // J2000.0
$dgeo = [13.4, 52.5, 100.0]; // Berlin: lon, lat, height
$datm = [1013.25, 15.0, 50.0, 40.0]; // pressure, temp, RH, VR
$dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0]; // age, SN, binocular, mag, dia, trans
$helflag = Constants::SEFLG_SWIEPH;

echo "Test parameters:\n";
echo "  JD: $jd_ut (J2000.0)\n";
echo "  Location: Berlin (13.4°E, 52.5°N, 100m)\n";
echo "  Object: Venus\n\n";

// Test 1: swe_heliacal_ut - main API
echo "1. Testing swe_heliacal_ut()...\n";
$dret = array_fill(0, 10, 0.0);
$serr = '';
$retval = swe_heliacal_ut(
    $jd_ut,
    $dgeo,
    $datm,
    $dobs,
    'venus',
    1, // morning first
    $helflag,
    $dret,
    $serr
);

if ($retval >= 0 || $retval === -2) {
    echo "   ✓ API callable, return code: $retval\n";
    if ($retval >= 0) {
        echo "   Event date: " . date('Y-m-d H:i:s', ($dret[0] - 2440587.5) * 86400) . " UT\n";
    } elseif ($retval === -2) {
        echo "   No event found within search period (expected for some dates)\n";
    }
} else {
    echo "   ✗ FAILED (code: $retval): $serr\n";
    exit(1);
}

// Test 2: swe_vis_limit_mag
echo "\n2. Testing swe_vis_limit_mag()...\n";
$dret = array_fill(0, 10, 0.0);
$serr = '';
$retval = swe_vis_limit_mag(
    $jd_ut,
    $dgeo,
    $datm,
    $dobs,
    'venus',
    $helflag,
    $dret,
    $serr
);

if ($retval >= 0) {
    echo "   ✓ API callable\n";
    echo "   Visual limiting magnitude: " . number_format($dret[0], 2) . "\n";
    echo "   Object magnitude: " . number_format($dret[7], 2) . "\n";
} else {
    echo "   ✗ FAILED: $serr\n";
    exit(1);
}

// Test 3: swe_heliacal_pheno_ut
echo "\n3. Testing swe_heliacal_pheno_ut()...\n";
$darr = array_fill(0, 30, 0.0);
$serr = '';
$retval = swe_heliacal_pheno_ut(
    $jd_ut,
    $dgeo,
    $datm,
    $dobs,
    'venus',
    1,
    $helflag,
    $darr,
    $serr
);

if ($retval >= 0) {
    echo "   ✓ API callable\n";
    echo "   Object altitude: " . number_format($darr[0], 2) . "°\n";
    echo "   Object azimuth: " . number_format($darr[1], 2) . "°\n";
    echo "   Sun altitude: " . number_format($darr[2], 2) . "°\n";
    echo "   Topocentric arcus visionis: " . number_format($darr[4], 2) . "°\n";
} else {
    echo "   ✗ FAILED: $serr\n";
    exit(1);
}

// Test 4: swe_heliacal_angle
echo "\n4. Testing swe_heliacal_angle()...\n";
$dret = array_fill(0, 10, 0.0);
$serr = '';
$retval = swe_heliacal_angle(
    $jd_ut,
    $dgeo,
    $datm,
    $dobs,
    'venus',
    $helflag,
    -4.0, // Venus magnitude
    90.0, // AziO
    -1.0, // AltM (not used)
    0.0,  // AziM
    270.0, // AziS (Sun in west)
    $dret,
    $serr
);

if ($retval >= 0) {
    echo "   ✓ API callable\n";
    echo "   Heliacal angle: " . number_format($dret[0], 2) . "°\n";
    echo "   Arcus visionis: " . number_format($dret[1], 2) . "°\n";
    echo "   Solar altitude: " . number_format($dret[2], 2) . "°\n";
} else {
    echo "   ✗ FAILED: $serr\n";
    exit(1);
}

// Test 5: swe_topo_arcus_visionis
echo "\n5. Testing swe_topo_arcus_visionis()...\n";
$dret = array_fill(0, 10, 0.0);
$serr = '';
$retval = swe_topo_arcus_visionis(
    $jd_ut,
    $dgeo,
    $datm,
    $dobs,
    'venus',
    $helflag,
    -4.0,  // mag
    90.0,  // AziO
    10.0,  // AltO
    270.0, // AziS
    -5.0,  // AltS (dawn/dusk)
    -1.0,  // AziM (not used)
    0.0,   // AltM
    $dret,
    $serr
);

if ($retval >= 0) {
    echo "   ✓ API callable\n";
    echo "   Topocentric arcus visionis: " . number_format($dret[0], 2) . "°\n";
} else {
    echo "   ✗ FAILED: $serr\n";
    exit(1);
}

echo "\n=== All Heliacal APIs Smoke Tests PASSED ✓ ===\n";
echo "Total: 5/5 APIs verified callable\n";
echo "Full validation with swetest64 recommended for production use.\n";
