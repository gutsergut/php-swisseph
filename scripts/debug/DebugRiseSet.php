<?php

/**
 * Debug RiseSetFunctions - understand why it returns wrong events
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

$jd_ut = 2460677.0;  // 2025-01-01 12:00 UT
$geopos = [13.4, 52.5, 0.0];  // Berlin
$atpress = 1013.25;
$attemp = 15.0;

echo "=== Debug RiseSetFunctions ===\n\n";
echo "Looking for Moon SET after JD $jd_ut (2025-01-01 12:00 UT)\n\n";

// Moon SET
$tset = 0.0;
$serr = null;
$ret = swe_rise_trans(
    $jd_ut,
    Constants::SE_MOON,
    null,
    Constants::SEFLG_SWIEPH,
    Constants::SE_CALC_SET,
    $geopos,
    $atpress,
    $attemp,
    null,
    $tset,
    $serr
);

echo "Result: ret=$ret\n";
if ($ret === 0) {
    $date = swe_revjul($tset, Constants::SE_GREG_CAL);
    printf("Moon SET found at JD %.7f\n", $tset);
    printf("Date: %04d-%02d-%02d %02d:%02d:%05.2f UT\n",
        $date['y'], $date['m'], $date['d'],
        (int)$date['ut'],
        (int)(($date['ut'] - (int)$date['ut']) * 60),
        ((($date['ut'] - (int)$date['ut']) * 60) - (int)(($date['ut'] - (int)$date['ut']) * 60)) * 60
    );

    if ($tset > $jd_ut) {
        echo "✅ Event is AFTER tjd_ut (correct)\n";
    } else {
        echo "❌ Event is BEFORE tjd_ut (WRONG!)\n";
    }
} else {
    echo "ERROR: $serr\n";
}

echo "\n--- Expected (swetest64.exe) ---\n";
echo "Moon SET: 01.01.2025 16:26:27.6\n";
echo "This is JD 2460677.0 + (16.441/24) = JD 2460677.6850787\n\n";

// Now let's test Mars RISE
echo "=== Looking for Mars RISE after JD $jd_ut ===\n\n";

$trise = 0.0;
$serr = null;
$ret = swe_rise_trans(
    $jd_ut,
    Constants::SE_MARS,
    null,
    Constants::SEFLG_SWIEPH,
    Constants::SE_CALC_RISE,
    $geopos,
    $atpress,
    $attemp,
    null,
    $trise,
    $serr
);

echo "Result: ret=$ret\n";
if ($ret === 0) {
    $date = swe_revjul($trise, Constants::SE_GREG_CAL);
    printf("Mars RISE found at JD %.7f\n", $trise);
    printf("Date: %04d-%02d-%02d %02d:%02d:%05.2f UT\n",
        $date['y'], $date['m'], $date['d'],
        (int)$date['ut'],
        (int)(($date['ut'] - (int)$date['ut']) * 60),
        ((($date['ut'] - (int)$date['ut']) * 60) - (int)(($date['ut'] - (int)$date['ut']) * 60)) * 60
    );

    if ($trise > $jd_ut) {
        echo "✅ Event is AFTER tjd_ut (correct)\n";
    } else {
        echo "❌ Event is BEFORE tjd_ut (WRONG!)\n";
    }
} else {
    echo "ERROR: $serr\n";
}

echo "\n--- Expected (swetest64.exe) ---\n";
echo "Mars RISE: 01.01.2025 16:15:49.4\n";
echo "This is JD 2460677.0 + (16.264/24) = JD 2460677.6777199\n";
