<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Nutation\Iau2000Planetary;

// Test at J2000.0
$jd = 2451545.0;

echo "IAU 2000A Planetary Nutation at J2000.0:\n\n";

[$dpsi_pl, $deps_pl] = Iau2000Planetary::calc($jd);

echo sprintf("  Δψ (longitude): %.9f° = %.6f\" = %.3f mas\n",
    $dpsi_pl, $dpsi_pl * 3600, $dpsi_pl * 3600000);
echo sprintf("  Δε (obliquity):  %.9f° = %.6f\" = %.3f mas\n",
    $deps_pl, $deps_pl * 3600, $deps_pl * 3600000);

// Test at 2024-01-01
$jd2024 = 2460310.5;
echo "\n\nIAU 2000A Planetary Nutation at 2024-01-01:\n\n";

[$dpsi2024, $deps2024] = Iau2000Planetary::calc($jd2024);

echo sprintf("  Δψ (longitude): %.9f° = %.6f\" = %.3f mas\n",
    $dpsi2024, $dpsi2024 * 3600, $dpsi2024 * 3600000);
echo sprintf("  Δε (obliquity):  %.9f° = %.6f\" = %.3f mas\n",
    $deps2024, $deps2024 * 3600, $deps2024 * 3600000);

echo "\n\nNote: Planetary nutation is typically on the order of 1 milliarcsecond (mas).\n";
echo "This is much smaller than luni-solar nutation (~10-20 arcseconds).\n";
