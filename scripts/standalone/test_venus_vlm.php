<?php

require_once __DIR__ . '/vendor/autoload.php';

\swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$dgeo = [13.4, 52.5, 100.0];
\swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);

$datm = [1013.25, 15.0, 40.0, 0.0];
$dobs = [36.0, 1.0, 0.0, 1.0, 0.0, 0.0];
$jd_ut = 2451545.0; // J2000
$darr = array_fill(0, 10, 0.0);
$serr = '';

echo "Testing vis_limit_mag for Venus at J2000:\n";
echo "JD: $jd_ut\n";
echo "Location: {$dgeo[0]}°E, {$dgeo[1]}°N\n\n";

$retval = \swe_vis_limit_mag($jd_ut, $dgeo, $datm, $dobs, 'venus', 2 /* SEFLG_SWIEPH */, $darr, $serr);

if ($retval < 0) {
    echo "ERROR: $serr\n";
} else {
    echo "VLM: {$darr[0]}\n";
    echo "Object Alt: {$darr[1]}°\n";
    echo "Object Azi: {$darr[2]}°\n";
    echo "Sun Alt: {$darr[3]}°\n";
    echo "Sun Azi: {$darr[4]}°\n";
    echo "Moon Alt: {$darr[5]}°\n";
    echo "Moon Azi: {$darr[6]}°\n";
    echo "Object Mag: {$darr[7]}\n";
}
