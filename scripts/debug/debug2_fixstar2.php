<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_et = 2451545.0;

// Test 1
echo "=== Test 1: Ecliptic ===\n";
$star1 = 'Sirius';
$xx1 = array_fill(0, 6, 0.0);
$serr1 = '';
$iflag1 = Constants::SEFLG_SWIEPH;
$ret1 = swe_fixstar2($star1, $tjd_et, $iflag1, $xx1, $serr1);
echo "Return: $ret1\n";
echo "Star: $star1\n";
echo "Error: '$serr1'\n\n";

// Test 2
echo "=== Test 2: Equatorial ===\n";
$star2 = 'Sirius';
$xx2 = array_fill(0, 6, 0.0);
$serr2 = '';
$iflag2 = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL;
$ret2 = swe_fixstar2($star2, $tjd_et, $iflag2, $xx2, $serr2);
echo "Return: $ret2\n";
echo "Star: $star2\n";
echo "Error: '$serr2'\n";

if ($ret2 < 0) {
    echo "FAILED\n";
} else {
    echo "SUCCESS\n";
    printf("RA: %.6f\n", $xx2[0]);
    printf("Dec: %.6f\n", $xx2[1]);
}
