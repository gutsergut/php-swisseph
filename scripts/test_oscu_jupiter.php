<?php
/**
 * Test osculating nodes for Jupiter - comparing PHP with C reference
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_ut = 2451545.0; // J2000 at UT 12:00
$tjd_et = $tjd_ut + swe_deltat($tjd_ut);

echo "=== Osculating Nodes Test: Jupiter ===\n\n";
echo "tjd_ut = $tjd_ut\n";
echo "tjd_et = $tjd_et\n\n";

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';

$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

$ret = swe_nod_aps(
    $tjd_et,
    Constants::SE_JUPITER,
    $iflag,
    Constants::SE_NODBIT_OSCU,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

// C reference values from swetest64.exe (J2000 UT 12:00)
$c_asc = 100.5194687;
$c_dsc = 280.4645626;

echo "Results:\n";
echo "--------\n";
printf("Ascending node:  PHP = %.7f°, C = %.7f°, Diff = %.2f\"\n",
    $xnasc[0], $c_asc, ($xnasc[0] - $c_asc) * 3600);
printf("Descending node: PHP = %.7f°, C = %.7f°, Diff = %.2f\"\n",
    $xndsc[0], $c_dsc, ($xndsc[0] - $c_dsc) * 3600);

echo "\nFull output:\n";
echo "Ascending:  [{$xnasc[0]}, {$xnasc[1]}, {$xnasc[2]}, {$xnasc[3]}, {$xnasc[4]}, {$xnasc[5]}]\n";
echo "Descending: [{$xndsc[0]}, {$xndsc[1]}, {$xndsc[2]}, {$xndsc[3]}, {$xndsc[4]}, {$xndsc[5]}]\n";

if (abs($xnasc[0] - $c_asc) < 0.003) { // < 10 arcsec
    echo "\n✓ Result within acceptable tolerance (<10\")\n";
} else {
    echo "\n✗ Result differs by more than 10\"\n";
}
