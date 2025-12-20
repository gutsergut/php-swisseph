<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\State;

State::setEphePath(realpath(__DIR__ . '/../../eph/ephe'));

$xx = [];
$serr = null;
$retc = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
    2451545.0,
    Constants::SE_EARTH,
    Constants::SEFLG_XYZ | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_BARYCTR,
    $xx,
    $serr
);

echo "Return code: $retc\n";
echo "Count: " . count($xx) . "\n";
print_r($xx);
