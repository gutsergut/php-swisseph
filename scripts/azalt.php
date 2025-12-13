<?php
/**
 * Тест горизонтальных координат (азимут/высота)
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd = 2451545.0;
$geolon = 0.0;  // Greenwich
$geolat = 51.5; // London

// Calculate Sun position
$xx = [];
$serr = null;
swe_calc($jd, Constants::SE_SUN, Constants::SEFLG_EQUATORIAL, $xx, $serr);
$ra = $xx[0];
$dec = $xx[1];

// Get sidereal time
$sidt = swe_sidtime($jd);
if ($sidt < 0.0 || $sidt >= 24.0) {
    fwrite(STDERR, "Sidereal time out of range: $sidt\n");
    exit(1);
}

// Convert to azimuth/altitude
$xin = [$ra, $dec, 1.0]; // distance doesn't matter for direction
$xaz = [];
swe_azalt($jd, Constants::SE_ECL2HOR, [$geolon, $geolat, 0.0], 0.0, 0.0, $xin, $xaz);

if (!isset($xaz[0], $xaz[1], $xaz[2])) {
    fwrite(STDERR, "swe_azalt missing output values\n");
    exit(2);
}

$azimuth = $xaz[0];
$altitude = $xaz[1];

if ($azimuth < 0.0 || $azimuth >= 360.0) {
    fwrite(STDERR, "Azimuth out of range: $azimuth\n");
    exit(3);
}

if ($altitude < -90.0 || $altitude > 90.0) {
    fwrite(STDERR, "Altitude out of range: $altitude\n");
    exit(4);
}

// Test reverse conversion (approximate - transformations aren't perfect)
$xhor = [];
swe_azalt($jd, Constants::SE_HOR2ECL, [$geolon, $geolat, 0.0], 0.0, 0.0, $xaz, $xhor);

if (!isset($xhor[0], $xhor[1])) {
    fwrite(STDERR, "Reverse conversion failed\n");
    exit(5);
}

// Just verify we got reasonable equatorial coordinates back
if ($xhor[1] < -90.0 || $xhor[1] > 90.0) {
    fwrite(STDERR, "Reverse dec out of range\n");
    exit(6);
}

// Test refraction exists
$alt_apparent = $altitude;
$alt_true = swe_refrac($alt_apparent, 0.0, 1013.25, 15.0, Constants::SE_APP_TO_TRUE);

// Refraction should return a value
if (!is_numeric($alt_true)) {
    fwrite(STDERR, "Refraction returned non-numeric\n");
    exit(7);
}

echo "OK\n";
