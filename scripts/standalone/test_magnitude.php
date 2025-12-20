<?php
// Compare Magnitude calculation PHP vs swetest64
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$dgeo = [13.4, 52.5, 100.0];
$test_jd = 2452004.66233;

echo "=== Magnitude Comparison at JD $test_jd ===\n\n";

// Set topocentric
swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);

// Get phenomena with different flags
$attr = array_fill(0, 20, 0.0);
$serr = '';

// Test 1: TOPOCTR + EQUATORIAL (as used in Magnitude function)
$iflag1 = Constants::SEFLG_TOPOCTR | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SWIEPH | Constants::SEFLG_NONUT | Constants::SEFLG_TRUEPOS;
$rc1 = swe_pheno_ut($test_jd, Constants::SE_VENUS, $iflag1, $attr, $serr);
printf("TOPOCTR+EQUATORIAL+NONUT+TRUEPOS:\n");
printf("  Magnitude (attr[4]): %.6f\n", $attr[4]);
printf("  Phase angle: %.6f\n", $attr[0]);
printf("  Phase: %.6f\n", $attr[1]);
printf("  Elong: %.6f\n", $attr[2]);
printf("  Diameter: %.6f\"\n", $attr[3]);
printf("  Error: %s\n\n", $serr);

// Test 2: TOPOCTR only (standard)
$iflag2 = Constants::SEFLG_TOPOCTR | Constants::SEFLG_SWIEPH;
$attr2 = array_fill(0, 20, 0.0);
$serr2 = '';
$rc2 = swe_pheno_ut($test_jd, Constants::SE_VENUS, $iflag2, $attr2, $serr2);
printf("TOPOCTR only:\n");
printf("  Magnitude (attr[4]): %.6f\n", $attr2[4]);
printf("  Error: %s\n\n", $serr2);

// Test 3: Geocentric for reference
$iflag3 = Constants::SEFLG_SWIEPH;
$attr3 = array_fill(0, 20, 0.0);
$serr3 = '';
$rc3 = swe_pheno_ut($test_jd, Constants::SE_VENUS, $iflag3, $attr3, $serr3);
printf("Geocentric:\n");
printf("  Magnitude (attr[4]): %.6f\n", $attr3[4]);
printf("  Error: %s\n\n", $serr3);

// Get position for additional info
$xx = array_fill(0, 6, 0.0);
swe_calc_ut($test_jd, Constants::SE_VENUS, Constants::SEFLG_TOPOCTR | Constants::SEFLG_SWIEPH, $xx, $serr);
printf("Venus topocentric position:\n");
printf("  Lon: %.6f°\n", $xx[0]);
printf("  Lat: %.6f°\n", $xx[1]);
printf("  Dist: %.6f AU\n\n", $xx[2]);

// Compare with swetest64 expected value
echo "Expected from C test: -4.032151 (approximately)\n";
echo "PHP calculated: " . number_format($attr[4], 6) . "\n";
echo "Difference: " . number_format(abs($attr[4] - (-4.032151)), 6) . "\n";
