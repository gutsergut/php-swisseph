<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$star = 'Sirius';
$xx = [];
$serr = '';
$iflag = 2 | 2048; // SEFLG_SWIEPH | SEFLG_EQUATORIAL

echo "Testing equatorial coordinates...\n";
$r = swe_fixstar2($star, 2451545.0, $iflag, $xx, $serr);

echo "Return code: $r\n";
echo "Error: '$serr'\n";
echo "Star: $star\n";
print_r($xx);
