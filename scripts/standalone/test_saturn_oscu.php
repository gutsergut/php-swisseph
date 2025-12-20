<?php
/**
 * Test Saturn osculating nodes - compare with C reference
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

$tjd_ut = 2451545.0;
$ipl = Constants::SE_SATURN;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$method = Constants::SE_NODBIT_OSCU;

$xnasc = $xndsc = $xperi = $xaphe = [];
$serr = '';

$ret = swe_nod_aps_ut($tjd_ut, $ipl, $iflag, $method, $xnasc, $xndsc, $xperi, $xaphe, $serr);

echo "PHP Saturn Osculating Nodes at J2000.0 UT\n";
echo "==========================================\n\n";

printf("Ascending Node:  %.10f°\n", $xnasc[0]);
printf("Descending Node: %.10f°\n", $xndsc[0]);
printf("Perihelion:      %.10f°\n", $xperi[0]);
printf("Aphelion:        %.10f°\n\n", $xaphe[0]);

$c_ref = [
    'nasc' => 115.2334250651,
    'ndsc' => 292.4603927106,
    'peri' => 88.3836329207,
    'aphe' => 270.6109818153,
];

echo "=== Comparison with C reference ===\n";
printf("Ascending Node diff:  %.6f° = %.2f arcsec\n", $xnasc[0] - $c_ref['nasc'], ($xnasc[0] - $c_ref['nasc']) * 3600);
printf("Descending Node diff: %.6f° = %.2f arcsec\n", $xndsc[0] - $c_ref['ndsc'], ($xndsc[0] - $c_ref['ndsc']) * 3600);
printf("Perihelion diff:      %.6f° = %.2f arcsec\n", $xperi[0] - $c_ref['peri'], ($xperi[0] - $c_ref['peri']) * 3600);
printf("Aphelion diff:        %.6f° = %.2f arcsec\n", $xaphe[0] - $c_ref['aphe'], ($xaphe[0] - $c_ref['aphe']) * 3600);
