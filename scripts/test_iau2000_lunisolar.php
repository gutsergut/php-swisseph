<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Nutation\FundamentalArguments;
use Swisseph\Nutation\Iau2000LuniSolar;

// Test at J2000.0
$jd = 2451545.0;

echo "IAU 2000 Luni-Solar Nutation at J2000.0:\n\n";

// Calculate fundamental arguments
$args = FundamentalArguments::calcSimon1994($jd);

// IAU 2000A (full 678 terms)
[$dpsi_2000a, $deps_2000a] = Iau2000LuniSolar::calc($jd, $args, false);
echo "IAU 2000A (678 terms):\n";
echo sprintf("  Δψ (longitude): %.9f° = %.6f\"\n", $dpsi_2000a, $dpsi_2000a * 3600);
echo sprintf("  Δε (obliquity):  %.9f° = %.6f\"\n", $deps_2000a, $deps_2000a * 3600);

// IAU 2000B (truncated 77 terms)
[$dpsi_2000b, $deps_2000b] = Iau2000LuniSolar::calc($jd, $args, true);
echo "\nIAU 2000B (77 terms):\n";
echo sprintf("  Δψ (longitude): %.9f° = %.6f\"\n", $dpsi_2000b, $dpsi_2000b * 3600);
echo sprintf("  Δε (obliquity):  %.9f° = %.6f\"\n", $deps_2000b, $deps_2000b * 3600);

echo "\nDifference (2000A - 2000B):\n";
echo sprintf("  Δψ: %.9f\" (%.3f mas)\n", ($dpsi_2000a - $dpsi_2000b) * 3600, ($dpsi_2000a - $dpsi_2000b) * 3600000);
echo sprintf("  Δε: %.9f\" (%.3f mas)\n", ($deps_2000a - $deps_2000b) * 3600, ($deps_2000a - $deps_2000b) * 3600000);

// Test at 2024-01-01
$jd2024 = 2460310.5;
echo "\n\nIAU 2000A Luni-Solar Nutation at 2024-01-01:\n\n";

$args2024 = FundamentalArguments::calcSimon1994($jd2024);
[$dpsi2024, $deps2024] = Iau2000LuniSolar::calc($jd2024, $args2024, false);

echo sprintf("  Δψ (longitude): %.9f° = %.6f\"\n", $dpsi2024, $dpsi2024 * 3600);
echo sprintf("  Δε (obliquity):  %.9f° = %.6f\"\n", $deps2024, $deps2024 * 3600);
