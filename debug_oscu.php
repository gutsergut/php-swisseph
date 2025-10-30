<?php

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

$tjd = 2451545.0;
$ipl = Constants::SE_JUPITER;  // Changed from SE_MARS to match C test
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;

$ret = swe_nod_aps($tjd, $ipl, $iflag, Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);

echo "Return: $ret\n";
echo "Error: " . ($serr ?? 'none') . "\n";
echo "xnasc: ";
print_r($xnasc);
echo "xperi: ";
print_r($xperi);
