<?php

declare(strict_types=1);

namespace Swisseph;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

echo "=== Debug planForOscElem - Mars Nodes ===\n\n";

$tjd = 2451545.0; // J2000.0
$ipl = Constants::SE_MARS;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

echo "Calling swe_nod_aps for Mars osculating nodes...\n";
echo "JD: $tjd\n";
echo "Flags: $iflag\n\n";

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';

$ret = swe_nod_aps(
    $tjd,
    $ipl,
    $iflag,
    Constants::SE_NODBIT_OSCU,  // Osculating nodes
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

echo "Return code: $ret\n";
if ($serr) {
    echo "Error: $serr\n";
}

echo "\nResults:\n";
echo "Ascending node:\n";
print_r($xnasc);

echo "\nPerihel:\n";
print_r($xperi);

// Also test simple Mars position
echo "\n--- Simple Mars position ---\n";
$xx = [];
$serr2 = '';
$ret2 = swe_calc($tjd, Constants::SE_MARS, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr2);
echo "Mars position return: $ret2\n";
if ($serr2) echo "Error: $serr2\n";
echo "Mars coords:\n";
print_r($xx);
