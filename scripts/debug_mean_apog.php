<?php
/**
 * Debug script for Mean Apogee
 */

declare(strict_types=1);

use Swisseph\Constants;
use Swisseph\Swe\Functions\NodesApsidesFunctions;

require_once __DIR__ . '/../vendor/autoload.php';

$ephePath = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe';
swe_set_ephe_path($ephePath);

$jd = 2460000.5;
$iflag = Constants::SEFLG_SPEED;

echo "=== Debug Mean Apogee ===\n\n";
echo "JD: $jd\n\n";

// Call nodAps directly
$xnasc = array_fill(0, 6, 0.0);
$xndsc = array_fill(0, 6, 0.0);
$xperi = array_fill(0, 6, 0.0);
$xaphe = array_fill(0, 6, 0.0);
$serr = null;

$ret = NodesApsidesFunctions::nodAps(
    $jd,
    Constants::SE_MOON,
    $iflag,
    Constants::SE_NODBIT_MEAN,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "Error: $serr\n";
} else {
    echo "xnasc (ascending node):\n";
    echo sprintf("  Longitude: %.7f°\n", $xnasc[0]);

    echo "\nxndsc (descending node):\n";
    echo sprintf("  Longitude: %.7f°\n", $xndsc[0]);

    echo "\nxperi (perigee):\n";
    echo sprintf("  Longitude: %.7f°\n", $xperi[0]);
    echo sprintf("  Latitude:  %.7f°\n", $xperi[1]);
    echo sprintf("  Distance:  %.10f AU\n", $xperi[2]);

    echo "\nxaphe (apogee):\n";
    echo sprintf("  Longitude: %.7f° (expected 125.3147095°)\n", $xaphe[0]);
    echo sprintf("  Latitude:  %.7f° (expected 5.1423508°)\n", $xaphe[1]);
    echo sprintf("  Distance:  %.10f AU (expected 0.002710625)\n", $xaphe[2]);

    echo "\nPerigee + 180 = " . ($xperi[0] + 180.0) . "° (modulo 360: " . fmod($xperi[0] + 180.0, 360.0) . "°)\n";
}

echo "\nDone.\n";
