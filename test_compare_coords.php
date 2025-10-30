#!/usr/bin/env php
<?php
/**
 * Compare PHP and C coordinates for Saturn at J2000
 */

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0; // J2000.0
$ipl = Constants::SE_SATURN;

// Get geocentric coordinates (default)
$iflag_geo = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$xx_geo = [];
$serr = '';
$ret = swe_calc($jd, $ipl, $iflag_geo, $xx_geo, $serr);

if ($ret < 0) {
    echo "ERROR geocentric: $serr\n";
    exit(1);
}

echo "Saturn at J2000.0 (JD 2451545.0)\n";
echo "=================================\n\n";

echo "GEOCENTRIC (default - POLAR):\n";
echo sprintf("  Longitude: %.10f° (%.6f°)\n", $xx_geo[0], $xx_geo[0]);
echo sprintf("  Latitude:  %.10f° (%.6f°)\n", $xx_geo[1], $xx_geo[1]);
echo sprintf("  Distance:  %.10f AU\n", $xx_geo[2]);
echo sprintf("  dLon: %.15f °/day\n", $xx_geo[3]);
echo sprintf("  dLat: %.15f °/day\n", $xx_geo[4]);
echo sprintf("  dDist: %.15f AU/day\n", $xx_geo[5]);

// Get rectangular geocentric coordinates (ECLIPTIC XYZ)
$iflag_rect = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_XYZ;
$xx_rect = [];
$ret = swe_calc($jd, $ipl, $iflag_rect, $xx_rect, $serr);

// Round to 9 decimal places like C swetest does
$x_rounded = round($xx_rect[0], 9);
$y_rounded = round($xx_rect[1], 9);
$z_rounded = round($xx_rect[2], 9);

echo "\nGEOCENTRIC (RECTANGULAR ECLIPTIC):\n";
echo sprintf("  X: %.9f AU (full: %.15f)\n", $x_rounded, $xx_rect[0]);
echo sprintf("  Y: %.9f AU (full: %.15f)\n", $y_rounded, $xx_rect[1]);
echo sprintf("  Z: %.9f AU (full: %.15f)\n", $z_rounded, $xx_rect[2]);
echo sprintf("  VX: %.15f AU/day\n", $xx_rect[3]);
echo sprintf("  VY: %.15f AU/day\n", $xx_rect[4]);
echo sprintf("  VZ: %.15f AU/day\n", $xx_rect[5]);

echo "\nC swetest geocentric (expected):\n";
echo "  Longitude: 40.395678°\n";
echo "  Latitude:  -2.444858°\n";
echo "  Distance:  8.652785710 AU\n";
echo "  X: 6.583852290 AU\n";
echo "  Y: 5.602441232 AU\n";
echo "  Z: -0.369109478 AU\n";

echo "\nDifferences:\n";
echo sprintf("  ΔLon: %.6f°\n", $xx_geo[0] - 40.395678);
echo sprintf("  ΔLat: %.6f°\n", $xx_geo[1] - (-2.444858));
echo sprintf("  ΔDist: %.9f AU (%.1f km)\n",
    $xx_geo[2] - 8.652785710,
    ($xx_geo[2] - 8.652785710) * 149597870.7);
echo sprintf("  ΔX: %.9f AU (%.1f km)\n",
    $x_rounded - 6.583852290,
    ($x_rounded - 6.583852290) * 149597870.7);
echo sprintf("  ΔY: %.9f AU (%.1f km)\n",
    $y_rounded - 5.602441232,
    ($y_rounded - 5.602441232) * 149597870.7);
echo sprintf("  ΔZ: %.9f AU (%.1f km)\n",
    $z_rounded - (-0.369109478),
    ($z_rounded - (-0.369109478)) * 149597870.7);

// Get heliocentric coordinates (rectangular ecliptic)
$iflag_helio = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_HELCTR | Constants::SEFLG_XYZ;
$xx_helio = [];
$ret = swe_calc($jd, $ipl, $iflag_helio, $xx_helio, $serr);

if ($ret < 0) {
    echo "\nERROR heliocentric: $serr\n";
    exit(1);
}

$x_helio_rounded = round($xx_helio[0], 9);
$y_helio_rounded = round($xx_helio[1], 9);
$z_helio_rounded = round($xx_helio[2], 9);

echo "\n\nHELIOCENTRIC (RECTANGULAR ECLIPTIC):\n";
echo sprintf("  X: %.9f AU (full: %.15f)\n", $x_helio_rounded, $xx_helio[0]);
echo sprintf("  Y: %.9f AU (full: %.15f)\n", $y_helio_rounded, $xx_helio[1]);
echo sprintf("  Z: %.9f AU (full: %.15f)\n", $z_helio_rounded, $xx_helio[2]);
echo sprintf("  VX: %.15f AU/day\n", $xx_helio[3]);
echo sprintf("  VY: %.15f AU/day\n", $xx_helio[4]);
echo sprintf("  VZ: %.15f AU/day\n", $xx_helio[5]);

echo "\nC swetest heliocentric (expected):\n";
echo "  X: 6.407079535 AU\n";
echo "  Y: 6.569351391 AU\n";
echo "  Z: -0.369081423 AU\n";

echo "\nDifferences:\n";
echo sprintf("  ΔX: %.9f AU (%.1f km)\n",
    $x_helio_rounded - 6.407079535,
    ($x_helio_rounded - 6.407079535) * 149597870.7);
echo sprintf("  ΔY: %.9f AU (%.1f km)\n",
    $y_helio_rounded - 6.569351391,
    ($y_helio_rounded - 6.569351391) * 149597870.7);
echo sprintf("  ΔZ: %.9f AU (%.1f km)\n",
    $z_helio_rounded - (-0.369081423),
    ($z_helio_rounded - (-0.369081423)) * 149597870.7);

// Get osculating nodes
$iflag_nodes = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 | Constants::SEFLG_NONUT | Constants::SEFLG_TRUEPOS;
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$ret = swe_nod_aps($jd, $ipl, $iflag_nodes, Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);

if ($ret < 0) {
    echo "\nERROR nodes: $serr\n";
    exit(1);
}

echo "\n\nOSCULATING NODES:\n";
echo sprintf("  Ascending node:  %.10f° (%.6f°)\n", $xnasc[0], $xnasc[0]);
echo sprintf("  Distance:        %.10f AU\n", $xnasc[2]);
echo sprintf("  Expected from C: ~115.233513°\n");
echo sprintf("  Difference:      %.6f°\n", $xnasc[0] - 115.233513);
