#!/usr/bin/env php
<?php
/**
 * Direct test of swe_calc to compare with C
 */

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0; // J2000.0
$ipl = Constants::SE_SATURN;

// Exact flags from C code swecl.c:5248
$iflJ2000 = Constants::SEFLG_SWIEPH |
            Constants::SEFLG_J2000 |
            Constants::SEFLG_EQUATORIAL |
            Constants::SEFLG_XYZ |
            Constants::SEFLG_TRUEPOS |
            Constants::SEFLG_NONUT |
            Constants::SEFLG_SPEED |
            Constants::SEFLG_HELCTR;

echo "Testing direct swe_calc for Saturn\n";
echo "===================================\n";
echo sprintf("JD: %.10f\n", $jd);
echo sprintf("ipl: %d (Saturn)\n", $ipl);
echo sprintf("iflag: 0x%X\n\n", $iflJ2000);

$xx = [];
$serr = '';
$ret = swe_calc($jd, $ipl, $iflJ2000, $xx, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Result (EQUATORIAL J2000 XYZ HELCTR):\n";
echo sprintf("X  = %.15f AU\n", $xx[0]);
echo sprintf("Y  = %.15f AU\n", $xx[1]);
echo sprintf("Z  = %.15f AU\n", $xx[2]);
echo sprintf("VX = %.15f AU/day\n", $xx[3]);
echo sprintf("VY = %.15f AU/day\n", $xx[4]);
echo sprintf("VZ = %.15f AU/day\n", $xx[5]);

echo "\nExpected from C (from test_c_nodes.txt):\n";
echo "X  = 6.406408601900000 AU\n";
echo "Y  = 6.174658357900000 AU\n";
echo "Z  = 2.274770065700000 AU\n";

echo "\nDifference:\n";
echo sprintf("ΔX = %.15f AU (%.3f km)\n", $xx[0] - 6.406408601900000, ($xx[0] - 6.406408601900000) * 149597870.7);
echo sprintf("ΔY = %.15f AU (%.3f km)\n", $xx[1] - 6.174658357900000, ($xx[1] - 6.174658357900000) * 149597870.7);
echo sprintf("ΔZ = %.15f AU (%.3f km)\n", $xx[2] - 2.274770065700000, ($xx[2] - 2.274770065700000) * 149597870.7);
