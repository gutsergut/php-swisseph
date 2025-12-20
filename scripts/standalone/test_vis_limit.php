<?php
/**
 * Test vis_limit_mag at specific dates to compare with C
 */

require_once __DIR__ . '/vendor/autoload.php';

\swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$dgeo = [13.4, 52.5, 100.0];
\swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);

$datm = [1013.25, 15.0, 40.0, 0.0];
$dobs = [36.0, 1.0, 0.0, 1.0, 0.0, 0.0];
$ObjectName = 'Sirius';
$helflag = 2; // SEFLG_SWIEPH

// Test at critical JDs from debug output
$test_dates = [
    ['jd' => 2451770.41803, 'desc' => 'Cosmic rising (PHP)'],
    ['jd' => 2451784.64528, 'desc' => 'Expected heliacal rising (C)'],
    ['jd' => 2451788.61211, 'desc' => 'Actual heliacal rising (PHP)'],
];

echo "Testing vis_limit_mag for Sirius at critical dates:\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($test_dates as $test) {
    $jd = $test['jd'];
    $desc = $test['desc'];

    echo sprintf("JD %.5f - %s\n", $jd, $desc);

    // Get Sun rise time
    $tret = null;
    $serr = '';
    $retval = \swe_rise_trans($jd, 0 /* SE_SUN */, null, 2 /* SEFLG_SWIEPH */, 1 /* SE_CALC_RISE */, $dgeo, 1013.25, 15.0, 0.0, $tret, $serr);

    if ($retval < 0) {
        echo "  Sun rise error: $serr\n\n";
        continue;
    }

    echo sprintf("  Sun rise at: JD %.5f\n", $tret);

    // Check visibility at sunrise
    $darr = array_fill(0, 10, 0.0);
    $retval = \swe_vis_limit_mag($tret, $dgeo, $datm, $dobs, $ObjectName, $helflag, $darr, $serr);

    if ($retval < 0) {
        echo "  vis_limit_mag error: $serr\n";
    } else {
        $vlm = $darr[0]; // Visual limiting magnitude
        $obm = $darr[7]; // Object's magnitude
        $vdelta = $vlm - $obm;

        echo sprintf("  VLM: %.3f, Object mag: %.3f, Delta: %.3f\n", $vlm, $obm, $vdelta);

        if ($vdelta > 0) {
            echo "  Status: VISIBLE (vdelta > 0)\n";
        } else {
            echo "  Status: NOT VISIBLE (vdelta ≤ 0)\n";
        }
    }

    echo "\n";
}
