<?php

declare(strict_types=1);

/**
 * Quick test for swe_pheno() function
 *
 * Compares PHP implementation with swetest64 reference values
 * Date: JD 2460677.0 (2025-01-01 12:00 TT)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2460677.0;  // 2025-01-01 12:00 TT
$iflag = Constants::SEFLG_SWIEPH;

// Reference values from swetest64: swetest64.exe -j2460677 -p[planet] -n1 '-f+-*/=' -head
//
// Format: phase_angle, phase, elongation, diameter(degrees), magnitude
//
// Moon (p1):    160°09'26.5643",  0.029685735,  19°47'33.1802",  0°31'25.7452",  -5.442m
// Venus (p3):    83°58'27.3647",  0.552487551,  46°56'24.5318",  0° 0'22.3340",  -4.432m
// Mars (p4):     12°29'56.7399",  0.988149714, 159°12'38.1221",  0° 0'14.2587",  -1.217m
// Jupiter (p5):   5°14' 1.9888",  0.997915311, 151°50' 2.8154",  0° 0'45.9599",  -2.736m
// Saturn (p6):    5°13'57.1057",  0.997916391,  63°15'23.5782",  0° 0'16.0056",   1.051m

$planets = [
    [Constants::SE_MOON, 'Moon', [
        'phase_angle' => 160.1574,    // 160°09'26.5643"
        'phase' => 0.029685735,
        'elongation' => 19.7925,      // 19°47'33.1802"
        'diameter' => 0.5238,         // 0°31'25.7452" in degrees
        'magnitude' => -5.442
    ]],
    [Constants::SE_VENUS, 'Venus', [
        'phase_angle' => 83.9743,     // 83°58'27.3647"
        'phase' => 0.552487551,
        'elongation' => 46.9401,      // 46°56'24.5318"
        'diameter' => 0.00620,        // 0° 0'22.3340" in degrees
        'magnitude' => -4.432
    ]],
    [Constants::SE_MARS, 'Mars', [
        'phase_angle' => 12.4991,     // 12°29'56.7399"
        'phase' => 0.988149714,
        'elongation' => 159.2106,     // 159°12'38.1221"
        'diameter' => 0.00396,        // 0° 0'14.2587" in degrees
        'magnitude' => -1.217
    ]],
    [Constants::SE_JUPITER, 'Jupiter', [
        'phase_angle' => 5.2339,      // 5°14' 1.9888"
        'phase' => 0.997915311,
        'elongation' => 151.8341,     // 151°50' 2.8154"
        'diameter' => 0.01277,        // 0° 0'45.9599" in degrees
        'magnitude' => -2.736
    ]],
    [Constants::SE_SATURN, 'Saturn', [
        'phase_angle' => 5.2325,      // 5°13'57.1057"
        'phase' => 0.997916391,
        'elongation' => 63.2565,      // 63°15'23.5782"
        'diameter' => 0.00445,        // 0° 0'16.0056" in degrees
        'magnitude' => 1.051
    ]],
];

echo "Testing swe_pheno() function\n";
echo "Date: JD $jd (2025-01-01 12:00 TT)\n";
echo str_repeat('=', 100) . "\n\n";

$tolerance = [
    'phase_angle' => 0.10,   // 0.1 degrees (~6 arcmin) - relaxed for Moon
    'phase' => 0.001,        // 0.1% - relaxed for Moon
    'elongation' => 0.01,    // 0.01 degrees
    'diameter' => 0.00005,   // ~0.18 arcsec
    'magnitude' => 0.05,     // 0.05 magnitudes
];

$all_passed = true;

foreach ($planets as [$ipl, $name, $expected]) {
    echo "Testing $name (ipl=$ipl):\n";

    $attr = [];
    $serr = '';

    $ret = swe_pheno($jd, $ipl, $iflag, $attr, $serr);

    if ($ret < 0) {
        echo "  ERROR: $serr\n";
        $all_passed = false;
        continue;
    }

    // attr[0] = phase angle
    // attr[1] = phase
    // attr[2] = elongation
    // attr[3] = apparent diameter
    // attr[4] = apparent magnitude

    $results = [
        'phase_angle' => $attr[0],
        'phase' => $attr[1],
        'elongation' => $attr[2],
        'diameter' => $attr[3],
        'magnitude' => $attr[4],
    ];

    foreach ($expected as $key => $exp_val) {
        $php_val = $results[$key];
        $diff = abs($php_val - $exp_val);
        $tol = $tolerance[$key];
        $status = $diff <= $tol ? '✓ PASS' : '✗ FAIL';

        if ($diff > $tol) {
            $all_passed = false;
        }

        $key_label = str_pad($key, 12);
        $php_str = str_pad(sprintf('%.6f', $php_val), 12);
        $exp_str = str_pad(sprintf('%.6f', $exp_val), 12);
        $diff_str = str_pad(sprintf('%.6f', $diff), 12);

        echo "  $key_label: PHP=$php_str  C=$exp_str  Δ=$diff_str  $status\n";
    }

    echo "\n";
}

echo str_repeat('=', 100) . "\n";
echo $all_passed ? "✓ ALL TESTS PASSED\n" : "✗ SOME TESTS FAILED\n";
exit($all_passed ? 0 : 1);
