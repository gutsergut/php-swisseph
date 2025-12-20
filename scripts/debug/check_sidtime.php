<?php

/**
 * Check sidereal time calculation against swetest reference
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Sidereal;
use Swisseph\Nutation;
use Swisseph\Obliquity;
use Swisseph\DeltaT;

// Test for J2000.0 epoch
$jd_tt = Constants::J2000; // 2451545.0
$jd_ut = $jd_tt - DeltaT::deltaTSecondsFromJd($jd_tt) / 86400.0;

echo "Julian Day (TT): $jd_tt\n";
echo "Julian Day (UT): $jd_ut\n";
echo "Delta T: " . DeltaT::deltaTSecondsFromJd($jd_tt) . " seconds\n\n";

// Get nutation and obliquity
[$nutLon, $nutObl] = Nutation::calcIau1980($jd_tt);
$eps_mean = Obliquity::calc($jd_tt);
$eps_true = $eps_mean + $nutObl;

echo "Mean obliquity: " . rad2deg($eps_mean) . " degrees\n";
echo "Nutation in obliquity: " . rad2deg($nutObl) . " arcsec\n";
echo "True obliquity: " . rad2deg($eps_true) . " degrees\n";
echo "Nutation in longitude: " . rad2deg($nutLon) . " degrees\n\n";

// Calculate sidereal time
$sidt = Sidereal::sidtime0($jd_ut, rad2deg($eps_true), rad2deg($nutLon));

echo "Sidereal time (sidtime0): $sidt hours\n";
echo "Sidereal time (degrees): " . ($sidt * 15.0) . " degrees\n\n";

// Also try with gmstHoursFromJdUt for comparison
$gmst_meeus = Sidereal::gmstHoursFromJdUt($jd_ut);
echo "GMST (Meeus formula): $gmst_meeus hours\n";
echo "GMST (degrees): " . ($gmst_meeus * 15.0) . " degrees\n\n";

echo "For comparison with swetest, the date is:\n";
echo "2000-01-01 12:00:00 TT (or 2000-01-01 11:58:56 UT)\n";
