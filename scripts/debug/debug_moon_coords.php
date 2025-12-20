<?php
/**
 * Debug: Compare raw Moon position from PHP vs C
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

// Set ephemeris path
$ephePath = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
if ($ephePath) {
    swe_set_ephe_path($ephePath);
}

// JD for 2020-01-01 12:00:00 UT
$deltaT = 69.184 / 86400.0;
$jd_ut = 2458850.0;
$jd_tt = $jd_ut + $deltaT;

echo "JD_TT = $jd_tt\n\n";

// Get Moon in different coordinate systems
echo "=== Moon from PlanetsFunctions::calc ===\n\n";

// 1. Default (ecliptic lon/lat)
$xx = [];
$serr = null;
$ret = PlanetsFunctions::calc($jd_tt, Constants::SE_MOON, Constants::SEFLG_SPEED, $xx, $serr);
echo "1. Default (ecliptic polar):\n";
printf("   lon=%.10f°, lat=%.10f°, dist=%.10f AU\n", $xx[0], $xx[1], $xx[2]);
printf("   speed=%.10f°/d\n\n", $xx[3]);

// 2. J2000 ecliptic polar
$xx = [];
$ret = PlanetsFunctions::calc($jd_tt, Constants::SE_MOON, Constants::SEFLG_SPEED | Constants::SEFLG_J2000 | Constants::SEFLG_NONUT, $xx, $serr);
echo "2. J2000 ecliptic (polar):\n";
printf("   lon=%.10f°, lat=%.10f°, dist=%.10f AU\n", $xx[0], $xx[1], $xx[2]);
printf("   speed=%.10f°/d\n\n", $xx[3]);

// 3. J2000 equatorial polar
$xx = [];
$ret = PlanetsFunctions::calc($jd_tt, Constants::SE_MOON, Constants::SEFLG_SPEED | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_NONUT, $xx, $serr);
echo "3. J2000 equatorial (polar):\n";
printf("   RA=%.10f°, Dec=%.10f°, dist=%.10f AU\n", $xx[0], $xx[1], $xx[2]);
printf("   speed=%.10f°/d\n\n", $xx[3]);

// 4. J2000 equatorial XYZ
$xx = [];
$ret = PlanetsFunctions::calc($jd_tt, Constants::SE_MOON, Constants::SEFLG_SPEED | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_NONUT, $xx, $serr);
echo "4. J2000 equatorial XYZ:\n";
printf("   x=%.15f, y=%.15f, z=%.15f AU\n", $xx[0], $xx[1], $xx[2]);
printf("   vx=%.15f, vy=%.15f, vz=%.15f AU/d\n\n", $xx[3], $xx[4], $xx[5]);

// Convert XYZ to polar for verification
$r = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
$lon_xyz = rad2deg(atan2($xx[1], $xx[0]));
$lat_xyz = rad2deg(asin($xx[2]/$r));
echo "   (converted to polar: lon=%.10f°, lat=%.10f°, r=%.10f AU)\n\n";
printf("   lon=%f°, lat=%f°, r=%f AU\n\n", $lon_xyz, $lat_xyz, $r);

// 5. J2000 ecliptic XYZ (for comparison)
$xx = [];
$ret = PlanetsFunctions::calc($jd_tt, Constants::SE_MOON, Constants::SEFLG_SPEED | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_NONUT, $xx, $serr);
echo "5. J2000 ecliptic XYZ:\n";
printf("   x=%.15f, y=%.15f, z=%.15f AU\n", $xx[0], $xx[1], $xx[2]);
printf("   vx=%.15f, vy=%.15f, vz=%.15f AU/d\n\n", $xx[3], $xx[4], $xx[5]);

// Convert to polar
$r = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
$lon_xyz = rad2deg(atan2($xx[1], $xx[0]));
$lat_xyz = rad2deg(asin($xx[2]/$r));
printf("   (converted to polar: lon=%f°, lat=%f°, r=%f AU)\n\n", $lon_xyz, $lat_xyz, $r);

echo "Done.\n";
