<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Berlin coordinates
$lon = 13.41;
$lat = 52.52;
$alt = 0.0;

swe_set_topo($lon, $lat, $alt);

// Calculate Moon position at expected rise time
// Reference: 2025-01-01 08:58:14.8 UT
$jd_expected_rise = swe_julday(2025, 1, 1, 8 + 58/60 + 14.8/3600, Constants::SE_GREG_CAL);

echo "Expected rise time: 2025-01-01 08:58:14.8 UT\n";
echo "JD: " . number_format($jd_expected_rise, 6) . "\n\n";

// Calculate Moon equatorial position (topocentric)
$serr = '';
$xx_topo = [];
$rc = swe_calc($jd_expected_rise, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR, $xx_topo, $serr);

if ($rc < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Moon topocentric equatorial coordinates at expected rise time:\n";
echo "  RA: " . $xx_topo[0] . "°\n";
echo "  Dec: " . $xx_topo[1] . "°\n";
echo "  Distance: " . $xx_topo[2] . " AU\n\n";

// Calculate Moon horizontal position
$xaz = [];
swe_azalt($jd_expected_rise, Constants::SE_EQU2HOR, [$lon, $lat, $alt], 1013.25, 15.0, $xx_topo, $xaz);

echo "Moon horizontal coordinates:\n";
echo "  Azimuth: " . $xaz[0] . "°\n";
echo "  True altitude: " . $xaz[1] . "°\n";
echo "  Apparent altitude: " . $xaz[2] . "°\n\n";

// Calculate Moon apparent radius
$diameter_m = 3475000.0; // Moon diameter in meters
$distance_m = $xx_topo[2] * 149597870700.0; // AU to meters
$apparent_radius_deg = rad2deg(asin($diameter_m / 2.0 / $distance_m));

echo "Moon apparent radius: " . $apparent_radius_deg . "° (" . ($apparent_radius_deg * 3600) . " arcsec)\n";
echo "Moon upper edge altitude: " . ($xaz[2] + $apparent_radius_deg) . "°\n\n";

echo "Expected: Moon upper edge at horizon (altitude ≈ 0°)\n";
