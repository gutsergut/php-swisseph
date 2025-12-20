<?php
/**
 * Debug real applyOsculatingNodApsTransformations with actual values
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
swe_set_ephe_path($ephePath);

$tjdEt = 2451545.0;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

// Call swe_nod_aps and capture debug
putenv('DEBUG_NODAPS=1');

$xnasc = $xndsc = $xperi = $xaphe = [];
$serr = '';

$ret = swe_nod_aps($tjdEt, Constants::SE_JUPITER, $iflag, Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);

putenv('DEBUG_NODAPS=');

echo "=== RESULT ===\n";
echo sprintf("Ascending Node:  lon=%.10f°\n", $xnasc[0]);
echo sprintf("C REFERENCE:     lon=100.5196455351°\n");
echo sprintf("DELTA = %.4f arcsec\n", abs($xnasc[0] - 100.5196455351) * 3600);
