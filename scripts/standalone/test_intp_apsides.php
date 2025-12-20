<?php
/**
 * Test script for SE_INTP_APOG (21) and SE_INTP_PERG (22)
 * Compare results with swetest64.exe reference
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe;
use Swisseph\Constants;

// Test date: 2024-01-01 12:00:00 UT (matches swetest64 -b1.1.2024 -ut12:00:00)
// JD = 2460311.0
$jd = 2460311.0;

// Reference values from swetest64.exe -p1cg -emos:
// Moon             161.9067363  11.8138327    0.002706332
// intp. Apogee     163.5930380   0.2102416    0.002706626
// intp. Perigee    315.6340796   0.2434253    0.002437538

echo "=== Test Interpolated Lunar Apsides ===\n";
echo "JD = $jd\n\n";

$iflag = Constants::SEFLG_SPEED;

// Test SE_INTP_APOG (21)
echo "--- SE_INTP_APOG (Interpolated Lunar Apogee) ---\n";
$xx = [];
$serr = null;
$ret = Swe::swe_calc($jd, Constants::SE_INTP_APOG, $iflag, $xx, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Longitude: %.6f°\n", $xx[0]);
    printf("Latitude:  %.6f°\n", $xx[1]);
    printf("Distance:  %.6f AU\n", $xx[2]);
    printf("Speed:     %.6f°/day\n", $xx[3]);
}

echo "\n";

// Test SE_INTP_PERG (22)
echo "--- SE_INTP_PERG (Interpolated Lunar Perigee) ---\n";
$xx = [];
$serr = null;
$ret = Swe::swe_calc($jd, Constants::SE_INTP_PERG, $iflag, $xx, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Longitude: %.6f°\n", $xx[0]);
    printf("Latitude:  %.6f°\n", $xx[1]);
    printf("Distance:  %.6f AU\n", $xx[2]);
    printf("Speed:     %.6f°/day\n", $xx[3]);
}

echo "\n=== Done ===\n";
