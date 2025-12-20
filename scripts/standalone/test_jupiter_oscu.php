<?php
/**
 * Debug Jupiter osculating nodes
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Swe\Functions\NodesApsidesFunctions;
use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

$tjd = 2451545.0; // J2000.0 UT
$ipl = Constants::SE_JUPITER;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$method = Constants::SE_NODBIT_OSCU;

$xnasc = $xndsc = $xperi = $xaphe = [];
$serr = '';

// Use nodApsUt() to match C test which uses swe_nod_aps_ut()
$ret = NodesApsidesFunctions::nodApsUt($tjd, $ipl, $iflag, $method, $xnasc, $xndsc, $xperi, $xaphe, $serr);

echo "=== PHP RESULT for Jupiter Osculating Nodes ===\n";
echo "ret = $ret\n";
if ($serr) {
    echo "serr = $serr\n";
}
echo "\n";
printf("Ascending Node:  lon=%.10f°\n", $xnasc[0]);
printf("Descending Node: lon=%.10f°\n", $xndsc[0]);
printf("Perihelion:      lon=%.10f°\n", $xperi[0]);
printf("Aphelion:        lon=%.10f°\n", $xaphe[0]);

echo "\n=== C REFERENCE ===\n";
printf("Ascending Node:  lon=100.5194686522°\n");
printf("Descending Node: lon=280.4644425303°\n");
printf("Perihelion:      lon=4.1628298067°\n");
printf("Aphelion:        lon=205.5688205573°\n");

echo "\n=== DELTAS ===\n";
printf("Ascending:  delta=%.4f arcsec\n", abs($xnasc[0] - 100.5194686522) * 3600);
printf("Descending: delta=%.4f arcsec\n", abs($xndsc[0] - 280.4644425303) * 3600);
printf("Perihelion: delta=%.4f arcsec\n", abs($xperi[0] - 4.1628298067) * 3600);
printf("Aphelion:   delta=%.4f arcsec\n", abs($xaphe[0] - 205.5688205573) * 3600);
