<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

putenv('DEBUG_RISESET=1');

$jd_ut = 2460676.5;  // 2025-01-01 00:00 UT
$geopos = [13.41, 52.52, 0.0];  // Berlin

echo "Testing Venus rise/set algorithm selection\n";
echo "Berlin: lon={$geopos[0]}°, lat={$geopos[1]}°\n";
echo "Date: 2025-01-01 00:00 UT (JD $jd_ut)\n\n";

// Calculate rise
$serr = '';
$tret = 0.0;

$result = swe_rise_trans(
    $jd_ut,
    Constants::SE_VENUS,
    '',
    Constants::SEFLG_SWIEPH,
    Constants::SE_CALC_RISE,
    $geopos,
    1013.25,
    15.0,
    null,
    $tret,
    $serr
);

if ($result < 0) {
    echo "ERROR: $serr\n";
} else {
    $dayFrac = fmod($tret + 0.5, 1.0);
    $seconds = $dayFrac * 86400.0;
    $h = floor($seconds / 3600);
    $m = floor(($seconds - $h * 3600) / 60);
    $s = $seconds - $h * 3600 - $m * 60;

    echo "\nRise JD: " . number_format($tret, 6) . "\n";
    printf("Rise time: %02d:%02d:%06.3f UT\n", $h, $m, $s);
}

echo "\nExpected from swetest64: 05:41:57.5 UT\n";
