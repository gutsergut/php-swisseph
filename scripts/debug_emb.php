<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierPlanetCalculator;

$pobj = [0.0, 0.0, 0.0];
MoshierPlanetCalculator::moshplan2(2460477.0008007409, 2, $pobj);

echo "EMB lon (deg): " . rad2deg($pobj[0]) . "\n";
echo "EMB lat (deg): " . rad2deg($pobj[1]) . "\n";
echo "EMB rad (AU): " . $pobj[2] . "\n";
