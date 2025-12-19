<?php
/**
 * Test planet speeds accuracy against C reference (swetest64.exe)
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd_ut = 2451545.0; // J2000.0

// Reference values from swetest64.exe (1.1.2000 12:00 UT)
// Format: [lon, lat, dist, speed]
$reference = [
    Constants::SE_SUN     => ['name' => 'Sun',     'lon' => 280.3689187, 'lat' =>  0.0002274, 'dist' => 0.983327625,  'speed' =>  1.0194342],
    Constants::SE_MOON    => ['name' => 'Moon',    'lon' => 223.3237512, 'lat' =>  5.1707406, 'dist' => 0.00243907,   'speed' => 12.0213038],
    Constants::SE_MERCURY => ['name' => 'Mercury', 'lon' => 271.8892770, 'lat' => -0.9948286, 'dist' => 1.415469448,  'speed' =>  1.5562581],
    Constants::SE_VENUS   => ['name' => 'Venus',   'lon' => 241.5657884, 'lat' =>  2.0663491, 'dist' => 1.137579213,  'speed' =>  1.2090430],
    Constants::SE_MARS    => ['name' => 'Mars',    'lon' => 327.9633026, 'lat' => -1.0677855, 'dist' => 1.849687837,  'speed' =>  0.7756740],
    Constants::SE_JUPITER => ['name' => 'Jupiter', 'lon' =>  25.2530878, 'lat' => -1.2621908, 'dist' => 4.621175069,  'speed' =>  0.0407612],
    Constants::SE_SATURN  => ['name' => 'Saturn',  'lon' =>  40.3956635, 'lat' => -2.4448547, 'dist' => 8.652796338,  'speed' => -0.0199451],
    Constants::SE_URANUS  => ['name' => 'Uranus',  'lon' => 314.8091867, 'lat' => -0.6583325, 'dist' => 20.727171081, 'speed' =>  0.0503435],
    Constants::SE_NEPTUNE => ['name' => 'Neptune', 'lon' => 303.1930118, 'lat' =>  0.2349926, 'dist' => 31.024497132, 'speed' =>  0.0355701],
    Constants::SE_PLUTO   => ['name' => 'Pluto',   'lon' => 251.4547772, 'lat' => 10.8552337, 'dist' => 31.064362594, 'speed' =>  0.0351529],
];

echo "=== Planet Speeds Test ===\n";
echo "Date: J2000.0 (2000-01-01 12:00 UT)\n";
echo "Reference: swetest64.exe v2.10.03\n\n";

printf("%-10s %12s %12s %12s\n", "Planet", "PHP Speed", "C Speed", "Diff (\"/d)");
echo str_repeat("-", 50) . "\n";

$maxDiff = 0;
$allPass = true;

foreach ($reference as $ipl => $ref) {
    $xx = [];
    $serr = '';
    $ret = swe_calc_ut($jd_ut, $ipl, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);

    if ($ret < 0) {
        printf("%-10s ERROR: %s\n", $ref['name'], $serr);
        $allPass = false;
        continue;
    }

    $dSpeed = ($xx[3] - $ref['speed']) * 3600; // arcsec/day
    $maxDiff = max($maxDiff, abs($dSpeed));

    // Moon has higher tolerance due to its fast motion
    $tolerance = ($ipl == Constants::SE_MOON) ? 5.0 : 1.5;
    $pass = abs($dSpeed) < $tolerance;

    if (!$pass) $allPass = false;

    printf("%-10s %+12.7f %+12.7f %+9.2f %s\n",
           $ref['name'], $xx[3], $ref['speed'], $dSpeed,
           $pass ? "" : "FAIL");
}

echo str_repeat("-", 50) . "\n";
printf("Maximum difference: %.2f\"/day\n", $maxDiff);
printf("Result: %s\n", $allPass ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED");
