<?php
/**
 * Debug Tromsø polar test for Moon rise/set
 * Tromsø: 69.65°N, 18.96°E
 * Date: 2025-06-15 (summer - Moon circumpolar?)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd_ut = 2460841.5;  // 2025-06-15 00:00 UT
$geopos = [18.96, 69.65, 0.0];  // Tromsø (lon, lat, alt)

echo "Testing Moon rise/set for Tromsø (polar latitude)\n";
echo "Location: lon={$geopos[0]}°, lat={$geopos[1]}°\n";
echo "Date: 2025-06-15 00:00 UT (JD $jd_ut)\n";
echo "Expected: Slow algorithm (lat 69.65° > 60°)\n\n";

// Calculate set
$serr = '';
$tret = 0.0;

$result = swe_rise_trans(
    $jd_ut,
    Constants::SE_MOON,
    '',
    Constants::SEFLG_SWIEPH,
    Constants::SE_CALC_SET,
    $geopos,
    1013.25,
    15.0,
    null,
    $tret,
    $serr
);

if ($result < 0) {
    echo "ERROR: Return code = $result\n";
    echo "Error message: '$serr'\n";
} else {
    $dayFrac = fmod($tret + 0.5, 1.0);
    $seconds = $dayFrac * 86400.0;
    $h = floor($seconds / 3600);
    $m = floor(($seconds - $h * 3600) / 60);
    $s = $seconds - $h * 3600 - $m * 60;

    echo "Set JD: " . number_format($tret, 6) . "\n";
    printf("Set time: %02d:%02d:%06.3f UT\n", $h, $m, $s);
}

echo "\nExpected from swetest64: check with swetest...\n";
