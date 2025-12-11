<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Sidereal;
use Swisseph\Nutation;
use Swisseph\Obliquity;
use Swisseph\DeltaT;
use Swisseph\SwephFile\SwedState;

/**
 * Test complete swe_sidtime0 implementation
 */

echo "Testing swe_sidtime0 implementation\n";
echo "====================================\n\n";

// Set ephemeris path
$swisseph = SwedState::getInstance();
$swisseph->setEphePath(__DIR__ . '/../../eph/ephe');

// Test 1: 2000-01-01 00:00 UT (standard epoch reference)
echo "Test 1: 2000-01-01 00:00 UT\n";
$jd_ut = 2451544.5; // 2000-01-01 00:00 UT
$jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;

// Get obliquity and nutation
[$nutLon, $nutObl] = Nutation::calcIau1980($jd_tt);
$eps_mean = Obliquity::calc($jd_tt);

// Convert to degrees
$eps_deg = rad2deg($eps_mean + $nutObl); // true obliquity = mean + nutation in obliquity
$nut_deg = rad2deg($nutLon);

echo "JD (UT): $jd_ut\n";
echo "JD (TT): $jd_tt\n";
echo "Obliquity: $eps_deg degrees\n";
echo "Nutation: $nut_deg degrees\n";

// Calculate sidereal time
$sidt = Sidereal::sidtime0($jd_ut, $eps_deg, $nut_deg);

echo "Sidereal time: $sidt hours\n";
echo "Sidereal time: " . ($sidt * 15.0) . " degrees\n";
echo "Expected: ~6.664 hours (~99.96 degrees)\n";
$diff_hours = abs($sidt - 6.664283392);
$diff_arcsec = $diff_hours * 3600.0 * 15.0; // Convert hour difference to arcseconds
echo "Difference: $diff_arcsec arcseconds\n";

if ($diff_arcsec < 1.0) {
    echo "✓ PASS: Difference < 1 arcsecond\n";
} else {
    echo "✗ FAIL: Difference >= 1 arcsecond\n";
}

echo "\n";

// Test 2: Different date (2025-01-01 00:00 UT)
echo "Test 2: 2025-01-01 00:00 UT\n";
$jd_ut2 = 2460676.5;
$jd_tt2 = $jd_ut2 + DeltaT::deltaTSecondsFromJd($jd_ut2) / 86400.0;

// Get obliquity and nutation
[$nutLon2, $nutObl2] = Nutation::calcIau1980($jd_tt2);
$eps2 = Obliquity::trueObliquityRadFromJdTT($jd_tt2);

// Convert to degrees
$eps_deg2 = rad2deg($eps2);
$nut_deg2 = rad2deg($nutLon2);

echo "JD (UT): $jd_ut2\n";
echo "JD (TT): $jd_tt2\n";
echo "Obliquity: $eps_deg2 degrees\n";
echo "Nutation: $nut_deg2 degrees\n";

// Calculate sidereal time
$sidt2 = Sidereal::sidtime0($jd_ut2, $eps_deg2, $nut_deg2);

echo "Sidereal time: $sidt2 hours\n";
echo "Sidereal time: " . ($sidt2 * 15.0) . " degrees\n";

// Get reference from swetest
echo "\n";
echo "To verify with swetest, run:\n";
echo "cd \"C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\с-swisseph\\swisseph\\windows\\programs\"\n";
echo ".\\swetest64.exe -b'1.1.2025' -px\n";

echo "\n";

// Test 3: Long-term model (date before 1850)
echo "Test 3: Long-term model (date before 1850)\n";
// Test date: 1800-01-01 00:00 UT
$jd_ut3 = 2378496.5;
$jd_tt3 = $jd_ut3 + DeltaT::deltaTSecondsFromJd($jd_ut3) / 86400.0;

// Get obliquity and nutation
[$nutLon3, $nutObl3] = Nutation::calcIau1980($jd_tt3);
$eps3 = Obliquity::trueObliquityRadFromJdTT($jd_tt3);

// Convert to degrees
$eps_deg3 = rad2deg($eps3);
$nut_deg3 = rad2deg($nutLon3);

echo "JD (UT): $jd_ut3\n";
echo "Date: 1800-01-01 (before 1850, should use long-term model)\n";
echo "Obliquity: $eps_deg3 degrees\n";
echo "Nutation: $nut_deg3 degrees\n";

// Calculate sidereal time
$sidt3 = Sidereal::sidtime0($jd_ut3, $eps_deg3, $nut_deg3);

echo "Sidereal time: $sidt3 hours\n";
echo "Sidereal time: " . ($sidt3 * 15.0) . " degrees\n";

echo "\n";
echo "To verify with swetest, run:\n";
echo ".\\swetest64.exe -b'1.1.1800' -px\n";

echo "\n====================================\n";
echo "All tests completed!\n";
