<?php
/**
 * Debug deltaT comparison
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\DeltaT;

$tjdUt = 2451545.0;
$deltaT = DeltaT::deltaTSecondsFromJd($tjdUt);
printf("PHP deltaT: %.10f seconds = %.16f days\n", $deltaT, $deltaT / 86400.0);
printf("PHP tjdEt: %.16f\n", $tjdUt + $deltaT / 86400.0);
