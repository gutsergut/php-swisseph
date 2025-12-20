#!/usr/bin/env php
<?php
/**
 * Test swe_calc with SEFLG_SWIEPH routing to SwephCalculator
 *
 * This tests that PlanetsFunctions correctly routes SEFLG_SWIEPH
 * requests to the Swiss Ephemeris file reader.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\Swe\Functions\PlanetsFunctions;

echo "Testing swe_calc with SEFLG_SWIEPH for Jupiter at J2000.0\n\n";

// Set ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
if (!is_dir($ephePath)) {
    die("ERROR: Ephemeris directory not found at $ephePath\n");
}

SwedState::getInstance()->setEphePath($ephePath);
echo "Ephemeris path: " . SwedState::getInstance()->ephepath . "\n\n";

// J2000.0 = 2451545.0 TT
$jd_tt = 2451545.0;
$ipl = Constants::SE_JUPITER;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;

$xx = [];
$serr = null;

echo "Calling PlanetsFunctions::calc($jd_tt, SE_JUPITER, SEFLG_SWIEPH|SEFLG_J2000|SEFLG_XYZ, ...)\n\n";

$retc = PlanetsFunctions::calc($jd_tt, $ipl, $iflag, $xx, $serr);

if ($retc === Constants::SE_ERR) {
    echo "ERROR: " . ($serr ?? 'Unknown error') . "\n";
    exit(1);
}

echo "Success!\n";
echo "Barycentric J2000 ecliptic cartesian coordinates:\n";
printf("  x = %.10f AU\n", $xx[0]);
printf("  y = %.10f AU\n", $xx[1]);
printf("  z = %.10f AU\n", $xx[2]);

// Compare with direct SwephCalculator result (should be identical)
echo "\n--- For comparison, direct SwephCalculator ---\n";
passthru("php " . __DIR__ . "/test_sweph_jupiter.php");
