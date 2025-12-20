<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\NodesApsidesFunctions;

$jd = 2451545.0; // J2000.0
$planet = 4; // Mars

// Get mean nodes
$xmean = [];
$xndsc_mean = [];
$xperi_mean = [];
$xaphe_mean = [];
$serr = '';
NodesApsidesFunctions::nodAps($jd, $planet, 256, 1, $xmean, $xndsc_mean, $xperi_mean, $xaphe_mean, $serr); // method=1 = SE_NODBIT_MEAN

// Get osculating nodes
$xosc = [];
$xndsc_osc = [];
$xperi_osc = [];
$xaphe_osc = [];
$serr = '';
NodesApsidesFunctions::nodAps($jd, $planet, 256, 2, $xosc, $xndsc_osc, $xperi_osc, $xaphe_osc, $serr); // method=2 = SE_NODBIT_OSCU

echo "Mars nodes at J2000.0:\n";
echo "  Mean:       {$xmean[0]}°\n";
echo "  Osculating: {$xosc[0]}°\n";
echo "  Difference: " . abs($xosc[0] - $xmean[0]) . "°\n\n";

echo "Expected from swetest:\n";
echo "  Mean:       7.6738357°\n";
echo "  Osculating: 7.6769164°\n";
echo "  Difference: 0.0030807°\n";
