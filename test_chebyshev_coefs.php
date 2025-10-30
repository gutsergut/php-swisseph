<?php

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

$serr = '';
$xx = [];
$iflg = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ |
        Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_SPEED |
        Constants::SEFLG_HELCTR;

$ret = swe_calc(2451545.0, Constants::SE_JUPITER, $iflg, $xx, $serr);

$swed = SwedState::getInstance();
$pdp = $swed->pldat[SwephConstants::SEI_JUPITER];

echo "PHP Chebyshev coefficients for Jupiter at J2000.0\n";
echo "==================================================\n\n";

echo "X coefficients:\n";
for ($i = 0; $i < $pdp->ncoe; $i++) {
    printf("  coef[%2d] = %20.15f\n", $i, $pdp->segp[$i]);
}

echo "\nY coefficients:\n";
for ($i = 0; $i < $pdp->ncoe; $i++) {
    printf("  coef[%2d] = %20.15f\n", $i, $pdp->segp[$pdp->ncoe + $i]);
}

echo "\nZ coefficients:\n";
for ($i = 0; $i < $pdp->ncoe; $i++) {
    printf("  coef[%2d] = %20.15f\n", $i, $pdp->segp[2 * $pdp->ncoe + $i]);
}

echo "\nSegment info:\n";
printf("  tseg0 = %.2f\n", $pdp->tseg0);
printf("  dseg = %.2f\n", $pdp->dseg);
printf("  ncoe = %d\n", $pdp->ncoe);
printf("  neval = %d\n", $pdp->neval);

echo "\nManual Chebyshev evaluation for X coordinate:\n";
$t = (2451545.0 - $pdp->tseg0) / $pdp->dseg;
$t = $t * 2.0 - 1.0;
printf("  t_normalized = %.10f\n", $t);

$x2 = $t * 2.0;
$br = 0.0;
$brp2 = 0.0;
$brpp = 0.0;

echo "\nClenshaw recursion steps:\n";
for ($j = $pdp->neval - 1; $j >= 0; $j--) {
    $brp2 = $brpp;
    $brpp = $br;
    $br = $x2 * $brpp - $brp2 + $pdp->segp[$j];
    if ($j >= $pdp->neval - 5 || $j <= 2) {
        printf("  j=%2d: br=%.15f, coef[%d]=%.15f\n", $j, $br, $j, $pdp->segp[$j]);
    } elseif ($j == $pdp->neval - 6) {
        echo "  ...\n";
    }
}

$result = ($br - $brp2) * 0.5;
printf("\nFinal result: (%.15f - %.15f) * 0.5 = %.15f\n", $br, $brp2, $result);
printf("Expected C result: 4.001177023500000\n");
printf("Difference: %.15f AU\n", $result - 4.001177023500000);
