<?php
/**
 * Debug Earth position in PHP
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
swe_set_ephe_path($ephePath);

$tjdEt = 2451545.0;

// What PHP returns for Earth with same flags
$earthFlags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 |
              Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR;

$xear = [];
$serr = '';
$ret = swe_calc($tjdEt, Constants::SE_EARTH, $earthFlags, $xear, $serr);

echo "PHP swe_calc SE_EARTH (BARYCTR|J2000|EQUATORIAL|XYZ):\n";
echo sprintf("  xear = [%.9f, %.9f, %.9f]\n\n", $xear[0], $xear[1], $xear[2]);

echo "C swetest64 reference:\n";
echo "  xear = [-0.184284294, 0.884779352, 0.383819005]\n\n";

echo "Difference:\n";
echo sprintf("  dX = %.9f AU = %.2f km\n", $xear[0] - (-0.184284294), ($xear[0] - (-0.184284294)) * 149597870.7);
echo sprintf("  dY = %.9f AU = %.2f km\n", $xear[1] - 0.884779352, ($xear[1] - 0.884779352) * 149597870.7);
echo sprintf("  dZ = %.9f AU = %.2f km\n", $xear[2] - 0.383819005, ($xear[2] - 0.383819005) * 149597870.7);
