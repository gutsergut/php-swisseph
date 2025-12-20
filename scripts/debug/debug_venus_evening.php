<?php
/**
 * Debug Venus evening first (TypeEvent=3)
 * Expected: JD 2452335.21615
 */

require_once __DIR__ . '/vendor/autoload.php';

putenv('DEBUG_HELIACAL=1');
swe_set_ephe_path('../eph/ephe');

$jd_ut_start = 2451544.5; // 2000-01-01
$dgeo = [13.4, 52.5, 100.0]; // Berlin
$datm = [1013.25, 15.0, 40.0, 0.0];
$dobs = [36.0, 1.0, 0.0, 1.0, 0.0, 0.0];

$dret = array_fill(0, 10, 0.0);
$serr = '';

echo "=== Venus Evening First (TypeEvent=3) Debug ===\n\n";
echo "Start JD: {$jd_ut_start} (2000-01-01)\n";
echo "Expected: JD 2452335.21615 (2002-03-16)\n";
echo "TypeEvent=3 means: evening first (needs SUPERIOR conjunction)\n\n";

$retval = swe_heliacal_ut(
    $jd_ut_start,
    $dgeo,
    $datm,
    $dobs,
    'venus',
    3, // SE_EVENING_FIRST
    2, // SEFLG_SWIEPH
    $dret,
    $serr
);

echo "\n=== RESULT ===\n";
if ($retval < 0) {
    echo "FAILED: {$serr}\n";
    echo "Last dret[0]: {$dret[0]}\n";
} else {
    echo "SUCCESS\n";
    printf("Found JD: %.5f\n", $dret[0]);
    printf("Expected:  %.5f\n", 2452335.21615);
    $diff = $dret[0] - 2452335.21615;
    printf("Difference: %.5f days (%.2f hours)\n", $diff, $diff * 24);

    if (abs($diff) > 1.0) {
        echo "\n⚠️  ERROR: Difference > 1 day!\n";

        // Check what conjunction type we got
        $x = array_fill(0, 6, 0.0);
        $serr2 = '';
        swe_calc($dret[0], 3, 2, $x, $serr2); // SE_VENUS
        printf("Planet distance at found time: %.3f AU\n", $x[2]);

        if ($x[2] > 0.8) {
            echo "→ This is SUPERIOR conjunction (correct for TypeEvent=3)\n";
        } else {
            echo "→ This is INFERIOR conjunction (WRONG for TypeEvent=3!)\n";
        }
    }
}

echo "\n";
