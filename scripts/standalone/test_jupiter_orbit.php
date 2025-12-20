#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0; // J2000.0
$ipl = Constants::SE_JUPITER;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_SPEED;

echo "Jupiter orbital positions around J2000.0\n";
echo "=========================================\n\n";

// Scan around J2000 to find where Z crosses zero
// Jupiter period ~ 4333 days, so scan +/- half period
for ($days = -2200; $days <= 2200; $days += 100) {
    $tjd = $jd + $days;
    $xx = [];
    $serr = null;
    $rc = swe_calc($tjd, $ipl, $iflag, $xx, $serr);

    if ($rc >= 0) {
        // Calculate ecliptic longitude from X,Y,Z
        $lon = atan2($xx[1], $xx[0]);
        if ($lon < 0) $lon += 2 * M_PI;
        $lon_deg = rad2deg($lon);

        printf("Day %+4d: Z=%.6f, Longitude=%.2f°\n", $days, $xx[2], $lon_deg);
    }
}

echo "\n\nNow let's check osculating node:\n";
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;
$ret = swe_nod_aps($jd, $ipl, $iflag, Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);

echo "Ascending node longitude: " . rad2deg($xnasc[0]) . "°\n";
echo "Ascending node distance: " . $xnasc[2] . " AU\n";
echo "Inclination: " . rad2deg($xnasc[3]) . "°\n";
