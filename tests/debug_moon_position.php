<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Initialize Swiss Ephemeris
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// JD for 2025-01-01 00:00:00 UT
$tjd_ut = 2460676.5;

echo "Getting Moon position at JD $tjd_ut (2025-01-01 00:00 UT)\n\n";

// Berlin coordinates
$lon = 13.41;
$lat = 52.52;
$alt = 0.0;

swe_set_topo($lon, $lat, $alt);

// Get topocentric equatorial position
$xx = [];
$serr = null;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR;
$ret = swe_calc_ut($tjd_ut, Constants::SE_MOON, $iflag, $xx, $serr);

if ($ret < 0) {
    die("Error: $serr\n");
}

printf("RA:  %.8f° = %dh %dm %.2fs\n",
    $xx[0],
    floor($xx[0] / 15),
    floor(($xx[0] / 15 - floor($xx[0] / 15)) * 60),
    (($xx[0] / 15 - floor($xx[0] / 15)) * 60 - floor(($xx[0] / 15 - floor($xx[0] / 15)) * 60)) * 60
);

printf("Dec: %.8f°\n", $xx[1]);
printf("Distance: %.6f AU\n", $xx[2]);

// Get sidereal time
$sidt = swe_sidtime($tjd_ut);
printf("\nSidereal time: %.8f hours = %.8f degrees\n", $sidt, $sidt * 15);

printf("\nFor Berlin (lon=%.2f°, lat=%.2f°):\n", $lon, $lat);
$armc = fmod($sidt * 15 + $lon + 360, 360);
printf("ARMC: %.8f°\n", $armc);

$md = fmod($xx[0] - $armc + 360, 360);
printf("Meridian distance: %.8f°\n", $md);

// Semi-diurnal arc
$sda = -tan(deg2rad($lat)) * tan(deg2rad($xx[1]));
if ($sda >= 1.0) {
    $sda = 10.0;
} elseif ($sda <= -1.0) {
    $sda = 180.0;
} else {
    $sda = rad2deg(acos($sda));
}
printf("Semi-diurnal arc: %.8f°\n", $sda);

$mdrise = fmod($sda + 360, 360);
printf("MD at rise: %.8f°\n", $mdrise);

$dmd = fmod($md - $mdrise + 360, 360);
printf("Delta MD: %.8f°\n", $dmd);

$tr_rough = $tjd_ut + $dmd / 360.0;
printf("\nRough estimate TR: %.8f\n", $tr_rough);

$dt_hours = ($tr_rough - floor($tr_rough)) * 24;
$dt_h = floor($dt_hours);
$dt_m = floor(($dt_hours - $dt_h) * 60);
$dt_s = (($dt_hours - $dt_h) * 60 - $dt_m) * 60;
printf("UT: %02d:%02d:%06.3f\n", $dt_h, $dt_m, $dt_s);
