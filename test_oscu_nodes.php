#!/usr/bin/env php
<?php
/**
 * Test osculating nodes for Saturn to debug 186° vs 100.5° issue
 */

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

putenv('DEBUG_OSCU=1');

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0; // J2000.0
$ipl = Constants::SE_SATURN;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 | Constants::SEFLG_NONUT | Constants::SEFLG_TRUEPOS;

echo "Testing Saturn osculating nodes at J2000.0 (with SEFLG_J2000)\n";
echo "==========================================\n\n";

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';
$retval = swe_nod_aps($jd, $ipl, $iflag, Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);

if ($retval < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "\nRESULT:\n";
echo "-------\n";
printf("Ascending node:  %.6f°\n", $xnasc[0]);
printf("Descending node: %.6f°\n", $xndsc[0]);
printf("Distance:        %.10f AU\n", $xnasc[2]);

echo "\nExpected (from C swetest): ~100.5°\n";
echo "PHP returns:                {$xnasc[0]}°\n";

if (abs($xnasc[0] - 100.5) > 1.0) {
    echo "\n⚠️  WARNING: Large difference detected!\n";
} else {
    echo "\n✓ Result looks correct!\n";
}
