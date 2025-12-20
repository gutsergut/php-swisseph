<?php
/**
 * Debug: Detailed XYZ comparison and conversion analysis
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== XYZ to RA/Dec Conversion Analysis ===\n\n";

$jd = 2451545.0;

// Get PHP XYZ
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_XYZ;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

$php_x = $xx[0];
$php_y = $xx[1];
$php_z = $xx[2];

// From swetest output
$swe_x = -0.001949007;
$swe_y = -0.001838438;
$swe_z = 0.000242453;

echo "PHP XYZ:     X={$php_x}, Y={$php_y}, Z={$php_z}\n";
echo "swetest XYZ: X={$swe_x}, Y={$swe_y}, Z={$swe_z}\n\n";

$diff_x = $php_x - $swe_x;
$diff_y = $php_y - $swe_y;
$diff_z = $php_z - $swe_z;

echo "Differences in XYZ:\n";
echo "dX = " . sprintf("%.15f", $diff_x) . " AU\n";
echo "dY = " . sprintf("%.15f", $diff_y) . " AU\n";
echo "dZ = " . sprintf("%.15f", $diff_z) . " AU\n";

// Convert to km
$AU_KM = 149597870.7;
echo "\nIn km:\n";
echo "dX = " . sprintf("%.3f", $diff_x * $AU_KM) . " km\n";
echo "dY = " . sprintf("%.3f", $diff_y * $AU_KM) . " km\n";
echo "dZ = " . sprintf("%.3f", $diff_z * $AU_KM) . " km\n";

// Distance to Moon
$r_php = sqrt($php_x*$php_x + $php_y*$php_y + $php_z*$php_z);
$r_swe = sqrt($swe_x*$swe_x + $swe_y*$swe_y + $swe_z*$swe_z);

echo "\nDistance: PHP = " . ($r_php * $AU_KM) . " km, swetest = " . ($r_swe * $AU_KM) . " km\n";
echo "Difference = " . (($r_php - $r_swe) * $AU_KM) . " km\n";

// Now compute RA/Dec from XYZ manually
// For equatorial: RA = atan2(Y, X), Dec = atan(Z / sqrt(X^2 + Y^2))
echo "\n=== Manual RA/Dec from XYZ ===\n";

// PHP XYZ is in ECLIPTIC (J2000)
// But we asked for EQUATORIAL... let me check what frame XYZ is in

// Actually swetest with -j2000 -true -nonut gives ECLIPTIC coordinates
// Even with fPXYZ it should be ecliptic XYZ

// Let me recompute - for ecliptic XYZ:
// lon = atan2(Y, X)
// lat = atan(Z / sqrt(X^2 + Y^2))

$lon_php = atan2($php_y, $php_x) * 180 / M_PI;
$lat_php = atan2($php_z, sqrt($php_x*$php_x + $php_y*$php_y)) * 180 / M_PI;
if ($lon_php < 0) $lon_php += 360;

$lon_swe = atan2($swe_y, $swe_x) * 180 / M_PI;
$lat_swe = atan2($swe_z, sqrt($swe_x*$swe_x + $swe_y*$swe_y)) * 180 / M_PI;
if ($lon_swe < 0) $lon_swe += 360;

echo "\nFrom PHP XYZ: lon = {$lon_php}°, lat = {$lat_php}°\n";
echo "From swetest XYZ: lon = {$lon_swe}°, lat = {$lat_swe}°\n";
echo "Longitude diff = " . (($lon_php - $lon_swe) * 3600) . " arcsec\n";
echo "Latitude diff = " . (($lat_php - $lat_swe) * 3600) . " arcsec\n";

// Now get PHP ecliptic polar directly
echo "\n=== PHP Ecliptic Polar (no XYZ) ===\n";
$flags2 = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx2 = [];
swe_calc($jd, Constants::SE_MOON, $flags2, $xx2, $serr);
echo "PHP Moon polar: lon={$xx2[0]}°, lat={$xx2[1]}°, dist={$xx2[2]} AU\n";

// swetest polar
echo "swetest lon at J2000 from earlier: 223.3278077° (from debug_minimal.php)\n";
echo "Difference: " . (($xx2[0] - 223.3278077) * 3600) . " arcsec\n";

// Now the big question: what frame are PHP XYZ?
// With J2000+TRUEPOS+NONUT, XYZ should be J2000 ecliptic rectangular
// But lon from XYZ = {$lon_php}, while polar lon = {$xx2[0]}

echo "\n=== Frame check ===\n";
echo "Lon from XYZ (atan2): {$lon_php}°\n";
echo "Lon from polar: {$xx2[0]}°\n";
echo "Difference: " . (($lon_php - $xx2[0]) * 3600) . " arcsec\n";

// They should match!
if (abs($lon_php - $xx2[0]) > 0.001) {
    echo "\n*** WARNING: XYZ and polar don't match - possible bug! ***\n";
}
