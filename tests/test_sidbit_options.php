<?php
/**
 * Test sidereal mode options (SE_SIDBIT_*)
 * Tests different calculation methods for ayanamsha
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Test Sidereal Mode Options (SE_SIDBIT_*) ===\n\n";

$jd_tt = 2451545.0; // J2000.0
$jd_ut = $jd_tt - (swe_deltat($jd_tt) / 86400.0);

echo "Test Date: J2000.0 (JD $jd_tt TT)\n";
echo str_repeat("=", 70) . "\n\n";

// Test different ayanamsha modes with different options
$test_modes = [
    'Fagan/Bradley' => Constants::SE_SIDM_FAGAN_BRADLEY,
    'Lahiri' => Constants::SE_SIDM_LAHIRI,
    'Raman' => Constants::SE_SIDM_RAMAN,
];

// Test different option combinations
$test_options = [
    'Default (ecliptic t0)' => 0,
    'ECL_DATE (ecliptic of date)' => Constants::SE_SIDBIT_ECL_DATE,
    'NO_PREC_OFFSET' => Constants::SE_SIDBIT_NO_PREC_OFFSET,
    'ECL_DATE + NO_PREC_OFFSET' => Constants::SE_SIDBIT_ECL_DATE | Constants::SE_SIDBIT_NO_PREC_OFFSET,
];

foreach ($test_modes as $mode_name => $mode) {
    echo "Mode: $mode_name\n";
    echo str_repeat("-", 70) . "\n";

    foreach ($test_options as $opt_name => $opts) {
        // Set sidereal mode with options
        swe_set_sid_mode($mode | $opts, 0, 0);

        // Get ayanamsha
        $ayan = swe_get_ayanamsa_ut($jd_ut);

        echo sprintf("  %-30s: %10.6f°\n", $opt_name, $ayan);
    }

    echo "\n";
}

// Get C reference values using swetest64
echo str_repeat("=", 70) . "\n";
echo "C Reference (swetest64.exe):\n";
echo str_repeat("=", 70) . "\n";
echo "\nTo get C reference, run:\n";
echo "  swetest64 -bj2451545 -sid0 -fPl -p0  # Fagan/Bradley\n";
echo "  swetest64 -bj2451545 -sid1 -fPl -p0  # Lahiri\n";
echo "  swetest64 -bj2451545 -sid3 -fPl -p0  # Raman\n";
echo "\nNote: swetest64 uses default calculation method.\n";
echo "      SE_SIDBIT_* options change the algorithm but results should be similar.\n";
