<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Domain\NodesApsides\PlanetaryElements;
use Swisseph\Constants;

$jd = 2451545.0; // J2000
$t = ($jd - 2451545.0) / 36525.0; // Should be 0.0

// Jupiter
$ipl = Constants::SE_JUPITER;
$iplx = PlanetaryElements::IPL_TO_ELEM[$ipl] ?? null;

echo "Jupiter ipl=$ipl, iplx=$iplx\n";
echo "Jupiter orbital elements at J2000 (T=$t):\n";
echo "=========================================\n\n";

$node = PlanetaryElements::evalPoly(PlanetaryElements::EL_NODE[$iplx], $t);
echo "Ascending Node: " . $node . "°\n";

$peri = PlanetaryElements::evalPoly(PlanetaryElements::EL_PERI[$iplx], $t);
echo "Perihelion arg: " . $peri . "°\n";

$incl = PlanetaryElements::evalPoly(PlanetaryElements::EL_INCL[$iplx], $t);
echo "Inclination:    " . $incl . "°\n";

$sema = PlanetaryElements::evalPoly(PlanetaryElements::EL_SEMA[$iplx], $t);
echo "Semi-major axis: $sema AU\n";

$ecce = PlanetaryElements::evalPoly(PlanetaryElements::EL_ECCE[$iplx], $t);
echo "Eccentricity:    $ecce\n";

echo "\n";

// Now check swetest
echo "Expected from swetest: 99.287939°\n";
echo "PHP calculated:        " . $node . "°\n";
echo "Difference:            " . abs($node - 99.287939) . "°\n";
