<?php
require 'vendor/autoload.php';
use Swisseph\Constants;

// Only SWIEPH test
swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);
printf("SWIEPH only: lon=%.6f, lat=%.6f, dist=%.6f AU\n", $xx[0], $xx[1], $xx[2]);
