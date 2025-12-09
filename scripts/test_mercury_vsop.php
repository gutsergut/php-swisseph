#!/usr/bin/env php
<?php
/**
 * Mercury VSOP87 quick test
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Swe\Planets\Vsop87Strategy;

// Сброс кэша
$reflection = new \ReflectionClass(Vsop87Strategy::class);
$prop = $reflection->getProperty('modelCache');
$prop->setAccessible(true);
$prop->setValue(null, []);

$ephe_path = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
swe_set_ephe_path($ephe_path);

$jd = 2451545.0; // J2000.0

// SWIEPH
$xx_sw = array_fill(0, 6, 0.0);
$serr = '';
PlanetsFunctions::calc($jd, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH, $xx_sw, $serr);

// VSOP87
$xx_vs = array_fill(0, 6, 0.0);
$serr = '';
PlanetsFunctions::calc($jd, Constants::SE_MERCURY, Constants::SEFLG_VSOP87, $xx_vs, $serr);

printf("Mercury J2000.0:\n");
printf("SWIEPH: lon=%11.6f° lat=%11.6f° dist=%.9f AU\n", $xx_sw[0], $xx_sw[1], $xx_sw[2]);
printf("VSOP87: lon=%11.6f° lat=%11.6f° dist=%.9f AU\n", $xx_vs[0], $xx_vs[1], $xx_vs[2]);
printf("DIFF:   lon=%8.3f\"     lat=%8.3f\"     dist=%10.1f km\n",
    abs($xx_sw[0] - $xx_vs[0]) * 3600,
    abs($xx_sw[1] - $xx_vs[1]) * 3600,
    abs($xx_sw[2] - $xx_vs[2]) * 149597870.7
);
