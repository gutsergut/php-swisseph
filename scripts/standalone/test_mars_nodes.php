<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2451545.0; // J2000.0
$ipl = Constants::SE_MARS;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

echo "=== Mars Nodes Comparison ===\n\n";

// Mean nodes
$xnascMean = [];
$xndscMean = [];
$xperiMean = [];
$xapheMean = [];
$serr = '';

$ret = swe_nod_aps($tjd, $ipl, $iflag, Constants::SE_NODBIT_MEAN, $xnascMean, $xndscMean, $xperiMean, $xapheMean, $serr);
echo "Mean Ascending Node:\n";
printf("  Longitude: %.6f°\n", $xnascMean[0]);
printf("  Latitude: %.6f°\n", $xnascMean[1]);
printf("  Distance: %.6f AU\n\n", $xnascMean[2]);

// Osculating nodes
$xnascOscu = [];
$xndscOscu = [];
$xperiOscu = [];
$xapheOscu = [];

$ret = swe_nod_aps($tjd, $ipl, $iflag, Constants::SE_NODBIT_OSCU, $xnascOscu, $xndscOscu, $xperiOscu, $xapheOscu, $serr);
echo "Osculating Ascending Node:\n";
printf("  Longitude: %.6f°\n", $xnascOscu[0]);
printf("  Latitude: %.6f°\n", $xnascOscu[1]);
printf("  Distance: %.6f AU\n\n", $xnascOscu[2]);

echo "Difference: " . abs($xnascOscu[0] - $xnascMean[0]) . " degrees\n";

// Get C reference with swetest
echo "\n=== Get C reference ===\n";
echo "C Mean nodes: swetest64.exe -b1.1.2000 -p4 -fPl -eswe -n1 +m\n";
echo "C Oscu nodes: swetest64.exe -b1.1.2000 -p4 -fPl -eswe -n1 +N\n";
