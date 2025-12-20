#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;

$tjd = 2451545.0; // J2000.0
$xx = [];
$serr = '';

// Get Sun barycentric
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_SPEED;
$retval = PlanetsFunctions::calc($tjd, Constants::SE_SUN, $iflag, $xx, $serr);

echo "Sun barycentric at J2000.0:\n";
printf("  X = %.15f AU\n", $xx[0]);
printf("  Y = %.15f AU\n", $xx[1]);
printf("  Z = %.15f AU\n", $xx[2]);
printf("\n");

// Calculate what 3.994 + Sun would be:
$jupFromCheb = 3.994040678108119;
$resultIfAdded = $jupFromCheb + $xx[0];
printf("If we add Sun X to Chebyshev result:\n");
printf("  3.994040678108119 + %.15f = %.15f\n", $xx[0], $resultIfAdded);
printf("  C expects: 4.178312157164416\n");
printf("  Difference: %.15f\n", 4.178312157164416 - $resultIfAdded);
