<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$jd = 2460323.951034;

echo "Testing Delta T for JD=$jd:\n";
$dt = swe_deltat_ex($jd, 0, $serr);
echo "Delta T = $dt days\n";
echo "Delta T = " . ($dt * 86400) . " seconds\n";
echo "\nExpected: ~69 seconds = 0.0008 days\n";
