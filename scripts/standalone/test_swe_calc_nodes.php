<?php
/**
 * Test swe_calc() for lunar nodes and apsides
 *
 * Reference values from swetest64 for JD=2460000.5 (25 Feb 2023 00:00 UT):
 *   True Node:   35.8566277° speed  0.0044299°/d
 *   Mean Apogee: 125.3147095° lat 5.1423508° speed 0.1120522°/d
 *   Oscu Apogee: 123.3606289° lat 5.0870555° speed -2.6305447°/d
 */

declare(strict_types=1);

use Swisseph\Constants;

require_once __DIR__ . '/../vendor/autoload.php';

$ephePath = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe';
swe_set_ephe_path($ephePath);

$jd = 2460000.5;

echo "=== Testing swe_calc() for Lunar Nodes and Apsides ===\n\n";
echo "JD: $jd (25 Feb 2023 00:00 UT)\n\n";

// Test Mean Node (already working)
$xx = [];
$serr = null;
$ret = swe_calc($jd, Constants::SE_MEAN_NODE, Constants::SEFLG_SPEED, $xx, $serr);
echo "SE_MEAN_NODE (10):\n";
if ($ret < 0) {
    echo "  Error: $serr\n";
} else {
    echo sprintf("  Longitude: %.7f°\n", $xx[0]);
    echo sprintf("  Speed:     %.7f°/d\n", $xx[3]);
}

// Test True Node
$xx = [];
$ret = swe_calc($jd, Constants::SE_TRUE_NODE, Constants::SEFLG_SPEED, $xx, $serr);
echo "\nSE_TRUE_NODE (11): expected lon=35.8566277° speed=0.0044299°/d\n";
if ($ret < 0) {
    echo "  Error: $serr\n";
} else {
    echo sprintf("  Longitude: %.7f° (diff: %.7f°)\n", $xx[0], $xx[0] - 35.8566277);
    echo sprintf("  Speed:     %.7f°/d (diff: %.7f)\n", $xx[3], $xx[3] - 0.0044299);
}

// Test Mean Apogee
$xx = [];
$ret = swe_calc($jd, Constants::SE_MEAN_APOG, Constants::SEFLG_SPEED, $xx, $serr);
echo "\nSE_MEAN_APOG (12): expected lon=125.3147095° lat=5.1423508° speed=0.1120522°/d\n";
if ($ret < 0) {
    echo "  Error: $serr\n";
} else {
    echo sprintf("  Longitude: %.7f° (diff: %.7f°)\n", $xx[0], $xx[0] - 125.3147095);
    echo sprintf("  Latitude:  %.7f° (diff: %.7f°)\n", $xx[1], $xx[1] - 5.1423508);
    echo sprintf("  Speed:     %.7f°/d (diff: %.7f)\n", $xx[3], $xx[3] - 0.1120522);
}

// Test Oscu Apogee
$xx = [];
$ret = swe_calc($jd, Constants::SE_OSCU_APOG, Constants::SEFLG_SPEED, $xx, $serr);
echo "\nSE_OSCU_APOG (13): expected lon=123.3606289° lat=5.0870555° speed=-2.6305447°/d\n";
if ($ret < 0) {
    echo "  Error: $serr\n";
} else {
    echo sprintf("  Longitude: %.7f° (diff: %.7f°)\n", $xx[0], $xx[0] - 123.3606289);
    echo sprintf("  Latitude:  %.7f° (diff: %.7f°)\n", $xx[1], $xx[1] - 5.0870555);
    echo sprintf("  Speed:     %.7f°/d (diff: %.7f)\n", $xx[3], $xx[3] - (-2.6305447));
}

echo "\nDone.\n";
