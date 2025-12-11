<?php

/**
 * Test swe_get_orbital_elements() against swetest reference values
 *
 * Usage: php tests/OrbitalElementsTest.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Initialize ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
if (is_dir($ephePath)) {
    swe_set_ephe_path($ephePath);
} else {
    echo "WARNING: Ephemeris path not found: $ephePath\n";
}

function testVenusOrbit(): bool
{
    echo "Testing Venus orbital elements at J2000.0...\n";

    $jd_et = 2451545.0; // J2000.0
    $ipl = Constants::SE_VENUS;
    $iflag = Constants::SEFLG_SWIEPH;

    $dret = [];
    $serr = null;

    $ret = swe_get_orbital_elements($jd_et, $ipl, $iflag, $dret, $serr);

    if ($ret < 0) {
        echo "ERROR: " . ($serr ?? "Unknown error") . "\n";
        return false;
    }

    echo "\nVenus orbital elements:\n";
    echo sprintf("  Semimajor axis:     %.9f AU\n", $dret[0]);
    echo sprintf("  Eccentricity:       %.9f\n", $dret[1]);
    echo sprintf("  Inclination:        %.6f°\n", $dret[2]);
    echo sprintf("  Ascending node:     %.6f°\n", $dret[3]);
    echo sprintf("  Arg of perihelion:  %.6f°\n", $dret[4]);
    echo sprintf("  Lon of perihelion:  %.6f°\n", $dret[5]);
    echo sprintf("  Mean anomaly:       %.6f°\n", $dret[6]);
    echo sprintf("  True anomaly:       %.6f°\n", $dret[7]);
    echo sprintf("  Eccentric anomaly:  %.6f°\n", $dret[8]);
    echo sprintf("  Mean longitude:     %.6f°\n", $dret[9]);
    echo sprintf("  Sidereal period:    %.6f years\n", $dret[10]);
    echo sprintf("  Daily motion:       %.9f °/day\n", $dret[11]);
    echo sprintf("  Tropical period:    %.6f years\n", $dret[12]);
    echo sprintf("  Synodic period:     %.1f days\n", $dret[13]);
    echo sprintf("  Perihelion JD:      %.6f\n", $dret[14]);
    echo sprintf("  Perihelion dist:    %.9f AU\n", $dret[15]);
    echo sprintf("  Aphelion dist:      %.9f AU\n", $dret[16]);

    // Known astronomical values for Venus
    $expected = [
        0 => 0.7233,   // ~0.723 AU
        1 => 0.0067,   // eccentricity ~0.0067
        2 => 3.39,     // inclination ~3.39°
        10 => 0.6152,  // ~0.615 sidereal years
        11 => 1.602,   // ~1.6 °/day
    ];

    $tolerance = [
        0 => 0.01,   // semimajor axis: 0.01 AU tolerance
        1 => 0.001,  // eccentricity: 0.001 tolerance
        2 => 0.1,    // inclination: 0.1° tolerance
        10 => 0.01,  // period: 0.01 year tolerance
        11 => 0.01,  // daily motion: 0.01 °/day tolerance
    ];

    $allOk = true;
    foreach ($expected as $idx => $expValue) {
        $diff = abs($dret[$idx] - $expValue);
        $tol = $tolerance[$idx];
        $ok = $diff <= $tol;
        $allOk = $allOk && $ok;

        $status = $ok ? "✓" : "✗";
        echo sprintf(
            "  %s [%d]: %.9f (expected %.9f, diff %.9f, tol %.9f)\n",
            $status,
            $idx,
            $dret[$idx],
            $expValue,
            $diff,
            $tol
        );
    }

    return $allOk;
}

function testMarsOrbit(): bool
{
    echo "\n\nTesting Mars orbital elements at J2000.0...\n";

    $jd_et = 2451545.0; // J2000.0
    $ipl = Constants::SE_MARS;
    $iflag = Constants::SEFLG_SWIEPH;

    $dret = [];
    $serr = null;

    $ret = swe_get_orbital_elements($jd_et, $ipl, $iflag, $dret, $serr);

    if ($ret < 0) {
        echo "ERROR: " . ($serr ?? "Unknown error") . "\n";
        return false;
    }

    echo "\nMars orbital elements:\n";
    echo sprintf("  Semimajor axis:     %.9f AU\n", $dret[0]);
    echo sprintf("  Eccentricity:       %.9f\n", $dret[1]);
    echo sprintf("  Inclination:        %.6f°\n", $dret[2]);
    echo sprintf("  Sidereal period:    %.6f years\n", $dret[10]);
    echo sprintf("  Daily motion:       %.9f °/day\n", $dret[11]);
    echo sprintf("  Perihelion dist:    %.9f AU\n", $dret[15]);
    echo sprintf("  Aphelion dist:      %.9f AU\n", $dret[16]);

    // Known astronomical values for Mars
    $expected = [
        0 => 1.523679,  // ~1.524 AU
        1 => 0.0934,    // eccentricity ~0.0934
        2 => 1.85,      // inclination ~1.85°
        10 => 1.881,    // ~1.88 sidereal years
        15 => 1.381,    // perihelion ~1.381 AU
        16 => 1.666,    // aphelion ~1.666 AU
    ];

    $tolerance = [
        0 => 0.01,   // semimajor axis
        1 => 0.001,  // eccentricity
        2 => 0.1,    // inclination
        10 => 0.01,  // period
        15 => 0.01,  // perihelion distance
        16 => 0.01,  // aphelion distance
    ];

    $allOk = true;
    foreach ($expected as $idx => $expValue) {
        $diff = abs($dret[$idx] - $expValue);
        $tol = $tolerance[$idx];
        $ok = $diff <= $tol;
        $allOk = $allOk && $ok;

        $status = $ok ? "✓" : "✗";
        echo sprintf(
            "  %s [%d]: %.9f (expected %.9f, diff %.9f, tol %.9f)\n",
            $status,
            $idx,
            $dret[$idx],
            $expValue,
            $diff,
            $tol
        );
    }

    return $allOk;
}

function testJupiterOrbit(): bool
{
    echo "\n\nTesting Jupiter orbital elements at J2000.0...\n";

    $jd_et = 2451545.0; // J2000.0
    $ipl = Constants::SE_JUPITER;
    $iflag = Constants::SEFLG_SWIEPH;

    $dret = [];
    $serr = null;

    $ret = swe_get_orbital_elements($jd_et, $ipl, $iflag, $dret, $serr);

    if ($ret < 0) {
        echo "ERROR: " . ($serr ?? "Unknown error") . "\n";
        return false;
    }

    echo "\nJupiter orbital elements:\n";
    echo sprintf("  Semimajor axis:     %.9f AU\n", $dret[0]);
    echo sprintf("  Eccentricity:       %.9f\n", $dret[1]);
    echo sprintf("  Inclination:        %.6f°\n", $dret[2]);
    echo sprintf("  Sidereal period:    %.6f years\n", $dret[10]);
    echo sprintf("  Synodic period:     %.1f days\n", $dret[13]);

    // Known astronomical values for Jupiter
    $expected = [
        0 => 5.2044,   // ~5.204 AU
        1 => 0.0489,   // eccentricity ~0.0489
        2 => 1.304,    // inclination ~1.304°
        10 => 11.862,  // ~11.86 sidereal years
        13 => 398.9,   // ~399 days synodic period
    ];

    $tolerance = [
        0 => 0.01,   // semimajor axis
        1 => 0.001,  // eccentricity
        2 => 0.1,    // inclination
        10 => 0.1,   // period
        13 => 1.0,   // synodic period
    ];

    $allOk = true;
    foreach ($expected as $idx => $expValue) {
        $diff = abs($dret[$idx] - $expValue);
        $tol = $tolerance[$idx];
        $ok = $diff <= $tol;
        $allOk = $allOk && $ok;

        $status = $ok ? "✓" : "✗";
        echo sprintf(
            "  %s [%d]: %.9f (expected %.9f, diff %.9f, tol %.9f)\n",
            $status,
            $idx,
            $dret[$idx],
            $expValue,
            $diff,
            $tol
        );
    }

    return $allOk;
}

// Run all tests
$results = [
    'Venus' => testVenusOrbit(),
    'Mars' => testMarsOrbit(),
    'Jupiter' => testJupiterOrbit(),
];

echo "\n\n" . str_repeat("=", 70) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 70) . "\n";

$totalTests = count($results);
$passedTests = count(array_filter($results));

foreach ($results as $name => $passed) {
    $status = $passed ? "✓ PASS" : "✗ FAIL";
    echo sprintf("%-20s: %s\n", $name, $status);
}

echo str_repeat("=", 70) . "\n";
echo sprintf("Total: %d/%d tests passed\n", $passedTests, $totalTests);

if ($passedTests === $totalTests) {
    echo "✓✓✓ ALL TESTS PASSED! ✓✓✓\n";
    exit(0);
} else {
    echo "✗✗✗ SOME TESTS FAILED ✗✗✗\n";
    exit(1);
}
