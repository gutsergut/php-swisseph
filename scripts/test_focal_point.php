<?php
require_once __DIR__ . '/../vendor/autoload.php';
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

use Swisseph\Constants;

$jd = 2451545.0;
$xnasc = $xndsc = $xperi = $xaphe = [];
$serr = null;

// Aphelion
$ret1 = swe_nod_aps($jd, Constants::SE_MARS, Constants::SEFLG_SWIEPH,
    Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);
echo sprintf("Aphelion mode: Peri=%.4f°, Aphe=%.4f°\n", $xperi[0], $xaphe[0]);

// Focal point
$xfocal = [];
$ret2 = swe_nod_aps($jd, Constants::SE_MARS, Constants::SEFLG_SWIEPH,
    Constants::SE_NODBIT_OSCU | Constants::SE_NODBIT_FOPOINT, $xnasc, $xndsc, $xperi, $xfocal, $serr);
echo sprintf("Focal mode: Peri=%.4f°, Focal=%.4f°\n", $xperi[0], $xfocal[0]);

echo sprintf("Peri - Focal = %.4f°\n", abs($xperi[0] - $xfocal[0]));
echo sprintf("Peri + 180 = %.4f°, Focal = %.4f°\n", swe_degnorm($xperi[0] + 180), $xfocal[0]);
