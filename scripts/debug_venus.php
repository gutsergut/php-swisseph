<?php

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$xx = [];
$serr = null;
$ret = swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xx, $serr);

echo "Venus at JD 2451545.0:\n";
echo "  ret: $ret\n";
echo "  lon: {$xx[0]} deg\n";
echo "  lat: {$xx[1]} deg\n";
echo "  dist: {$xx[2]} AU\n";
echo "  lon_speed: {$xx[3]} deg/day\n";
echo "  lat_speed: {$xx[4]} deg/day\n";
echo "  dist_speed: {$xx[5]} AU/day\n";
echo "  serr: $serr\n";
