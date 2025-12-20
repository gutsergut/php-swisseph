<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/functions.php';

use Swisseph\Constants;

echo "=== PHP vs C Heliacal Comparison ===\n\n";

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../eph/ephe');

// Test parameters (matching C test)
$tjd_start = 2451697.5; // 2000-06-01
$dgeo = [13.4, 52.5, 100.0]; // Berlin
$datm = [1013.25, 15.0, 40.0, 0.0];
$dobs = [36.0, 1.0, 0, 1.0, 0.0, 0.0];

$dret = array_fill(0, 10, 0.0);
$serr = '';

echo "PHP: Calling swe_heliacal_ut...\n";
$result = swe_heliacal_ut(
    $tjd_start,
    $dgeo,
    $datm,
    $dobs,
    'venus',
    1, // heliacal rising
    Constants::SEFLG_SWIEPH,
    $dret,
    $serr
);

echo "PHP: Return value: $result\n";
if ($serr !== '') {
    echo "PHP: Message: $serr\n";
}
if ($result >= 0) {
    printf("PHP: Event JD: %.8f\n", $dret[0]);
    printf("PHP: Expected:  2452004.66233000\n");
    printf("PHP: Diff: %.6f days\n", $dret[0] - 2452004.66233);
} else {
    printf("PHP: Last dret[0]: %.8f\n", $dret[0]);
}

echo "\n--- Comparison ---\n";
echo "C:   Event JD: 2452004.66232509 (from test_simple.exe)\n";
printf("PHP: Event JD: %.8f\n", $dret[0]);
if ($result >= 0) {
    printf("Difference: %.8f days (%.2f seconds)\n",
        abs($dret[0] - 2452004.66232509),
        abs($dret[0] - 2452004.66232509) * 86400);
}

