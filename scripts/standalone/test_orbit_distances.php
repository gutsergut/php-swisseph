<?php
/**
 * Test script for swe_orbit_max_min_true_distance()
 * Verifies calculation of maximum, minimum and true orbital distances
 */

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

echo "=== swe_orbit_max_min_true_distance() Test ===\n\n";

// Set ephemeris path
$ephe_path = __DIR__ . '/../eph/ephe';
if (!is_dir($ephe_path)) {
    echo "ERROR: Ephemeris path not found: $ephe_path\n";
    echo "Please set the correct path to Swiss Ephemeris data files.\n";
    exit(1);
}
swe_set_ephe_path($ephe_path);

$jd_et = 2451545.0;  // J2000.0
$iflag = Constants::SEFLG_SWIEPH;

// Test planets
$planets = [
    Constants::SE_MERCURY => 'Mercury',
    Constants::SE_VENUS => 'Venus',
    Constants::SE_MARS => 'Mars',
    Constants::SE_JUPITER => 'Jupiter',
    Constants::SE_SATURN => 'Saturn',
    Constants::SE_URANUS => 'Uranus',
    Constants::SE_NEPTUNE => 'Neptune',
    Constants::SE_PLUTO => 'Pluto',
];

echo "Test 1: Geocentric Distances at J2000.0\n";
echo "=========================================\n\n";

foreach ($planets as $ipl => $name) {
    $dmax = 0.0;
    $dmin = 0.0;
    $dtrue = 0.0;
    $serr = null;

    $result = swe_orbit_max_min_true_distance(
        $jd_et,
        $ipl,
        $iflag,
        $dmax,
        $dmin,
        $dtrue,
        $serr
    );

    if ($result < 0) {
        echo sprintf("%-10s ERROR: %s\n", $name, $serr);
        continue;
    }

    printf("%-10s Min: %8.4f AU  Max: %8.4f AU  True: %8.4f AU  (Δ: %7.4f AU)\n",
        $name, $dmin, $dmax, $dtrue, $dmax - $dmin);
}

echo "\n";

// Test 2: Heliocentric Distances
echo "Test 2: Heliocentric Distances at J2000.0\n";
echo "==========================================\n\n";

$iflag_helio = Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR;

foreach ($planets as $ipl => $name) {
    $dmax = 0.0;
    $dmin = 0.0;
    $dtrue = 0.0;
    $serr = null;

    $result = swe_orbit_max_min_true_distance(
        $jd_et,
        $ipl,
        $iflag_helio,
        $dmax,
        $dmin,
        $dtrue,
        $serr
    );

    if ($result < 0) {
        echo sprintf("%-10s ERROR: %s\n", $name, $serr);
        continue;
    }

    printf("%-10s Min: %8.4f AU  Max: %8.4f AU  True: %8.4f AU  (Δ: %7.4f AU)\n",
        $name, $dmin, $dmax, $dtrue, $dmax - $dmin);
}

echo "\n";

// Test 3: Sun distance (should use Earth's orbital elements)
echo "Test 3: Sun Distance (geocentric)\n";
echo "==================================\n\n";

$dmax = 0.0;
$dmin = 0.0;
$dtrue = 0.0;
$serr = null;

$result = swe_orbit_max_min_true_distance(
    $jd_et,
    Constants::SE_SUN,
    $iflag,
    $dmax,
    $dmin,
    $dtrue,
    $serr
);

if ($result >= 0) {
    printf("Sun        Min: %8.4f AU  Max: %8.4f AU  True: %8.4f AU  (Δ: %7.4f AU)\n",
        $dmin, $dmax, $dtrue, $dmax - $dmin);
    echo "\nNote: For the Sun, min/max are Earth's perihelion/aphelion distances\n";
} else {
    echo "ERROR: " . $serr . "\n";
}

echo "\n";

// Test 4: Validation - check that true distance is between min and max
echo "Test 4: Validation (True Distance Between Min and Max)\n";
echo "========================================================\n\n";

$all_valid = true;
foreach ($planets as $ipl => $name) {
    $dmax = 0.0;
    $dmin = 0.0;
    $dtrue = 0.0;
    $serr = null;

    $result = swe_orbit_max_min_true_distance(
        $jd_et,
        $ipl,
        $iflag,
        $dmax,
        $dmin,
        $dtrue,
        $serr
    );

    if ($result < 0) {
        continue;
    }

    $is_valid = ($dtrue >= $dmin && $dtrue <= $dmax);
    $status = $is_valid ? "✓ PASS" : "✗ FAIL";

    if (!$is_valid) {
        $all_valid = false;
        printf("%s  %-10s True: %.4f not in range [%.4f, %.4f]\n",
            $status, $name, $dtrue, $dmin, $dmax);
    } else {
        printf("%s  %-10s True: %.4f is in range [%.4f, %.4f]\n",
            $status, $name, $dtrue, $dmin, $dmax);
    }
}

if ($all_valid) {
    echo "\n✓ All validations passed!\n";
} else {
    echo "\n✗ Some validations failed!\n";
}

echo "\n";

// Test 5: Distance variation over time
echo "Test 5: Mars Distance Variation Over 2 Years\n";
echo "=============================================\n\n";

echo "Date            True Dist (AU)  Status\n";
echo "--------------------------------------------\n";

$test_dates = [
    ['2000-01-01', 2451544.5],
    ['2000-07-01', 2451726.5],
    ['2001-01-01', 2451910.5],
    ['2001-07-01', 2452091.5],
    ['2002-01-01', 2452275.5],
];

foreach ($test_dates as [$date_str, $jd]) {
    $dmax = 0.0;
    $dmin = 0.0;
    $dtrue = 0.0;
    $serr = null;

    $result = swe_orbit_max_min_true_distance(
        $jd,
        Constants::SE_MARS,
        $iflag,
        $dmax,
        $dmin,
        $dtrue,
        $serr
    );

    if ($result >= 0) {
        $pct = ($dtrue - $dmin) / ($dmax - $dmin) * 100;
        printf("%-15s %8.4f        %5.1f%% from min\n",
            $date_str, $dtrue, $pct);
    }
}

echo "\n=== All tests completed ===\n";
