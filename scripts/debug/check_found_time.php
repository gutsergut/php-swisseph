<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Test found time from debug
$jd = 2460409.26204148;
echo "Testing JD: $jd\n";

$geopos = [];
$attr = [];
$serr = '';
$retflag = swe_sol_eclipse_where($jd, Constants::SEFLG_SWIEPH, $geopos, $attr, $serr);

echo "Eclipse flags: $retflag\n";
echo "Type: ";
if ($retflag & Constants::SE_ECL_TOTAL) echo "TOTAL ";
if ($retflag & Constants::SE_ECL_ANNULAR) echo "ANNULAR ";
if ($retflag & Constants::SE_ECL_PARTIAL) echo "PARTIAL ";
if ($retflag & Constants::SE_ECL_CENTRAL) echo "CENTRAL ";
if ($retflag & Constants::SE_ECL_NONCENTRAL) echo "NONCENTRAL ";
echo "\n";

echo "\nGeopos: lon={$geopos[0]}, lat={$geopos[1]}\n";
echo "Core shadow diameter: {$attr[3]} km\n";
