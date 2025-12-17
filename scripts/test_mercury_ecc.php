<?php
require_once __DIR__ . '/../vendor/autoload.php';
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2451545.0;
$xnasc = $xndsc = $xperi = $xaphe = [];
$serr = null;

// Mercury osculating
swe_nod_aps($jd, 2, 0, 2, $xnasc, $xndsc, $xperi, $xaphe, $serr);
echo sprintf("PHP Peri: lon=%.6f°, lat=%.6f°, dist=%.6f AU\n", $xperi[0], $xperi[1], $xperi[2]);
echo sprintf("PHP Aphe: lon=%.6f°, lat=%.6f°, dist=%.6f AU\n", $xaphe[0], $xaphe[1], $xaphe[2]);

// C swetest reference
echo "\nC reference (swetest64 -fFlbr):\n";
echo "Output format: peri_lon  aphe_lon  focal_lon  last_lat  last_dist\n";
