<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

// Test WITHOUT SEFLG_HELCTR (barycentric)
$jd = 2451545.0;
$iflag_bary = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_SPEED;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$xx_bary = [];
$serr_bary = null;
$rc_bary = swe_calc($jd, Constants::SE_JUPITER, $iflag_bary, $xx_bary, $serr_bary);

echo "Jupiter at J2000 - PHP Barycentric vs Heliocentric:\n";
echo "====================================================\n\n";

if ($rc_bary < 0) {
    echo "Error (bary): " . $serr_bary . "\n";
    exit(1);
}

echo "BARYCENTRIC (no SEFLG_HELCTR):\n";
echo sprintf("  X = %.15f AU\n", $xx_bary[0]);
echo sprintf("  Y = %.15f AU\n", $xx_bary[1]);
echo sprintf("  Z = %.15f AU\n", $xx_bary[2]);

// Test WITH SEFLG_HELCTR (heliocentric)
$iflag_helio = $iflag_bary | Constants::SEFLG_HELCTR;
$xx_helio = [];
$serr_helio = null;
$rc_helio = swe_calc($jd, Constants::SE_JUPITER, $iflag_helio, $xx_helio, $serr_helio);

if ($rc_helio < 0) {
    echo "Error (helio): " . $serr_helio . "\n";
    exit(1);
}

echo "\nHELIOCENTRIC (with SEFLG_HELCTR):\n";
echo sprintf("  X = %.15f AU\n", $xx_helio[0]);
echo sprintf("  Y = %.15f AU\n", $xx_helio[1]);
echo sprintf("  Z = %.15f AU\n", $xx_helio[2]);

echo "\nDifference (Helio - Bary):\n";
echo sprintf("  ΔX = %.15f AU\n", $xx_helio[0] - $xx_bary[0]);
echo sprintf("  ΔY = %.15f AU\n", $xx_helio[1] - $xx_bary[1]);
echo sprintf("  ΔZ = %.15f AU\n", $xx_helio[2] - $xx_bary[2]);

echo "\n\nC Reference (from test_helio_bary.exe):\n";
echo "BARYCENTRIC: X = 4.178312157164416 AU\n";
echo "HELIOCENTRIC: X = 4.001177023456984 AU\n";
