<?php
/**
 * Test Mars osculating nodes - compare with C reference
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

$tjd_ut = 2451545.0; // J2000.0 UT
$ipl = Constants::SE_MARS;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$method = Constants::SE_NODBIT_OSCU;

$xnasc = $xndsc = $xperi = $xaphe = [];
$serr = '';

$ret = swe_nod_aps_ut($tjd_ut, $ipl, $iflag, $method, $xnasc, $xndsc, $xperi, $xaphe, $serr);

if ($ret < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "PHP Mars Osculating Nodes at J2000.0 UT\n";
echo "========================================\n\n";

printf("Ascending Node:\n");
printf("  Longitude: %.10f°\n", $xnasc[0]);
printf("  Latitude:  %.10f°\n", $xnasc[1]);
printf("  Distance:  %.10f AU\n\n", $xnasc[2]);

printf("Descending Node:\n");
printf("  Longitude: %.10f°\n", $xndsc[0]);
printf("  Latitude:  %.10f°\n", $xndsc[1]);
printf("  Distance:  %.10f AU\n\n", $xndsc[2]);

printf("Perihelion:\n");
printf("  Longitude: %.10f°\n", $xperi[0]);
printf("  Latitude:  %.10f°\n", $xperi[1]);
printf("  Distance:  %.10f AU\n\n", $xperi[2]);

printf("Aphelion:\n");
printf("  Longitude: %.10f°\n", $xaphe[0]);
printf("  Latitude:  %.10f°\n", $xaphe[1]);
printf("  Distance:  %.10f AU\n\n", $xaphe[2]);

// Compare with C reference
$c_ref = [
    'nasc_lon' => 7.6769471910,
    'ndsc_lon' => 248.8829584013,
    'peri_lon' => 313.3141620324,
    'aphe_lon' => 192.2775649922,
];

echo "=== Comparison with C reference ===\n";
printf("Ascending Node diff:  %.6f° = %.2f arcsec\n", $xnasc[0] - $c_ref['nasc_lon'], ($xnasc[0] - $c_ref['nasc_lon']) * 3600);
printf("Descending Node diff: %.6f° = %.2f arcsec\n", $xndsc[0] - $c_ref['ndsc_lon'], ($xndsc[0] - $c_ref['ndsc_lon']) * 3600);
printf("Perihelion diff:      %.6f° = %.2f arcsec\n", $xperi[0] - $c_ref['peri_lon'], ($xperi[0] - $c_ref['peri_lon']) * 3600);
printf("Aphelion diff:        %.6f° = %.2f arcsec\n", $xaphe[0] - $c_ref['aphe_lon'], ($xaphe[0] - $c_ref['aphe_lon']) * 3600);
