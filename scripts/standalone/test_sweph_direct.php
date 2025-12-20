<?php

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

$serr = '';
$xx = [];
$iflg = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ |
        Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_SPEED |
        Constants::SEFLG_HELCTR;

echo "Testing DIRECT SwephCalculator output (ecliptic J2000 XYZ)\n";
echo sprintf("Flags: 0x%X\n\n", $iflg);

$retval = PlanetsFunctions::calc(2451545.0, Constants::SE_JUPITER, $iflg, $xx, $serr);

if ($retval < 0) {
    die("Error: $serr\n");
}

echo "PHP Ecliptic J2000 XYZ (NOAPPPOS, before appPosRest):\n";
printf("X = %.10f AU\n", $xx[0]);
printf("Y = %.10f AU\n", $xx[1]);
printf("Z = %.10f AU\n", $xx[2]);
printf("dX/dt = %.10f AU/day\n", $xx[3]);
printf("dY/dt = %.10f AU/day\n", $xx[4]);
printf("dZ/dt = %.10f AU/day\n", $xx[5]);

echo "\nExpected C values:\n";
echo "X = 4.0011770235 AU\n";
echo "Y = 2.9385762995 AU\n";
echo "Z = -0.1017854163 AU\n";
echo "dX/dt = -0.0045683156 AU/day\n";
echo "dY/dt = 0.0064432062 AU/day\n";
echo "dZ/dt = 0.0000755816 AU/day\n";

echo "\nDifferences:\n";
printf("ΔX = %.10f AU (%.4f%%)\n", $xx[0] - 4.0011770235, ($xx[0] / 4.0011770235 - 1) * 100);
printf("ΔY = %.10f AU (%.4f%%)\n", $xx[1] - 2.9385762995, ($xx[1] / 2.9385762995 - 1) * 100);
printf("ΔZ = %.10f AU (%.4f%%)\n", $xx[2] - (-0.1017854163), ($xx[2] / (-0.1017854163) - 1) * 100);
