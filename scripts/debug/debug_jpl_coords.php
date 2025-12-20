<?php
require 'vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

// Test JPL directly
$jpl = JplEphemeris::getInstance();
JplEphemeris::resetInstance();
$jpl = JplEphemeris::getInstance();

$ss = [];
$serr = '';
$ret = $jpl->open($ss, 'de200.eph', 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/', $serr);

if ($ret !== JplConstants::OK) {
    die("Failed to open: $serr\n");
}

// Get Mercury barycentric (SSB)
$mercBary = [];
$ret = $jpl->pleph(2451545.0, JplConstants::J_MERCURY, JplConstants::J_SBARY, $mercBary, $serr);
printf("Mercury Bary (equatorial J2000):\n");
printf("  x=%.12f, y=%.12f, z=%.12f AU\n", $mercBary[0], $mercBary[1], $mercBary[2]);

// Get Earth barycentric (SSB)
$earthBary = [];
$ret = $jpl->pleph(2451545.0, JplConstants::J_EARTH, JplConstants::J_SBARY, $earthBary, $serr);
printf("Earth Bary (equatorial J2000):\n");
printf("  x=%.12f, y=%.12f, z=%.12f AU\n", $earthBary[0], $earthBary[1], $earthBary[2]);

// Mercury geocentric = Mercury_bary - Earth_bary
$mercGeo = [
    $mercBary[0] - $earthBary[0],
    $mercBary[1] - $earthBary[1],
    $mercBary[2] - $earthBary[2],
];
printf("Mercury Geo (equatorial J2000):\n");
printf("  x=%.12f, y=%.12f, z=%.12f AU\n", $mercGeo[0], $mercGeo[1], $mercGeo[2]);

// Convert to ecliptic J2000
// Equatorial→Ecliptic is the INVERSE of Ecliptic→Equatorial
// If Ecl→Eq: y_eq = y_ecl*cos - z_ecl*sin, z_eq = y_ecl*sin + z_ecl*cos
// Then Eq→Ecl: y_ecl = y_eq*cos + z_eq*sin, z_ecl = -y_eq*sin + z_eq*cos
$eps = deg2rad(23.4392911);
$cosEps = cos($eps);
$sinEps = sin($eps);

$mercGeoEcl = [
    $mercGeo[0],
    $mercGeo[1] * $cosEps + $mercGeo[2] * $sinEps,
    -$mercGeo[1] * $sinEps + $mercGeo[2] * $cosEps,
];
printf("Mercury Geo (ecliptic J2000):\n");
printf("  x=%.12f, y=%.12f, z=%.12f AU\n", $mercGeoEcl[0], $mercGeoEcl[1], $mercGeoEcl[2]);

// Convert to spherical (lon, lat, dist)
$r = sqrt($mercGeoEcl[0]**2 + $mercGeoEcl[1]**2 + $mercGeoEcl[2]**2);
$lon = rad2deg(atan2($mercGeoEcl[1], $mercGeoEcl[0]));
if ($lon < 0) $lon += 360;
$lat = rad2deg(asin($mercGeoEcl[2] / $r));

printf("\nMercury Geo spherical (J2000 ecliptic):\n");
printf("  lon=%.6f°, lat=%.6f°, dist=%.6f AU\n", $lon, $lat, $r);

echo "\nReference from swetest (apparent, includes precession+nutation):\n";
echo "  lon=271.889277°, lat=-0.994°, dist=1.415469 AU\n";
echo "\nNote: The difference is because swetest outputs APPARENT position (with precession, nutation, aberration),\n";
echo "      while our calculation is J2000 geometric (no corrections).\n";
