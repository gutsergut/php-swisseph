<?php
// Quick Moon test
require_once __DIR__ . '/../vendor/autoload.php';
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
$xx = [];
$serr = '';
$ret = swe_calc_ut(2451545.0, 1, 2, $xx, $serr); // Moon, SEFLG_SWIEPH
echo "Return: $ret\n";
echo "Error: $serr\n";
print_r($xx);
