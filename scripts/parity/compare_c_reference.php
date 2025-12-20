<?php
/**
 * Compare PHP results with C reference values
 */
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

$jd = 2451545.0; // J2000.0

echo "=== Compare PHP vs C Reference Values ===\n\n";
echo "JD = $jd (J2000.0)\n\n";

// Test 1: J2000 + TRUEPOS + NONUT - ecliptic polar
$flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx = [];
$serr = '';
$ret = swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

echo "1) Moon Ecliptic polar (SEFLG_J2000 | SEFLG_TRUEPOS | SEFLG_NONUT):\n";
echo sprintf("   lon  = %.15f deg\n", $xx[0]);
echo sprintf("   lat  = %.15f deg\n", $xx[1]);
echo sprintf("   dist = %.15f AU\n", $xx[2]);
echo sprintf("   retc = %d\n\n", $ret);

// C reference
$c_lon = 223.318926841255433;
$c_lat = 5.170869334505696;
$c_dist = 0.002690202992216;

$diff_lon = ($xx[0] - $c_lon) * 3600;
$diff_lat = ($xx[1] - $c_lat) * 3600;
$diff_dist = ($xx[2] - $c_dist);

echo "   C ref lon  = $c_lon\n";
echo "   C ref lat  = $c_lat\n";
echo "   C ref dist = $c_dist\n";
echo sprintf("   DIFF: lon = %+.6f arcsec, lat = %+.6f arcsec\n\n", $diff_lon, $diff_lat);

// Test 2: J2000 + TRUEPOS + NONUT + XYZ - ecliptic XYZ
$flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_XYZ;
$xx = [];
$ret = swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

echo "2) Moon Ecliptic XYZ (+ SEFLG_XYZ):\n";
echo sprintf("   X = %.15f AU\n", $xx[0]);
echo sprintf("   Y = %.15f AU\n", $xx[1]);
echo sprintf("   Z = %.15f AU\n", $xx[2]);
echo sprintf("   retc = %d\n\n", $ret);

// C reference
$c_x = -0.001949281567213;
$c_y = -0.001838126136532;
$c_z = 0.000242457866962;

$km = 149597870.7; // AU to km
$diff_x = ($xx[0] - $c_x) * $km;
$diff_y = ($xx[1] - $c_y) * $km;
$diff_z = ($xx[2] - $c_z) * $km;

echo "   C ref X = $c_x\n";
echo "   C ref Y = $c_y\n";
echo "   C ref Z = $c_z\n";
echo sprintf("   DIFF: dX = %+.3f km, dY = %+.3f km, dZ = %+.3f km\n\n", $diff_x, $diff_y, $diff_z);

// Test 3: J2000 + TRUEPOS + NONUT + EQUATORIAL - equatorial polar
$flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_EQUATORIAL;
$xx = [];
$ret = swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

echo "3) Moon Equatorial polar (+ SEFLG_EQUATORIAL):\n";
echo sprintf("   RA  = %.15f deg\n", $xx[0]);
echo sprintf("   Dec = %.15f deg\n", $xx[1]);
echo sprintf("   dist = %.15f AU\n", $xx[2]);
echo sprintf("   retc = %d\n\n", $ret);

// C reference
$c_ra  = 222.447303193212804;
$c_dec = -10.900181181103722;

$diff_ra  = ($xx[0] - $c_ra) * 3600;
$diff_dec = ($xx[1] - $c_dec) * 3600;

echo "   C ref RA  = $c_ra\n";
echo "   C ref Dec = $c_dec\n";
echo sprintf("   DIFF: RA = %+.6f arcsec, Dec = %+.6f arcsec\n\n", $diff_ra, $diff_dec);

// Test 4: J2000 + TRUEPOS + NONUT + EQUATORIAL + XYZ
$flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ;
$xx = [];
$ret = swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

echo "4) Moon Equatorial XYZ (+ SEFLG_EQUATORIAL | SEFLG_XYZ):\n";
echo sprintf("   X = %.15f AU\n", $xx[0]);
echo sprintf("   Y = %.15f AU\n", $xx[1]);
echo sprintf("   Z = %.15f AU\n", $xx[2]);
echo sprintf("   retc = %d\n\n", $ret);

// C reference
$c_x = -0.001949281567213;
$c_y = -0.001782892062430;
$c_z = -0.000508713480045;

$diff_x = ($xx[0] - $c_x) * $km;
$diff_y = ($xx[1] - $c_y) * $km;
$diff_z = ($xx[2] - $c_z) * $km;

echo "   C ref X = $c_x\n";
echo "   C ref Y = $c_y\n";
echo "   C ref Z = $c_z\n";
echo sprintf("   DIFF: dX = %+.3f km, dY = %+.3f km, dZ = %+.3f km\n\n", $diff_x, $diff_y, $diff_z);

// Test 5: Apparent
$flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$xx = [];
$ret = swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

echo "5) Moon Apparent (default, SEFLG_SPEED):\n";
echo sprintf("   lon  = %.15f deg\n", $xx[0]);
echo sprintf("   lat  = %.15f deg\n", $xx[1]);
echo sprintf("   dist = %.15f AU\n", $xx[2]);
echo sprintf("   lon' = %.15f deg/day\n", $xx[3]);
echo sprintf("   lat' = %.15f deg/day\n", $xx[4]);
echo sprintf("   dist'= %.15f AU/day\n", $xx[5]);
echo sprintf("   retc = %d\n\n", $ret);

// C reference
$c_lon = 223.314870314333888;
$c_lat = 5.170872114975060;

$diff_lon = ($xx[0] - $c_lon) * 3600;
$diff_lat = ($xx[1] - $c_lat) * 3600;

echo "   C ref lon  = $c_lon\n";
echo "   C ref lat  = $c_lat\n";
echo sprintf("   DIFF: lon = %+.6f arcsec, lat = %+.6f arcsec\n\n", $diff_lon, $diff_lat);

// Test 6: Sun
$flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx = [];
$ret = swe_calc($jd, Constants::SE_SUN, $flags, $xx, $serr);

echo "6) Sun ecliptic (SEFLG_J2000 | SEFLG_TRUEPOS | SEFLG_NONUT):\n";
echo sprintf("   lon  = %.15f deg\n", $xx[0]);
echo sprintf("   lat  = %.15f deg\n", $xx[1]);
echo sprintf("   dist = %.15f AU\n", $xx[2]);
echo sprintf("   retc = %d\n\n", $ret);

// C reference
$c_lon = 280.377824819672696;
$c_lat = 0.000227380831927;
$c_dist = 0.983327677767626;

$diff_lon = ($xx[0] - $c_lon) * 3600;
$diff_lat = ($xx[1] - $c_lat) * 3600;

echo "   C ref lon  = $c_lon\n";
echo "   C ref lat  = $c_lat\n";
echo sprintf("   DIFF: lon = %+.6f arcsec, lat = %+.6f arcsec\n\n", $diff_lon, $diff_lat);
