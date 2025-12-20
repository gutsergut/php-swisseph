<?php
/**
 * Debug script to check raw xcom values for CENTER_BODY
 */

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephFileReader;
use Swisseph\SwephFile\SwephConstants;

// Set ephemeris path
\swe_set_ephe_path('C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe');

$jd = 2451545.0; // J2000.0
$iflag = 0x102; // SEFLG_SWIEPH | SEFLG_SPEED

echo "=== PHP Raw CENTER_BODY Debug ===\n";
echo "JD: $jd\n\n";

// Step 1: Get Jupiter barycenter standard
echo "=== 1. Jupiter Barycenter (standard swe_calc) ===\n";
$xx_bary = [];
$serr = null;
$ret = \swe_calc($jd, 5, $iflag, $xx_bary, $serr);
if ($ret < 0) {
    echo "Error: $serr\n";
} else {
    printf("Longitude: %.15f deg\n", $xx_bary[0]);
    printf("Latitude:  %.15f deg\n", $xx_bary[1]);
    printf("Distance:  %.15f AU\n", $xx_bary[2]);
}
echo "\n";

// Step 2: Get Jupiter CENTER_BODY via flag
echo "=== 2. Jupiter CENTER_BODY (SEFLG_CENTER_BODY) ===\n";
$xx_cob = [];
$serr = null;
$ret = \swe_calc($jd, 5, $iflag | (1024*1024), $xx_cob, $serr);
if ($ret < 0) {
    echo "Error: $serr\n";
} else {
    printf("Longitude: %.15f deg\n", $xx_cob[0]);
    printf("Latitude:  %.15f deg\n", $xx_cob[1]);
    printf("Distance:  %.15f AU\n", $xx_cob[2]);
}
echo "\n";

// Step 3: Get 9599 directly
echo "=== 3. ipl=9599 directly ===\n";
$xx_9599 = [];
$serr = null;
$ret = \swe_calc($jd, 9599, $iflag, $xx_9599, $serr);
if ($ret < 0) {
    echo "Error: $serr\n";
} else {
    printf("Longitude: %.15f deg\n", $xx_9599[0]);
    printf("Latitude:  %.15f deg\n", $xx_9599[1]);
    printf("Distance:  %.15f AU\n", $xx_9599[2]);
}
echo "\n";

// Differences
echo "=== 4. Differences ===\n";
printf("Bary vs CENTER_BODY:\n");
printf("  Delta Lon: %.15f deg = %.6f arcsec\n",
    $xx_cob[0] - $xx_bary[0],
    ($xx_cob[0] - $xx_bary[0]) * 3600.0);
printf("  Delta Lat: %.15f deg = %.6f arcsec\n",
    $xx_cob[1] - $xx_bary[1],
    ($xx_cob[1] - $xx_bary[1]) * 3600.0);
printf("  Delta Dist: %.15f AU = %.3f km\n",
    $xx_cob[2] - $xx_bary[2],
    ($xx_cob[2] - $xx_bary[2]) * 149597870.7);
echo "\n";

printf("CENTER_BODY vs 9599:\n");
printf("  Delta Lon: %.15f deg = %.6f arcsec\n",
    $xx_9599[0] - $xx_cob[0],
    ($xx_9599[0] - $xx_cob[0]) * 3600.0);
printf("  Delta Lat: %.15f deg = %.6f arcsec\n",
    $xx_9599[1] - $xx_cob[1],
    ($xx_9599[1] - $xx_cob[1]) * 3600.0);
echo "\n";

// Step 5: Try reading raw Chebyshev data for 9599
echo "=== 5. Raw file read for 9599 ===\n";
$swed = SwedState::getInstance();
$xx_raw = array_fill(0, 6, 0.0);
$serr_raw = null;

// Read 9599 without Sun addition
// We need to call SwephCalculator with xsunb=null
$retc = \Swisseph\SwephFile\SwephCalculator::calculate(
    $jd,
    SwephConstants::SEI_ANYBODY,
    9599,  // iplAst
    SwephConstants::SEI_FILE_ANY_AST,
    $iflag,
    null,  // xsunb = null - NO helio->bary conversion!
    false, // NO_SAVE
    $xx_raw,
    $serr_raw
);

if ($retc < 0) {
    echo "Error: $serr_raw\n";
} else {
    printf("Raw 9599 (no xsunb):\n");
    printf("  X: %.15f AU\n", $xx_raw[0]);
    printf("  Y: %.15f AU\n", $xx_raw[1]);
    printf("  Z: %.15f AU\n", $xx_raw[2]);
    $dist = sqrt($xx_raw[0]*$xx_raw[0] + $xx_raw[1]*$xx_raw[1] + $xx_raw[2]*$xx_raw[2]);
    printf("  Distance: %.15f AU = %.3f km\n", $dist, $dist * 149597870.7);
}
echo "\n";

// Step 6: Compare with C reference
echo "=== 6. C Reference Comparison ===\n";
$c_bary_lon = 25.253057710339888;
$c_cob_lon = 25.253052600068287;

printf("C Jupiter Bary Lon:   %.15f deg\n", $c_bary_lon);
printf("PHP Jupiter Bary Lon: %.15f deg\n", $xx_bary[0]);
printf("C-PHP Bary delta: %.6f arcsec\n\n", ($xx_bary[0] - $c_bary_lon) * 3600.0);

printf("C Jupiter COB Lon:   %.15f deg\n", $c_cob_lon);
printf("PHP Jupiter COB Lon: %.15f deg\n", $xx_cob[0]);
printf("C-PHP COB delta: %.6f arcsec\n\n", ($xx_cob[0] - $c_cob_lon) * 3600.0);

$c_delta = ($c_cob_lon - $c_bary_lon) * 3600.0;
$php_delta = ($xx_cob[0] - $xx_bary[0]) * 3600.0;
printf("C delta (COB-Bary): %.6f arcsec\n", $c_delta);
printf("PHP delta (COB-Bary): %.6f arcsec\n", $php_delta);
printf("Ratio PHP/C: %.3f\n", abs($php_delta / $c_delta));

echo "\nDone.\n";
