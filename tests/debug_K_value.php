<?php

/**
 * Minimal debug for swe_sol_eclipse_when_glob()
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\SolarEclipseWhenGlobFunctions;

// Initialize
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Calculate K for 2024-01-01
$tjdStart = swe_julday(2024, 1, 1, 0.0, Constants::SE_GREG_CAL);
echo "tjdStart: $tjdStart\n";

$K = (int)(($tjdStart - Constants::J2000) / 365.2425 * 12.3685);
echo "Initial K (lunation): $K\n";
echo "K-1 for forward search: " . ($K - 1) . "\n\n";

// Calculate approximate dates for a few lunations
for ($i = -1; $i <= 5; $i++) {
    $Ktest = $K + $i;
    $T = $Ktest / 1236.85;
    $tjd = 2451550.09765 + 29.530588853 * $Ktest
                         + 0.0001337 * ($T*$T)
                         - 0.000000150 * ($T*$T*$T)
                         + 0.00000000073 * ($T*$T*$T*$T);

    $y = $m = $d = $h = 0;
    swe_revjul($tjd, Constants::SE_GREG_CAL, $y, $m, $d, $h);

    printf("K=%d -> JD=%.2f -> %04d-%02d-%02d\n", $Ktest, $tjd, $y, $m, $d);
}

echo "\n2024-04-08 = JD " . swe_julday(2024, 4, 8, 0, Constants::SE_GREG_CAL) . "\n";
