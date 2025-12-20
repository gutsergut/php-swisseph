<?php
/**
 * Test script for LunarOsculatingCalculator
 * Reference values from swetest64 for JD=2460000.5 (25 Feb 2023 00:00 UT):
 *   True Node:   35.8566277° speed  0.0044299°/d
 *   Oscu Apogee: 123.3606289° lat 5.0870555° dist 0.002729960 AU speed -2.6305447°/d
 */

declare(strict_types=1);

use Swisseph\Constants;
use Swisseph\Domain\NodesApsides\LunarOsculatingCalculator;
use Swisseph\Domain\NodesApsides\OsculatingCalculator;

require_once __DIR__ . '/../vendor/autoload.php';

$ephePath = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe';
swe_set_ephe_path($ephePath);

$jd = 2460000.5;
$iflag = Constants::SEFLG_SPEED;

echo "=== Testing LunarOsculatingCalculator ===\n\n";
echo "JD: $jd (25 Feb 2023 00:00 UT)\n";
echo "Flags: " . sprintf("0x%X", $iflag) . "\n\n";

echo "Reference (swetest64):\n";
echo "  True Node:   35.8566277° speed 0.0044299°/d\n";
echo "  Oscu Apogee: 123.3606289° lat 5.0870555° dist 0.002729960 AU speed -2.6305447°/d\n\n";

$serr = null;

// Test True Node
$xreturn = [];
$result = LunarOsculatingCalculator::calculate($jd, Constants::SE_TRUE_NODE, $iflag, $xreturn, $serr);

if ($result < 0) {
    echo "Error calculating True Node: $serr\n";
} else {
    echo "True Node (LunarOsculatingCalculator):\n";
    echo sprintf("  Longitude: %.7f° (expected 35.8566277°, diff: %.7f°)\n",
        $xreturn[0], $xreturn[0] - 35.8566277);
    echo sprintf("  Latitude:  %.7f°\n", $xreturn[1]);
    echo sprintf("  Distance:  %.10f AU (expected 0.002544237)\n", $xreturn[2]);
    echo sprintf("  Speed lon: %.7f°/d (expected 0.0044299°/d, diff: %.7f)\n",
        $xreturn[3], $xreturn[3] - 0.0044299);
    echo sprintf("  Speed lat: %.7f°/d\n", $xreturn[4]);
}

echo "\n";

// Test Osculating Apogee
$xreturn = [];
$result = LunarOsculatingCalculator::calculate($jd, Constants::SE_OSCU_APOG, $iflag, $xreturn, $serr);

if ($result < 0) {
    echo "Error calculating Oscu Apogee: $serr\n";
} else {
    echo "Oscu Apogee (LunarOsculatingCalculator):\n";
    echo sprintf("  Longitude: %.7f° (expected 123.3606289°, diff: %.7f°)\n",
        $xreturn[0], $xreturn[0] - 123.3606289);
    echo sprintf("  Latitude:  %.7f° (expected 5.0870555°, diff: %.7f°)\n",
        $xreturn[1], $xreturn[1] - 5.0870555);
    echo sprintf("  Distance:  %.10f AU (expected 0.002729960)\n", $xreturn[2]);
    echo sprintf("  Speed lon: %.7f°/d (expected -2.6305447°/d, diff: %.7f)\n",
        $xreturn[3], $xreturn[3] - (-2.6305447));
    echo sprintf("  Speed lat: %.7f°/d\n", $xreturn[4]);
}

echo "\nDone.\n";
