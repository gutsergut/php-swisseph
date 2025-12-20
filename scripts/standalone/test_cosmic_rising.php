<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Domain\Heliacal\HeliacalAscensional;

\swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$dgeo = [13.4, 52.5, 100.0]; // Amsterdam
\swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);

$ObjectName = 'Sirius';
$TypeEvent = 1; // morning first
$helflag = 0;
$tjd_start = 2451696.0; // 2000-07-01
$tjd_cosmic = 0.0;
$serr = '';

$retval = HeliacalAscensional::get_asc_obl_with_sun(
    $tjd_start,
    -1, // Star
    $ObjectName,
    $helflag,
    $TypeEvent,
    0, // dperiod
    $dgeo,
    $tjd_cosmic,
    $serr
);

if ($retval !== 0 /* Constants::OK */) {
    echo "Error: $serr\n";
    exit(1);
}

echo "PHP cosmic rising JD: " . number_format($tjd_cosmic, 5) . "\n";
echo "C cosmic rising JD (from debug): 2451770.41803\n";
echo "Difference: " . number_format($tjd_cosmic - 2451770.41803, 5) . " days\n";
