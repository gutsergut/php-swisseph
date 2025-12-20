<?php

/**
 * Debug script for Saturn calculations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Testing Saturn calculation ===\n";

$tjd = swe_julday(2023, 3, 1, 12.0, Constants::SE_GREG_CAL);
echo "JD: $tjd\n";

// Try ecliptic coordinates
$xx = [];
$serr = null;
$retflag = swe_calc($tjd, Constants::SE_SATURN, Constants::SEFLG_SWIEPH, $xx, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Ecliptic: lon=%.6f° lat=%.6f° dist=%.6f AU\n", $xx[0], $xx[1], $xx[2]);
}

// Try equatorial coordinates
$xx = [];
$retflag = swe_calc($tjd, Constants::SE_SATURN, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL, $xx, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Equatorial: RA=%.6f° Dec=%.6f° dist=%.6f AU\n", $xx[0], $xx[1], $xx[2]);
}

// Try Cartesian coordinates
$xx = [];
$retflag = swe_calc($tjd, Constants::SE_SATURN, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ, $xx, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Cartesian: X=%.6f Y=%.6f Z=%.6f (AU)\n", $xx[0], $xx[1], $xx[2]);
    $mag = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
    printf("Magnitude: %.6f AU\n", $mag);
}

echo "\n=== Testing Moon calculation ===\n";

// Moon ecliptic
$xx = [];
$retflag = swe_calc($tjd, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xx, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Ecliptic: lon=%.6f° lat=%.6f° dist=%.6f AU\n", $xx[0], $xx[1], $xx[2]);
}

// Moon equatorial
$xx = [];
$retflag = swe_calc($tjd, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL, $xx, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Equatorial: RA=%.6f° Dec=%.6f° dist=%.6f AU\n", $xx[0], $xx[1], $xx[2]);
}

// Moon Cartesian
$xx = [];
$retflag = swe_calc($tjd, Constants::SE_MOON, Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ, $xx, $serr);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("Cartesian: X=%.6f Y=%.6f Z=%.6f (AU)\n", $xx[0], $xx[1], $xx[2]);
    $mag = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
    printf("Magnitude: %.6f AU\n", $mag);
}

echo "\nDone\n";
