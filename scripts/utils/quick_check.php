<?php
require 'vendor/autoload.php';
use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$ephFile = $argv[1] ?? 'de200.eph';

JplEphemeris::resetInstance();
$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = '';
$jpl->open($ss, $ephFile, 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/', $serr);

$p = [];
$jpl->pleph(2451545.0, JplConstants::J_MERCURY, JplConstants::J_SBARY, $p, $serr);

printf("File: %s\n", $ephFile);
printf("PHP Mercury bary JD=2451545.0:\n");
printf("X = %.15f AU\n", $p[0]);
printf("Y = %.15f AU\n", $p[1]);
printf("Z = %.15f AU\n", $p[2]);

echo "\nswetest with -t12 (TT):\n";
echo "X = -0.137288205 AU\n";
echo "Y = -0.403227332 AU\n";
echo "Z = -0.201399025 AU\n";

echo "\nDifference:\n";
printf("dX = %.9f AU = %.0f km\n", $p[0] - (-0.137288205), ($p[0] - (-0.137288205)) * 149597870.66);
printf("dY = %.9f AU = %.0f km\n", $p[1] - (-0.403227332), ($p[1] - (-0.403227332)) * 149597870.66);
printf("dZ = %.9f AU = %.0f km\n", $p[2] - (-0.201399025), ($p[2] - (-0.201399025)) * 149597870.66);
