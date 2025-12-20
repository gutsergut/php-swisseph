<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$xn = $xd = $xp = $xa = array_fill(0, 6, 0.0);
$serr = null;

echo "Testing MEAN nodes for Mars at J2000:\n";
$method = Constants::SE_NODBIT_MEAN;
$ret = swe_nod_aps(2451545.0, Constants::SE_MARS, 0, $method, $xn, $xd, $xp, $xa, $serr);

echo "Return: $ret\n";
echo "Error: " . ($serr ?? 'none') . "\n";
echo "Ascending node: lon={$xn[0]}, lat={$xn[1]}, dist={$xn[2]}\n";
echo "Perihelion: lon={$xp[0]}, lat={$xp[1]}, dist={$xp[2]}\n\n";

echo "Testing OSCULATING nodes for Mars at J2000:\n";
$method = Constants::SE_NODBIT_OSCU;
$ret = swe_nod_aps(2451545.0, Constants::SE_MARS, 0, $method, $xn, $xd, $xp, $xa, $serr);

echo "Return: $ret\n";
echo "Error: " . ($serr ?? 'none') . "\n";
echo "Ascending node: lon={$xn[0]}, lat={$xn[1]}, dist={$xn[2]}\n";
echo "Perihelion: lon={$xp[0]}, lat={$xp[1]}, dist={$xp[2]}\n";
