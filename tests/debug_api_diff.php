<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_et = 2451545.0;

// Test 1: Equatorial (should be identical)
echo "=== Equatorial coordinates ===\n";
$star1 = 'Sirius';
$xx1 = [];
$star2 = 'Sirius';
$xx2 = [];

swe_fixstar($star1, $tjd_et, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL, $xx1, $serr1);
swe_fixstar2($star2, $tjd_et, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL, $xx2, $serr2);

printf("Legacy:  RA=%.10f Dec=%.10f\n", $xx1[0], $xx1[1]);
printf("New:     RA=%.10f Dec=%.10f\n", $xx2[0], $xx2[1]);
printf("Diff:    %.15f\n\n", abs($xx1[0] - $xx2[0]) + abs($xx1[1] - $xx2[1]));

// Test 2: Ecliptic (shows difference)
echo "=== Ecliptic coordinates ===\n";
$star1 = 'Sirius';
$xx1 = [];
$star2 = 'Sirius';
$xx2 = [];

swe_fixstar($star1, $tjd_et, Constants::SEFLG_SWIEPH, $xx1, $serr1);
swe_fixstar2($star2, $tjd_et, Constants::SEFLG_SWIEPH, $xx2, $serr2);

printf("Legacy:  Lon=%.10f Lat=%.10f\n", $xx1[0], $xx1[1]);
printf("New:     Lon=%.10f Lat=%.10f\n", $xx2[0], $xx2[1]);
printf("Diff:    %.15f\n\n", abs($xx1[0] - $xx2[0]) + abs($xx1[1] - $xx2[1]));

// Test 3: XYZ (should show if transformation is issue)
echo "=== XYZ coordinates ===\n";
$star1 = 'Sirius';
$xx1 = [];
$star2 = 'Sirius';
$xx2 = [];

swe_fixstar($star1, $tjd_et, Constants::SEFLG_SWIEPH | Constants::SEFLG_XYZ, $xx1, $serr1);
swe_fixstar2($star2, $tjd_et, Constants::SEFLG_SWIEPH | Constants::SEFLG_XYZ, $xx2, $serr2);

printf("Legacy:  X=%.10f Y=%.10f Z=%.10f\n", $xx1[0], $xx1[1], $xx1[2]);
printf("New:     X=%.10f Y=%.10f Z=%.10f\n", $xx2[0], $xx2[1], $xx2[2]);
printf("Diff:    %.15f\n", sqrt(pow($xx1[0]-$xx2[0],2) + pow($xx1[1]-$xx2[1],2) + pow($xx1[2]-$xx2[2],2)));
