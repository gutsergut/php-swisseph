<?php
/**
 * Debug MoshierMoon::intpApsides() directly
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Domain\Moshier\MoshierMoon;
use Swisseph\Constants;

// Test date: 2025-01-15 12:00:00 UTC -> TT
// JD_UT = 2460691.0, delta_t ≈ 68.99 sec
$jd_ut = 2460691.0;
$delta_t = 68.99 / 86400.0;  // convert seconds to days
$jd_tt = $jd_ut + $delta_t;

echo "=== Debug MoshierMoon::intpApsides() ===\n";
echo "JD_UT = $jd_ut\n";
echo "JD_TT = $jd_tt\n\n";

// Test moshmoon2() directly to see raw ecliptic arcsec
echo "--- Testing moshmoon2() directly (raw ecliptic arcsec) ---\n";
$moon1 = new MoshierMoon();
$pol2 = array_fill(0, 3, 0.0);
$moon1->moshmoon2($jd_tt, $pol2);
printf("Moon via moshmoon2: Lon=%.2f arcsec = %.6f deg\n", $pol2[0], $pol2[0] * (180.0 / M_PI));
printf("                    Lat=%.2f arcsec = %.6f deg\n", $pol2[1], $pol2[1] * (180.0 / M_PI));
printf("                    Dist=%.9f AU\n", $pol2[2]);

echo "\n--- Testing regular Moon via moshmoon (equatorial J2000) ---\n";
$xx = array_fill(0, 6, 0.0);
$serr = null;
$moon1->moshmoon($jd_tt, true, $xx, $serr, null);
printf("Moon via moshmoon: X=%.6f, Y=%.6f, Z=%.6f AU\n", $xx[0], $xx[1], $xx[2]);

echo "\n--- SE_INTP_APOG (SEI=4) with fresh instance ---\n";
$moon2 = new MoshierMoon();
$pol = array_fill(0, 3, 0.0);
$ret = $moon2->intpApsides($jd_tt, $pol, 4);  // SEI_INTP_APOG = 4

echo "Return: $ret\n";
printf("pol[0] (longitude): %.6f (raw) = %.6f deg (if radians)\n", $pol[0], $pol[0] * (180.0 / M_PI));
printf("pol[1] (latitude):  %.6f (raw) = %.6f deg (if radians)\n", $pol[1], $pol[1] * (180.0 / M_PI));
printf("pol[2] (distance):  %.9f AU\n", $pol[2]);

echo "\n--- Expected (from swetest64.exe) ---\n";
echo "intp. Apogee: 203.0503367°, speed 0.2351489°/day\n";
echo "Moon: 134.6882718°\n";
