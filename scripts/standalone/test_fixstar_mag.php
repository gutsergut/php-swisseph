<?php

require_once __DIR__ . '/vendor/autoload.php';

\swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$star = 'Sirius';
$dmag = 0.0;
$serr = '';

$retval = \swe_fixstar_mag($star, $dmag, $serr);

echo "swe_fixstar_mag test:\n";
echo "Star: $star\n";
echo "Return value: $retval\n";
echo "Magnitude: $dmag\n";
echo "Error: $serr\n";
echo "Expected magnitude: -1.46\n";
