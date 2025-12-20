<?php
require_once __DIR__ . '/vendor/autoload.php';

swe_set_ephe_path('../eph/ephe');
$x = [];
$s = '';
$r = swe_calc(2451696.07860, 3, 2|256, $x, $s);
echo 'Result: ' . $r . PHP_EOL;
echo 'Error: ' . $s . PHP_EOL;
print_r($x);
