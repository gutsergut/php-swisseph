<?php
/**
 * Тест аянамши (sidereal offset)
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd = 2451545.0; // J2000.0

// Test default ayanamsa
$ayanamsa = swe_get_ayanamsa($jd);

if ($ayanamsa < 0.0 || $ayanamsa > 360.0) {
    fwrite(STDERR, "Ayanamsa out of range: $ayanamsa\n");
    exit(1);
}

// At J2000, Lahiri ayanamsa should be around 23-24 degrees
if ($ayanamsa < 20.0 || $ayanamsa > 27.0) {
    fwrite(STDERR, "Ayanamsa value suspicious for J2000: $ayanamsa\n");
    exit(2);
}

// Test with explicit sidmode
swe_set_sid_mode(Constants::SE_SIDM_LAHIRI, 0.0, 0.0);
$ayanamsa_lahiri = swe_get_ayanamsa($jd);

// Lahiri at J2000 should be ~23-24 degrees
if ($ayanamsa_lahiri < 20.0 || $ayanamsa_lahiri > 27.0) {
    fwrite(STDERR, "Lahiri ayanamsa out of expected range\n");
    exit(3);
}

// Test different ayanamsas give different values
swe_set_sid_mode(Constants::SE_SIDM_FAGAN_BRADLEY, 0.0, 0.0);
$ayanamsa_fagan = swe_get_ayanamsa($jd);

if (abs($ayanamsa_fagan - $ayanamsa_lahiri) < 0.1) {
    fwrite(STDERR, "Fagan-Bradley too similar to Lahiri\n");
    exit(4);
}

// Test ayanamsa_ut variant
$ayanamsa_ut = swe_get_ayanamsa_ut(2451545.0);
if (abs($ayanamsa_ut - $ayanamsa_fagan) > 0.001) {
    fwrite(STDERR, "ayanamsa_ut differs from ayanamsa for same moment\n");
    exit(5);
}

// Test sidereal mode setting (just verify it doesn't crash)
swe_set_sid_mode(Constants::SE_SIDM_LAHIRI, 0.0, 0.0);

$xx_trop = [];
$serr = null;
swe_calc($jd, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx_trop, $serr);

if (!isset($xx_trop[0])) {
    fwrite(STDERR, "Tropical calculation failed\n");
    exit(6);
}

echo "OK\n";
