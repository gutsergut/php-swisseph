<?php
require 'vendor/autoload.php';
use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

JplEphemeris::resetInstance();
$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = '';
$jpl->open($ss, 'de406e.eph', 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/', $serr);

$p = [];
$jpl->pleph(2451545.0, JplConstants::J_MERCURY, JplConstants::J_SBARY, $p, $serr);
printf("Mercury J2000 DE406e: X=%.15f, Y=%.15f, Z=%.15f\n", $p[0], $p[1], $p[2]);

$jpl->pleph(-254863.5, JplConstants::J_MERCURY, JplConstants::J_SBARY, $p, $serr);
printf("Mercury early DE406e: X=%.15f, Y=%.15f, Z=%.15f\n", $p[0], $p[1], $p[2]);
