<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Check 2024-04-08 18:00 UT
$jd = swe_julday(2024, 4, 8, 18.0, Constants::SE_GREG_CAL);
echo "JD for 2024-04-08 18:00 UT: $jd\n";

$geopos = [];
$attr = [];
$serr = '';
$retflag = swe_sol_eclipse_where($jd, Constants::SEFLG_SWIEPH, $geopos, $attr, $serr);

echo "Eclipse flags: $retflag\n";
if ($retflag & Constants::SE_ECL_TOTAL) echo "TOTAL ";
if ($retflag & Constants::SE_ECL_ANNULAR) echo "ANNULAR ";
if ($retflag & Constants::SE_ECL_PARTIAL) echo "PARTIAL ";
if ($retflag & Constants::SE_ECL_CENTRAL) echo "CENTRAL ";
if ($retflag & Constants::SE_ECL_NONCENTRAL) echo "NONCENTRAL ";
echo "\n";
