<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

$geopos = [13.4, 52.5, 0.0];
$atpress = 1013.25;
$attemp = 15.0;

// Set topocentric position (REQUIRED!)
swe_set_topo($geopos[0], $geopos[1], $geopos[2]);

// Check Moon height at several times around 14:00-17:00
// Remember: JD starts at noon! JD 2460677.0 = 12:00 UT
// So 14:00 UT = 12:00 + 2hrs = JD 2460677.0 + 2/24 = JD 2460677.083333
$times = [
    ['jd' => 2460677.0 + (1.0/24.0), 'label' => '13:00'],
    ['jd' => 2460677.0 + (2.0/24.0), 'label' => '14:00'],
    ['jd' => 2460677.0 + (3.0/24.0), 'label' => '15:00'],
    ['jd' => 2460677.0 + (4.0/24.0), 'label' => '16:00'],
    ['jd' => 2460677.0 + (4.4/24.0), 'label' => '16:24'],
    ['jd' => 2460677.0 + (4.5/24.0), 'label' => '16:30'],
    ['jd' => 2460677.0 + (5.0/24.0), 'label' => '17:00'],
];

echo "Moon altitude at Berlin (13.4°E, 52.5°N) on 2025-01-01:\n\n";

foreach ($times as $time_data) {
    $jd = $time_data['jd'];
    $time_str = $time_data['label'];

    $te = $jd + swe_deltat_ex($jd, Constants::SEFLG_SWIEPH, $serr);
    $xc = [];
    swe_calc($te, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR, $xc, $serr);

    $ah = [];
    // First get azimuth/altitude
    swe_azalt($jd, Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $ah, $serr);
    // Apply refraction with round-trip (same as RiseSetFunctions)
    swe_azalt_rev($jd, Constants::SE_HOR2EQU, $ah, $geopos, $xc, $serr);
    swe_azalt($jd, Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $ah, $serr);

    printf("%s UT (JD %.8f): altitude = %+7.3f° (refracted)  azimuth = %6.2f°\n",
        $time_str, $jd, $ah[2], $ah[0]);
}echo "\nLooking for exact time when altitude crosses 0° (horizon)...\n";

// Binary search for SET time between 14:00 and 17:00
$t1 = 2460677.0 + (2.0/24.0); // 14:00
$t2 = 2460677.0 + (5.0/24.0); // 17:00

for ($i = 0; $i < 25; $i++) {
    $t_mid = ($t1 + $t2) / 2.0;

    $te = $t_mid + swe_deltat_ex($t_mid, Constants::SEFLG_SWIEPH, $serr);
    $xc = [];
    swe_calc($te, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR, $xc, $serr);

    $ah = [];
    swe_azalt($t_mid, Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $ah, $serr);
    // Apply refraction with round-trip
    swe_azalt_rev($t_mid, Constants::SE_HOR2EQU, $ah, $geopos, $xc, $serr);
    swe_azalt($t_mid, Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $ah, $serr);

    if ($ah[2] > 0) {
        $t1 = $t_mid;
    } else {
        $t2 = $t_mid;
    }
}

$t_set = ($t1 + $t2) / 2.0;
$date = swe_revjul($t_set, Constants::SE_GREG_CAL);
printf("\nMoon SET (calculated): JD %.8f = %02d:%02d:%05.2f UT\n",
    $t_set,
    (int)$date['ut'],
    (int)((($date['ut'] - (int)$date['ut']) * 60)),
    ((($date['ut'] - (int)$date['ut']) * 60) - (int)(($date['ut'] - (int)$date['ut']) * 60)) * 60
);

echo "\nExpected from swetest64: 16:26:27.6 UT\n";
