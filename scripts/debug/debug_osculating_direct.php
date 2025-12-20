<?php

declare(strict_types=1);

namespace Swisseph;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

echo "=== Direct Osculating Calculation Debug ===\n\n";

// Mars at J2000
$tjd = 2451545.0;
$ipl = Constants::SE_MARS;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

echo "Calling swe_nod_aps for Mars osculating nodes...\n";
echo "  JD: $tjd\n";
echo "  Planet: Mars\n";
echo "  Method: NODBIT_OSCU\n\n";

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';

$ret = swe_nod_aps(
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

echo "Return code: $ret\n";
if ($serr !== '') {
    echo "Error: $serr\n";
}

echo "\nAscending Node:\n";
var_dump($xnasc);

echo "\nDescending Node:\n";
var_dump($xndsc);

echo "\nPerihelion:\n";
var_dump($xperi);

echo "\nAphelion:\n";
var_dump($xaphe);

// Check for NAN
$hasNan = false;
foreach ([$xnasc, $xndsc, $xperi, $xaphe] as $name => $arr) {
    foreach ($arr as $val) {
        if (is_nan($val)) {
            $hasNan = true;
            break 2;
        }
    }
}

echo "\n" . ($hasNan ? "❌ HAS NAN" : "✅ NO NAN") . "\n";
