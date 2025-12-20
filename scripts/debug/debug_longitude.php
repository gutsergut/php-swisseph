<?php
/**
 * Debug: Compare ecliptic longitude directly
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== Ecliptic Longitude Comparison ===\n\n";

$jd = 2451545.0;

// J2000 + TRUEPOS + NONUT = raw ecliptic coordinates
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

echo "PHP Moon ecliptic polar (J2000, TRUEPOS, NONUT):\n";
echo sprintf("  Longitude = %.10f°\n", $xx[0]);
echo sprintf("  Latitude  = %.10f°\n", $xx[1]);
echo sprintf("  Distance  = %.15f AU\n", $xx[2]);

echo "\nswetest reference:\n";
echo "  Longitude = 223.3278077°\n";

$diffLon = ($xx[0] - 223.3278077) * 3600;
echo "\nLongitude difference: {$diffLon}\" (~" . abs($diffLon) . " arcsec)\n";

// Now compute longitude from XYZ manually
$flagsXYZ = $flags | Constants::SEFLG_XYZ;
$xxXYZ = [];
swe_calc($jd, Constants::SE_MOON, $flagsXYZ, $xxXYZ, $serr);

$lon_from_xyz = atan2($xxXYZ[1], $xxXYZ[0]) * 180 / M_PI;
if ($lon_from_xyz < 0) $lon_from_xyz += 360;

echo "\nLongitude from XYZ (atan2): " . sprintf("%.10f°", $lon_from_xyz) . "\n";
echo "Difference from polar: " . (($lon_from_xyz - $xx[0]) * 3600) . " arcsec\n";
