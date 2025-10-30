<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Nutation\Iau1980;

// Test nutation calculation for J2000.0
$jd = 2451545.0;

[$dpsi, $deps] = Iau1980::calc($jd, false);

echo "Nutation at J2000.0:\n";
echo sprintf("  Δψ (longitude): %.9f\" (%.12f rad)\n", $dpsi * 206264.80624709636, $dpsi);
echo sprintf("  Δε (obliquity):  %.9f\" (%.12f rad)\n", $deps * 206264.80624709636, $deps);

// Test with a recent date: 2024-01-01 00:00 TT
$jd2024 = 2460310.5;
[$dpsi2, $deps2] = Iau1980::calc($jd2024, false);

echo "\nNutation at 2024-01-01:\n";
echo sprintf("  Δψ (longitude): %.9f\" (%.12f rad)\n", $dpsi2 * 206264.80624709636, $dpsi2);
echo sprintf("  Δε (obliquity):  %.9f\" (%.12f rad)\n", $deps2 * 206264.80624709636, $deps2);

// Test with Herring 1987 corrections
[$dpsi_h, $deps_h] = Iau1980::calc($jd2024, true);

echo "\nNutation at 2024-01-01 (with Herring 1987):\n";
echo sprintf("  Δψ (longitude): %.9f\" (%.12f rad)\n", $dpsi_h * 206264.80624709636, $dpsi_h);
echo sprintf("  Δε (obliquity):  %.9f\" (%.12f rad)\n", $deps_h * 206264.80624709636, $deps_h);

echo "\nDifference (Herring - standard):\n";
echo sprintf("  Δψ: %.9f\"\n", ($dpsi_h - $dpsi2) * 206264.80624709636);
echo sprintf("  Δε: %.9f\"\n", ($deps_h - $deps2) * 206264.80624709636);
