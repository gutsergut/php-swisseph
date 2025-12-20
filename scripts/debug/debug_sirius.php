<?php
/**
 * Debug Sirius heliacal rising
 */

require_once __DIR__ . '/vendor/autoload.php';

putenv('DEBUG_HELIACAL=1');
swe_set_ephe_path('../eph/ephe');

$dgeo = [13.4, 52.5, 100.0]; // Berlin
$datm = [1013.25, 15.0, 40.0, 0.0];
$dobs = [36.0, 1.0, 0.0, 1.0, 0.0, 0.0];

echo "=== Sirius Heliacal Rising Debug ===\n\n";

// Test 1: Heliacal rising
$jd_start = 2451727.5; // 2000-07-01
$dret = array_fill(0, 10, 0.0);
$serr = '';

echo "Test 1: Heliacal Rising (TypeEvent=1)\n";
echo "Start JD: {$jd_start} (2000-07-01)\n";
echo "Expected: JD 2451784.64528 (2000-08-28)\n\n";

$retval = swe_heliacal_ut(
    $jd_start,
    $dgeo,
    $datm,
    $dobs,
    'Sirius',
    1, // SE_HELIACAL_RISING
    2, // SEFLG_SWIEPH
    $dret,
    $serr
);

if ($retval < 0) {
    echo "FAILED: {$serr}\n";
} else {
    printf("Found JD: %.5f\n", $dret[0]);
    printf("Expected:  %.5f\n", 2451784.64528);
    $diff = $dret[0] - 2451784.64528;
    printf("Difference: %.5f days (%.2f hours)\n\n", $diff, $diff * 24);
}

// Test 2: Heliacal setting
$jd_start2 = 2451910.5; // 2001-01-01
$dret2 = array_fill(0, 10, 0.0);
$serr2 = '';

echo "\nTest 2: Heliacal Setting (TypeEvent=2)\n";
echo "Start JD: {$jd_start2} (2001-01-01)\n";
echo "Expected: JD 2452029.29441 (2001-04-29)\n\n";

$retval2 = swe_heliacal_ut(
    $jd_start2,
    $dgeo,
    $datm,
    $dobs,
    'Sirius',
    2, // SE_HELIACAL_SETTING
    2, // SEFLG_SWIEPH
    $dret2,
    $serr2
);

if ($retval2 < 0) {
    echo "FAILED: {$serr2}\n";
} else {
    printf("Found JD: %.5f\n", $dret2[0]);
    printf("Expected:  %.5f\n", 2452029.29441);
    $diff2 = $dret2[0] - 2452029.29441;
    printf("Difference: %.5f days (%.2f hours)\n", $diff2, $diff2 * 24);
}

echo "\n";
