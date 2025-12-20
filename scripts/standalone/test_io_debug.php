<?php
/**
 * Debug test for Io matching C test_io_debug.c
 */

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Swe;
use Swisseph\Constants;
use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Swe\Functions\TimeFunctions;
use Swisseph\Swe\Functions\PlanetsFunctions;

$jd_ut = 2451545.0;  // J2000.0

// Set ephemeris path - need to set BOTH State and SwedState
$ephePath = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe';
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

// Calculate delta T
$delta_t = TimeFunctions::deltat($jd_ut);
$jd_tt = $jd_ut + $delta_t;

echo "=== PHP Io (9501) Debug Test ===\n";
echo sprintf("JD UT: %.10f\n", $jd_ut);
echo sprintf("JD TT: %.10f\n", $jd_tt);
echo sprintf("Delta T: %.10f days = %.6f seconds\n", $delta_t, $delta_t * 86400.0);
echo "\n";

// Reference values from C program
$ref_io = [
    'lon' => 25.2453334345,
    'lat' => -1.2606643530,
    'dist' => 4.623900795604097,
];
$ref_jupiter = [
    'lon' => 25.2530577103,
    'lat' => -1.2621945982,
    'dist' => 4.621163600880967,
];

// Test 1: Io ecliptic geocentric
echo "=== Test 1: Io ecliptic geocentric ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$xx = [];
$serr = null;
$retval = Swe::swe_calc($jd_ut, 9501, $iflag, $xx, $serr);
if ($retval < 0) {
    echo "Error: $serr\n";
} else {
    echo sprintf("iflag input:  0x%08X\n", Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED);
    echo sprintf("iflag output: 0x%08X\n", $retval);
    echo sprintf("Longitude: %.10f deg (ref: %.10f, diff: %.6f arcsec)\n",
        $xx[0], $ref_io['lon'], ($xx[0] - $ref_io['lon']) * 3600);
    echo sprintf("Latitude:  %.10f deg (ref: %.10f, diff: %.6f arcsec)\n",
        $xx[1], $ref_io['lat'], ($xx[1] - $ref_io['lat']) * 3600);
    echo sprintf("Distance:  %.15f AU (ref: %.15f, diff: %.9f AU)\n",
        $xx[2], $ref_io['dist'], $xx[2] - $ref_io['dist']);
    echo sprintf("Speed Lon: %.10f deg/day\n", $xx[3]);
    echo sprintf("Speed Lat: %.10f deg/day\n", $xx[4]);
    echo sprintf("Speed Dist:%.15f AU/day\n", $xx[5]);
}
echo "\n";

// Test 2: Jupiter for comparison
echo "=== Test 2: Jupiter ecliptic geocentric ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$retval = Swe::swe_calc($jd_ut, Constants::SE_JUPITER, $iflag, $xx, $serr);
if ($retval < 0) {
    echo "Error: $serr\n";
} else {
    echo sprintf("Longitude: %.10f deg (ref: %.10f, diff: %.6f arcsec)\n",
        $xx[0], $ref_jupiter['lon'], ($xx[0] - $ref_jupiter['lon']) * 3600);
    echo sprintf("Latitude:  %.10f deg (ref: %.10f, diff: %.6f arcsec)\n",
        $xx[1], $ref_jupiter['lat'], ($xx[1] - $ref_jupiter['lat']) * 3600);
    echo sprintf("Distance:  %.15f AU (ref: %.15f, diff: %.9f AU)\n",
        $xx[2], $ref_jupiter['dist'], $xx[2] - $ref_jupiter['dist']);
}
echo "\n";

// Test 3: Io TRUEPOS
echo "=== Test 3: Io TRUEPOS (no light-time) ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS;
$retval = Swe::swe_calc($jd_ut, 9501, $iflag, $xx, $serr);
if ($retval < 0) {
    echo "Error: $serr\n";
} else {
    echo sprintf("Longitude: %.10f deg (C ref: 25.2496966032)\n", $xx[0]);
    echo sprintf("Latitude:  %.10f deg (C ref: -1.2604133397)\n", $xx[1]);
    echo sprintf("Distance:  %.15f AU (C ref: 4.623909779522016)\n", $xx[2]);
}
echo "\n";

// Test 4: Io equatorial
echo "=== Test 4: Io equatorial geocentric ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL;
$retval = Swe::swe_calc($jd_ut, 9501, $iflag, $xx, $serr);
if ($retval < 0) {
    echo "Error: $serr\n";
} else {
    echo sprintf("RA:   %.10f deg (C ref: 23.8599928368)\n", $xx[0]);
    echo sprintf("Dec:  %.10f deg (C ref: 8.5928625014)\n", $xx[1]);
    echo sprintf("Dist: %.15f AU\n", $xx[2]);
}
echo "\n";

// Test 5: Io J2000
echo "=== Test 5: Io J2000 (no precession) ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000;
$retval = Swe::swe_calc($jd_ut, 9501, $iflag, $xx, $serr);
if ($retval < 0) {
    echo "Error: $serr\n";
} else {
    echo sprintf("Longitude: %.10f deg (C ref: 25.2492033036)\n", $xx[0]);
    echo sprintf("Latitude:  %.10f deg (C ref: -1.2606643530)\n", $xx[1]);
    echo sprintf("Distance:  %.15f AU\n", $xx[2]);
}
echo "\n";

// Test 6: Io barycentric
echo "=== Test 6: Io barycentric ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_BARYCTR;
$retval = Swe::swe_calc($jd_ut, 9501, $iflag, $xx, $serr);
if ($retval < 0) {
    echo "Error: $serr\n";
} else {
    echo sprintf("Longitude: %.10f deg (C ref: 36.2975609614)\n", $xx[0]);
    echo sprintf("Latitude:  %.10f deg (C ref: -1.1726596078)\n", $xx[1]);
    echo sprintf("Distance:  %.15f AU (C ref: 4.960527354209367)\n", $xx[2]);
}
echo "\n";

// Test 7: Io XYZ
echo "=== Test 7: Io XYZ cartesian geocentric ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_XYZ;
$retval = Swe::swe_calc($jd_ut, 9501, $iflag, $xx, $serr);
if ($retval < 0) {
    echo "Error: $serr\n";
} else {
    echo sprintf("X: %.15f AU (C ref: 4.181259178325982)\n", $xx[0]);
    echo sprintf("Y: %.15f AU (C ref: 1.971593568218517)\n", $xx[1]);
    echo sprintf("Z: %.15f AU (C ref: -0.101730295449864)\n", $xx[2]);
}
echo "\n";
