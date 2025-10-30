<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\NodesApsidesFunctions;
use Swisseph\Constants;

$jd = 2451545.0; // J2000.0
$planet = 4; // Mars

echo "Testing Mars mean nodes at J2000.0\n\n";

// Get mean nodes with SEFLG_SWIEPH | SEFLG_SPEED
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';
$ret = NodesApsidesFunctions::nodAps(
    $jd,
    $planet,
    Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, // 256 | 1 = 257
    Constants::SE_NODBIT_MEAN, // method = 1
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

echo "Return code: $ret\n";
if ($serr) echo "Error: $serr\n";
echo "\nResults:\n";
echo "  Ascending node: {$xnasc[0]}°\n";
echo "  Descending node: {$xndsc[0]}°\n";
echo "  Perihelion: {$xperi[0]}°, r={$xperi[2]} AU\n";
echo "  Aphelion: {$xaphe[0]}°, r={$xaphe[2]} AU\n";

echo "\nExpected from swetest -p4 -bj2451545 -n1 -fPN:\n";
echo "  Ascending node: 7.6769124°\n";
echo "  Descending node: 248.8827425°\n";

// Also check with NONUT flag to match swetest
echo "\n\nWith SEFLG_NONUT (to match swetest reference frame):\n";
$xnasc2 = [];
$xndsc2 = [];
$xperi2 = [];
$xaphe2 = [];
$serr2 = '';
$ret2 = NodesApsidesFunctions::nodAps(
    $jd,
    $planet,
    Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NONUT,
    Constants::SE_NODBIT_MEAN,
    $xnasc2,
    $xndsc2,
    $xperi2,
    $xaphe2,
    $serr2
);

echo "Return code: $ret2\n";
if ($serr2) echo "Error: $serr2\n";
echo "\nResults:\n";
echo "  Ascending node: {$xnasc2[0]}°\n";
echo "  Descending node: {$xndsc2[0]}°\n";
