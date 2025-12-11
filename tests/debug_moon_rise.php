<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Berlin coordinates
$geopos = [13.41, 52.52, 0.0];

// 2025-01-01 00:00:00 UT
$jd_ut = swe_julday(2025, 1, 1, 0.0, Constants::SE_GREG_CAL);

echo "JD for 2025-01-01 00:00:00 UT: " . number_format($jd_ut, 6) . "\n";
echo "Expected: 2460676.500000\n\n";

// Calculate Moon rise
$serr = '';
$tret_rise = 0.0;

$result = swe_rise_trans(
    $jd_ut,
    Constants::SE_MOON,
    '',
    Constants::SEFLG_SWIEPH,
    Constants::SE_CALC_RISE | Constants::SE_BIT_DISC_CENTER,
    $geopos,
    1013.25,
    15.0,
    null,
    $tret_rise,
    $serr
);

if ($result < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Rise JD from PHP: " . number_format($tret_rise, 6) . "\n";

// Convert to calendar
$cal = swe_revjul($tret_rise, Constants::SE_GREG_CAL);
$hr = $cal['ut'];
$mn = ($hr - floor($hr)) * 60;
$sc = ($mn - floor($mn)) * 60;

printf("Rise time: %02d.%02d.%04d %02d:%02d:%05.2f UT\n",
    $cal['d'], $cal['m'], $cal['y'], (int)$hr, (int)$mn, $sc);

echo "\nExpected from swetest64:\n";
echo "rise  1.01.2025 08:58:14.8\n";
echo "JD:   2460676.873782\n";

// Calculate expected JD
$expected_jd = swe_julday(2025, 1, 1, 8 + 58/60 + 14.8/3600, Constants::SE_GREG_CAL);
echo "\nExpected JD calculated: " . number_format($expected_jd, 6) . "\n";
echo "Difference: " . (($tret_rise - $expected_jd) * 86400) . " seconds\n";
