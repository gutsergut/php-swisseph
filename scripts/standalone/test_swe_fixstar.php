<?php

require_once __DIR__ . '/vendor/autoload.php';

\swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$star = 'Sirius';
$tjd = 2451770.41803;
$iflag = 2048; // SEFLG_EQUATORIAL = 2*1024
$xx = [];
$serr = '';

$retval = \swe_fixstar($star, $tjd, $iflag, $xx, $serr);

echo "retval: $retval\n";
echo "star: $star\n";
echo "serr: $serr\n";
echo "xx: " . json_encode($xx) . "\n";
echo "RA: " . ($xx[0] ?? 'N/A') . "°\n";
echo "Dec: " . ($xx[1] ?? 'N/A') . "°\n";
