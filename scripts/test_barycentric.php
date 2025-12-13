<?php
/**
 * Test SEFLG_BARYCTR - barycentric coordinates
 * Reference: swetest64.exe -b25.2.2023 -ut12:00:00 -p0123456 -fPlbr -head -eswe -bary
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// JD for 25 Feb 2023 12:00 UT
$jd = 2460000.5;

// SEFLG_BARYCTR
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_BARYCTR;

// Reference values from swetest64.exe -bary
// Format: [lon, lat, dist]
$reference = [
    Constants::SE_SUN     => [182.8675816,  1.3530740, 0.008993982],
    Constants::SE_MOON    => [156.7009829,  0.0143605, 3416.80627],  // Note: Moon distance in different units?
    Constants::SE_MERCURY => [283.6398870, -5.8235092, 0.451980642],
    Constants::SE_VENUS   => [ 49.7054862, -1.5762437, 0.716476058],
    Constants::SE_MARS    => [114.8073990,  1.6775113, 1.626491406],
    Constants::SE_JUPITER => [ 17.6230947, -1.2931835, 4.942690244],
    Constants::SE_SATURN  => [327.9920461, -1.3959588, 9.814113003],
];

$planetNames = [
    Constants::SE_SUN     => 'Sun',
    Constants::SE_MOON    => 'Moon',
    Constants::SE_MERCURY => 'Mercury',
    Constants::SE_VENUS   => 'Venus',
    Constants::SE_MARS    => 'Mars',
    Constants::SE_JUPITER => 'Jupiter',
    Constants::SE_SATURN  => 'Saturn',
];

echo "Testing SEFLG_BARYCTR (barycentric coordinates)\n";
echo "JD = $jd (25 Feb 2023 12:00 UT)\n";
echo "Flag = SEFLG_SWIEPH | SEFLG_SPEED | SEFLG_BARYCTR\n\n";

$allPass = true;

foreach ($reference as $ipl => $ref) {
    $xx = [];
    $serr = '';

    $ret = \swe_calc($jd, $ipl, $iflag, $xx, $serr);

    if ($ret < 0) {
        echo "{$planetNames[$ipl]}: ERROR - $serr\n";
        $allPass = false;
        continue;
    }

    $lonDiff = abs($xx[0] - $ref[0]);
    $latDiff = abs($xx[1] - $ref[1]);
    $distDiff = abs($xx[2] - $ref[2]);

    // Tolerance: 0.01° for lon/lat, 0.001 AU for distance
    $lonOk = $lonDiff < 0.01;
    $latOk = $latDiff < 0.01;
    $distOk = $distDiff < 0.001 || ($ipl === Constants::SE_MOON && $distDiff < 1.0);  // Moon has larger tolerance

    $status = ($lonOk && $latOk && $distOk) ? 'PASS' : 'FAIL';
    if ($status === 'FAIL') $allPass = false;

    printf("%-8s: lon=%12.7f (ref=%12.7f, diff=%.7f) %s\n",
        $planetNames[$ipl], $xx[0], $ref[0], $lonDiff, $lonOk ? '✓' : '✗');
    printf("          lat=%12.7f (ref=%12.7f, diff=%.7f) %s\n",
        $xx[1], $ref[1], $latDiff, $latOk ? '✓' : '✗');
    printf("          dist=%12.9f (ref=%12.9f, diff=%.9f) %s\n",
        $xx[2], $ref[2], $distDiff, $distOk ? '✓' : '✗');
    echo "\n";
}

echo $allPass ? "\n=== ALL TESTS PASSED ===\n" : "\n=== SOME TESTS FAILED ===\n";
exit($allPass ? 0 : 1);
