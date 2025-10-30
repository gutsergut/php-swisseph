<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\NodesApsidesFunctions;

$tjd = 2451545.0; // J2000.0
$ipl = Constants::SE_MERCURY;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;

echo "=== Mercury Osculating Aphelion Debug ===\n\n";

$ret = NodesApsidesFunctions::nodAps(
    $tjd,
    $ipl,
    $iflag,
    Constants::SE_NODBIT_OSCU,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Return code: $ret\n\n";

echo "Perihelion (xperi):\n";
echo "  Longitude: {$xperi[0]}° (RA: {$xperi[0]}°)\n";
echo "  Latitude: {$xperi[1]}° (Dec: {$xperi[1]}°)\n";
echo "  Distance: {$xperi[2]} AU\n";
echo "  Speed lon: {$xperi[3]}°/day\n";
echo "  Speed lat: {$xperi[4]}°/day\n";
echo "  Speed dist: {$xperi[5]} AU/day\n\n";

echo "Aphelion (xaphe):\n";
echo "  Longitude: {$xaphe[0]}° (RA: {$xaphe[0]}°)\n";
echo "  Latitude: {$xaphe[1]}° (Dec: {$xaphe[1]}°)\n";
echo "  Distance: {$xaphe[2]} AU\n";
echo "  Speed lon: {$xaphe[3]}°/day\n";
echo "  Speed lat: {$xaphe[4]}°/day\n";
echo "  Speed dist: {$xaphe[5]} AU/day\n\n";

// Calculate orbital elements
$semiMajor = ($xperi[2] + $xaphe[2]) / 2.0;
$eccentricity = ($xaphe[2] - $xperi[2]) / ($xaphe[2] + $xperi[2]);

echo "Derived orbital elements:\n";
echo "  Semi-major axis: $semiMajor AU (expected ~0.387)\n";
echo "  Eccentricity: $eccentricity (expected ~0.206)\n\n";

// Check sanity
if ($xaphe[2] <= $xperi[2]) {
    echo "❌ ERROR: Aphelion distance <= perihelion distance!\n";
}

if ($eccentricity < 0.15 || $eccentricity > 0.25) {
    echo "❌ WARNING: Eccentricity out of range for Mercury!\n";
}

if ($semiMajor < 0.35 || $semiMajor > 0.42) {
    echo "❌ WARNING: Semi-major axis out of range for Mercury!\n";
}

// Expected values
echo "\nExpected for Mercury:\n";
echo "  Perihelion: ~0.307 AU (0.387 * (1 - 0.206))\n";
echo "  Aphelion: ~0.467 AU (0.387 * (1 + 0.206))\n";
echo "  Semi-major axis: ~0.387 AU\n";
echo "  Eccentricity: ~0.206\n";
