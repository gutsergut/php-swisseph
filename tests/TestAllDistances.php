<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

$jd_ut = 2460677.0;
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH, $serr);

echo "=== Test distances for all planets ===\n\n";

$bodies = [
    [Constants::SE_SUN, 'Sun'],
    [Constants::SE_MOON, 'Moon'],
    [Constants::SE_MERCURY, 'Mercury'],
    [Constants::SE_VENUS, 'Venus'],
    [Constants::SE_MARS, 'Mars'],
    [Constants::SE_JUPITER, 'Jupiter'],
    [Constants::SE_SATURN, 'Saturn'],
];

$expected = [
    'Sun' => 0.983,  // ~147M km (Earth-Sun distance varies)
    'Moon' => 0.00257,  // ~384K km
    'Mercury' => 1.30,  // varies widely
    'Venus' => 0.70,    // varies
    'Mars' => 1.50,     // varies
    'Jupiter' => 5.20,  // varies
    'Saturn' => 9.50,   // varies
];

foreach ($bodies as [$ipl, $name]) {
    $xc = [];
    $ret = swe_calc($jd_et, $ipl, Constants::SEFLG_SWIEPH, $xc, $serr);

    if ($ret < 0) {
        printf("%-10s ERROR: %s\n", $name, $serr);
    } else {
        $dist_au = $xc[2];
        $dist_km = $dist_au * 149597870.7;
        $exp = $expected[$name] ?? null;
        $status = '';

        if ($exp !== null) {
            $ratio = $dist_au / $exp;
            if ($ratio > 0.5 && $ratio < 2.0) {
                $status = '✅';
            } else {
                $status = '❌';
            }
        }

        printf("%-10s Distance = %.9f AU (%.0f km) %s\n", $name, $dist_au, $dist_km, $status);
        if ($exp !== null) {
            printf("           Expected ~%.3f AU\n", $exp);
        }
    }
}

printf("\n🔍 KEY FINDING:\n");
printf("If Moon shows Sun-like distance (~0.98 AU), there's a body index mixup!\n");
