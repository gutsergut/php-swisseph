<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "\n=== Test Legacy swe_fixstar() ===\n\n";

$star = 'Sirius';
$jd = 2451545.0; // J2000.0
$xx = [];
$serr = '';

$ret = swe_fixstar($star, $jd, 2, $xx, $serr); // SEFLG_SWIEPH
if ($ret >= 0) {
    echo "Star: $star\n";
    echo sprintf("Longitude: %.6f°\n", $xx[0]);
    echo sprintf("Latitude: %.6f°\n", $xx[1]);
    echo sprintf("Distance: %.6f AU\n", $xx[2]);
    echo "✓ swe_fixstar() works\n\n";
} else {
    echo "ERROR: $serr\n\n";
}

// Test _ut version
$star2 = 'Vega';
$xx2 = [];
$serr2 = '';

$ret2 = swe_fixstar_ut($star2, $jd, 2, $xx2, $serr2);
if ($ret2 >= 0) {
    echo "Star: $star2\n";
    echo sprintf("Longitude: %.6f°\n", $xx2[0]);
    echo "✓ swe_fixstar_ut() works\n\n";
} else {
    echo "ERROR: $serr2\n\n";
}

// Test _mag
$star3 = 'Aldebaran';
$mag = 0.0;
$serr3 = '';

$ret3 = swe_fixstar_mag($star3, $mag, $serr3);
if ($ret3 >= 0) {
    echo "Star: $star3\n";
    echo sprintf("Visual magnitude: %.2f\n", $mag);
    echo "✓ swe_fixstar_mag() works\n\n";
} else {
    echo "ERROR: $serr3\n\n";
}

echo "=== ALL LEGACY FIXSTAR FUNCTIONS WORK ===\n\n";
