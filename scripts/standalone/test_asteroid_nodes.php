<?php
/**
 * Test asteroid nodes against C reference values
 */
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path('C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe');

$jd = 2451545.0; // J2000.0
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$method = Constants::SE_NODBIT_OSCU; // osculating elements

// C Reference values (from test_asteroid_nodes.c):
$cRef = [
    'Jupiter' => [
        'ipl' => 5,
        'asc' => 100.5196455351,
        'desc' => 280.4643199679,
        'peri' => 4.1628141639,
        'aphe' => 205.5687865618,
    ],
    'Eros (10433)' => [
        'ipl' => Constants::SE_AST_OFFSET + 433,
        'asc' => 295.9093118946,
        'desc' => 183.9605090610,
        'peri' => 182.2736734692,
        'aphe' => 295.0448820936,
    ],
    'Ceres (10001)' => [
        'ipl' => Constants::SE_AST_OFFSET + 1,
        'asc' => 69.7743434110,
        'desc' => 265.6013286555,
        'peri' => 176.5098178610,
        'aphe' => 321.4370783254,
    ],
];

$allPassed = true;
$tolerance = 0.0001; // 0.36 arcsec

foreach ($cRef as $name => $ref) {
    echo "=== Testing $name (ipl={$ref['ipl']}) ===" . PHP_EOL;

    $xnasc = $xndsc = $xperi = $xaphe = [];
    $serr = null;

    $ret = swe_nod_aps($jd, $ref['ipl'], $iflag, $method, $xnasc, $xndsc, $xperi, $xaphe, $serr);

    if ($ret < 0) {
        echo "ERROR: $serr" . PHP_EOL;
        $allPassed = false;
        continue;
    }

    $tests = [
        ['name' => 'Ascending Node', 'php' => $xnasc[0], 'c' => $ref['asc']],
        ['name' => 'Descending Node', 'php' => $xndsc[0], 'c' => $ref['desc']],
        ['name' => 'Perihelion', 'php' => $xperi[0], 'c' => $ref['peri']],
        ['name' => 'Aphelion', 'php' => $xaphe[0], 'c' => $ref['aphe']],
    ];

    foreach ($tests as $t) {
        $delta = abs($t['php'] - $t['c']);
        $status = $delta < $tolerance ? 'PASS' : 'FAIL';
        if ($status === 'FAIL') $allPassed = false;

        printf("  %s: PHP=%.10f, C=%.10f, delta=%.10f deg (%.4f\") [%s]" . PHP_EOL,
            $t['name'], $t['php'], $t['c'], $delta, $delta * 3600, $status);
    }
    echo PHP_EOL;
}

echo $allPassed ? "=== ALL TESTS PASSED ===" : "=== SOME TESTS FAILED ===" . PHP_EOL;
exit($allPassed ? 0 : 1);
