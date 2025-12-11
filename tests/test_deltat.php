<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2460408.5;
$serr = '';

echo "Ephemeris path: " . __DIR__ . '/../../eph/ephe' . "\n";

$dt_php = swe_deltat_ex($tjd, -1, $serr);
echo "PHP swe_deltat_ex($tjd, -1): " . ($dt_php * 86400) . " seconds\n";
echo "JD in days: $dt_php\n";

if ($serr) {
    echo "Error: $serr\n";
}
