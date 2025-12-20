<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Nutation\Iau1980;
use Swisseph\Nutation\Iau2000;

// Test at J2000.0
$jd = 2451545.0;

echo "Comparison of Nutation Models at J2000.0:\n";
echo str_repeat("=", 70) . "\n\n";

// IAU 1980
[$dpsi_1980, $deps_1980] = Iau1980::calc($jd, false);
echo "IAU 1980 (~106 terms):\n";
echo sprintf("  Δψ: %12.9f rad = %10.6f\"\n", $dpsi_1980, $dpsi_1980 * 206264.80624709636);
echo sprintf("  Δε: %12.9f rad = %10.6f\"\n", $deps_1980, $deps_1980 * 206264.80624709636);

// IAU 2000B
[$dpsi_2000b, $deps_2000b] = Iau2000::calc($jd, true, false);
echo "\nIAU 2000B (77 luni-solar terms):\n";
echo sprintf("  Δψ: %12.9f rad = %10.6f\"\n", $dpsi_2000b, $dpsi_2000b * 206264.80624709636);
echo sprintf("  Δε: %12.9f rad = %10.6f\"\n", $deps_2000b, $deps_2000b * 206264.80624709636);

// IAU 2000A (without P03)
[$dpsi_2000a, $deps_2000a] = Iau2000::calc($jd, false, false);
echo "\nIAU 2000A (678 luni-solar + 687 planetary):\n";
echo sprintf("  Δψ: %12.9f rad = %10.6f\"\n", $dpsi_2000a, $dpsi_2000a * 206264.80624709636);
echo sprintf("  Δε: %12.9f rad = %10.6f\"\n", $deps_2000a, $deps_2000a * 206264.80624709636);

// IAU 2000A (with P03)
[$dpsi_2000a_p03, $deps_2000a_p03] = Iau2000::calc($jd, false, true);
echo "\nIAU 2000A + P03 corrections (IAU 2006):\n";
echo sprintf("  Δψ: %12.9f rad = %10.6f\"\n", $dpsi_2000a_p03, $dpsi_2000a_p03 * 206264.80624709636);
echo sprintf("  Δε: %12.9f rad = %10.6f\"\n\n", $deps_2000a_p03, $deps_2000a_p03 * 206264.80624709636);

// Differences
echo "Differences (IAU 2000A - IAU 1980):\n";
echo sprintf("  Δψ: %10.6f\" (%7.3f mas)\n",
    ($dpsi_2000a - $dpsi_1980) * 206264.80624709636,
    ($dpsi_2000a - $dpsi_1980) * 206264806.24709636);
echo sprintf("  Δε: %10.6f\" (%7.3f mas)\n\n",
    ($deps_2000a - $deps_1980) * 206264.80624709636,
    ($deps_2000a - $deps_1980) * 206264806.24709636);

// Test at 2024-01-01
$jd2024 = 2460310.5;
echo "\n" . str_repeat("=", 70) . "\n";
echo "Nutation at 2024-01-01 00:00 TT:\n";
echo str_repeat("=", 70) . "\n\n";

[$dpsi_1980_2024, $deps_1980_2024] = Iau1980::calc($jd2024, false);
[$dpsi_2000a_2024, $deps_2000a_2024] = Iau2000::calc($jd2024, false, true);

echo sprintf("IAU 1980:     Δψ = %10.6f\"   Δε = %10.6f\"\n",
    $dpsi_1980_2024 * 206264.80624709636,
    $deps_1980_2024 * 206264.80624709636);

echo sprintf("IAU 2000A+P03: Δψ = %10.6f\"   Δε = %10.6f\"\n",
    $dpsi_2000a_2024 * 206264.80624709636,
    $deps_2000a_2024 * 206264.80624709636);

echo sprintf("\nDifference:    Δψ = %10.6f\"   Δε = %10.6f\"\n",
    ($dpsi_2000a_2024 - $dpsi_1980_2024) * 206264.80624709636,
    ($deps_2000a_2024 - $deps_1980_2024) * 206264.80624709636);
