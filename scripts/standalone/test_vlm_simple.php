<?php
// Test VLM calculation at different times around the event
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$dgeo = [13.4, 52.5, 100.0];
$datm = [1013.25, 15.0, 40.0, 0.0];
$dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

$base_jd = 2452004.66233; // Event time: 2001-04-05 03:53:44 UT

echo "=== VLM around heliacal rising event ===\n\n";
printf("Event JD: %.5f (2001-04-05 03:53:44 UT)\n\n", $base_jd);

$test_times = [
    2452004.56233, // 2.4h before
    2452004.61233, // 1.2h before
    2452004.66233, // at event
    2452004.71233, // 1.2h after
    2452004.76233, // 2.4h after
];

foreach ($test_times as $jd) {
    $darr = array_fill(0, 10, 0.0);
    $serr = '';

    $result = swe_vis_limit_mag($jd, $dgeo, $datm, $dobs, 'Venus', Constants::SEFLG_SWIEPH, $darr, $serr);

    $offset_hrs = ($jd - $base_jd) * 24;
    printf("JD %.5f (%+.1fh):\n", $jd, $offset_hrs);
    printf("  Ret: %d %s\n", $result, $serr !== '' ? "($serr)" : '');
    printf("  VLM:    %8.4f\n", $darr[0]);
    printf("  Magn:   %8.4f\n", $darr[7]);
    printf("  vdelta: %8.4f %s\n\n", $darr[0] - $darr[7],
        ($darr[0] - $darr[7] > 0) ? '✓ VIS' : '✗ NO');
}
