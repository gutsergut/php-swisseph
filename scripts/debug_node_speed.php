<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Domain\NodesApsides\PlanetaryElements;
use Swisseph\Constants;

$jd = 2451545.0; // J2000
$ipl = Constants::SE_JUPITER;
$iplx = PlanetaryElements::IPL_TO_ELEM[$ipl];

echo "=== Debugging Node Speed Calculation ===\n\n";

$t = ($jd - 2451545.0) / 36525.0; // T = 0

// Get raw orbital elements
$node_raw = PlanetaryElements::evalPoly(PlanetaryElements::EL_NODE[$iplx], $t);
$node_speed_raw = PlanetaryElements::EL_NODE[$iplx][1] / 36525.0;

echo "T = $t (centuries from J2000)\n\n";

echo "Raw from tables:\n";
echo "  Node longitude: $node_raw°\n";
echo "  Node speed (dlon/dt): $node_speed_raw°/day\n\n";

// Now let's see what MeanCalculator does
// It should just pass these values through without modification!

// Let's manually trace through the code
echo "What should MeanCalculator do:\n";
echo "  xnasc[0] = evalPoly(...) = $node_raw°\n";
echo "  xnasc[3] = EL_NODE[...][1] / 36525 = $node_speed_raw°/day\n\n";

// Let's verify by calling it
use Swisseph\NodesApsides;

NodesApsides::compute($jd, $ipl, 0, $serr);
$results = NodesApsides::getResults();
$xnasc = $results[0];

echo "Actual MeanCalculator result:\n";
echo "  xnasc[0] = {$xnasc[0]}°\n";
echo "  xnasc[3] = {$xnasc[3]}°/day\n\n";

echo "Difference:\n";
$diff_lon = $xnasc[0] - $node_raw;
$diff_speed = $xnasc[3] - $node_speed_raw;
echo "  Longitude diff: {$diff_lon}° (" . ($diff_lon * 3600) . "\")\n";
echo "  Speed diff: {$diff_speed}°/day\n\n";

// Now let's check if this difference comes from CoordinateTransformer
// Let's disable it temporarily and see
echo "To isolate the issue, we need to check:\n";
echo "1. Does the difference come from speed calculation in MeanCalculator?\n";
echo "2. Or from CoordinateTransformer?\n\n";

// The key question: what are xnasc values BEFORE transformMeanToTrue is called?
echo "We need to add debug output inside MeanCalculator before calling CoordinateTransformer\n";
