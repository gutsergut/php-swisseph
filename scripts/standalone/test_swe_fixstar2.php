<?php

require_once __DIR__ . '/vendor/autoload.php';

\swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$star = 'Sirius';
$tjd = 2451770.41803;
$iflag = 2048; // SEFLG_EQUATORIAL
$xx = [];
$serr = '';

echo "Testing swe_fixstar2:\n";
$retval = \swe_fixstar2($star, $tjd, $iflag, $xx, $serr);

echo "retval: $retval\n";
echo "star: $star\n";
echo "serr: $serr\n";
echo "xx: " . json_encode($xx) . "\n";
if (isset($xx[0], $xx[1])) {
    echo "RA: " . $xx[0] . "°\n";
    echo "Dec: " . $xx[1] . "°\n";
}
