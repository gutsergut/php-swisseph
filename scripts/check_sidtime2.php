<?php

/**
 * Check sidereal time for 2000-01-01 00:00 UT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Sidereal;
use Swisseph\Nutation;
use Swisseph\Obliquity;
use Swisseph\DeltaT;

// Test for 2000-01-01 00:00 UT
$jd_ut = 2451544.5; // 2000-01-01 00:00 UT
$jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;

echo "Date: 2000-01-01 00:00 UT\n";
echo "Julian Day (UT): $jd_ut\n";
echo "Julian Day (TT): $jd_tt\n";
echo "Delta T: " . DeltaT::deltaTSecondsFromJd($jd_ut) . " seconds\n\n";

// Get nutation and obliquity
[$nutLon, $nutObl] = Nutation::calcIau1980($jd_tt);
$eps_mean = Obliquity::calc($jd_tt);
$eps_true = $eps_mean + $nutObl;

echo "True obliquity: " . rad2deg($eps_true) . " degrees\n";
echo "Nutation in longitude: " . rad2deg($nutLon) . " degrees\n\n";

// Calculate sidereal time
$sidt = Sidereal::sidtime0($jd_ut, rad2deg($eps_true), rad2deg($nutLon));

echo "Sidereal time: $sidt hours\n";
echo "Sidereal time: " . ($sidt * 15.0) . " degrees\n\n";

echo "If expected value is 6.646... hours, this would match!\n";
