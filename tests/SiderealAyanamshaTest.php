<?php

/**
 * Test sidereal ayanamsha calculations for various modes
 */

require_once __DIR__ . '/bootstrap.php';

use Swisseph\Constants;

echo "=== Sidereal Ayanamsha Test ===\n\n";

// Test date: 2000-01-01 12:00 TT (J2000.0)
$jd_tt = 2451545.0;
echo "Test JD(TT): " . $jd_tt . " (J2000.0)\n\n";

// Test all major sidereal modes
$modes = [
    Constants::SE_SIDM_FAGAN_BRADLEY => 'Fagan/Bradley',
    Constants::SE_SIDM_LAHIRI => 'Lahiri',
    Constants::SE_SIDM_DELUCE => 'De Luce',
    Constants::SE_SIDM_RAMAN => 'Raman',
    Constants::SE_SIDM_USHASHASHI => 'Usha/Shashi',
    Constants::SE_SIDM_KRISHNAMURTI => 'Krishnamurti',
    Constants::SE_SIDM_DJWHAL_KHUL => 'Djwhal Khul',
    Constants::SE_SIDM_YUKTESHWAR => 'Yukteshwar',
    Constants::SE_SIDM_JN_BHASIN => 'J.N. Bhasin',
    Constants::SE_SIDM_BABYLONIAN_KUGLER1 => 'Babylonian/Kugler 1',
    Constants::SE_SIDM_J2000 => 'J2000',
    Constants::SE_SIDM_J1900 => 'J1900',
    Constants::SE_SIDM_B1950 => 'B1950',
    Constants::SE_SIDM_SURYASIDDHANTA => 'Suryasiddhanta',
    Constants::SE_SIDM_ARYABHATA_MSUN => 'Aryabhata',
    Constants::SE_SIDM_LAHIRI_1940 => 'Lahiri 1940',
    Constants::SE_SIDM_LAHIRI_VP285 => 'Lahiri VP285',
];

foreach ($modes as $mode => $name) {
    swe_set_sid_mode($mode, 0, 0);

    $daya = null;
    $serr = null;
    $ret = swe_get_ayanamsa_ex($jd_tt, 0, $daya, $serr);

    if ($ret < 0) {
        echo sprintf("%-35s: ERROR - %s\n", $name, $serr);
    } else {
        $deg = floor($daya);
        $min = floor(($daya - $deg) * 60);
        $sec = (($daya - $deg) * 60 - $min) * 60;
        echo sprintf("%-35s: %3d°%02d'%05.2f\" (%12.8f°)\n", $name, $deg, $min, $sec, $daya);
    }
}

// Test user-defined mode
echo "\n--- User-defined mode test ---\n";
swe_set_sid_mode(Constants::SE_SIDM_USER, 2451545.0, 25.5);
$daya = null;
$serr = null;
swe_get_ayanamsa_ex($jd_tt, 0, $daya, $serr);
echo "User-defined (t0=J2000, ayan_t0=25.5°): " . sprintf("%.8f°", $daya) . "\n";

// Test at different epoch (2025-10-26)
echo "\n--- Test at 2025-10-26 00:00 UT ---\n";
$jd_ut_2025 = 2460680.5;
$dt = \Swisseph\DeltaT::deltaTSecondsFromJd($jd_ut_2025) / 86400.0;
$jd_tt_2025 = $jd_ut_2025 + $dt;

$test_modes = [
    Constants::SE_SIDM_FAGAN_BRADLEY,
    Constants::SE_SIDM_LAHIRI,
    Constants::SE_SIDM_J2000,
];

foreach ($test_modes as $mode) {
    swe_set_sid_mode($mode, 0, 0);
    $daya = null;
    swe_get_ayanamsa_ex($jd_tt_2025, 0, $daya);
    $name = swe_get_ayanamsa_name($mode);
    echo sprintf("%-25s: %12.8f°\n", $name, $daya);
}

echo "\n=== Test completed ===\n";
