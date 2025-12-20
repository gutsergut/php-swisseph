<?php
/**
 * Debug raw Moon velocities from ephemeris file
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwephCalculator;
use Swisseph\SwephFile\SwedState;

swe_set_ephe_path('C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe');

$jd = swe_julday(2025, 1, 1, 12.0, Constants::SE_GREG_CAL);

// Convert UT to TT
$dt = swe_deltat($jd);
$jd_tt = $jd + $dt;

echo "JD UT: $jd\n";
echo "JD TT: $jd_tt\n";
echo "Delta T: " . ($dt * 86400) . " seconds\n\n";

// Get raw Moon data from ephemeris
$xpm = [];
$serr = '';
$retc = SwephCalculator::calculate(
    $jd_tt,
    SwephConstants::SEI_MOON,
    SwephConstants::SEI_FILE_MOON,
    Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
    null,
    true,
    $xpm,
    $serr
);

echo "=== Raw Moon from ephemeris (J2000 ecliptic, geocentric) ===\n";
printf("X:  %.15f AU  (speed: %.15e AU/day)\n", $xpm[0], $xpm[3] ?? 0);
printf("Y:  %.15f AU  (speed: %.15e AU/day)\n", $xpm[1], $xpm[4] ?? 0);
printf("Z:  %.15f AU  (speed: %.15e AU/day)\n", $xpm[2], $xpm[5] ?? 0);

// Convert speed to degrees/day for comparison
// Speed in AU/day at Moon distance ~0.00257 AU
// Angular speed = linear speed / distance * RADTODEG
$dist = sqrt($xpm[0]*$xpm[0] + $xpm[1]*$xpm[1] + $xpm[2]*$xpm[2]);
$speedMag = sqrt(($xpm[3] ?? 0)**2 + ($xpm[4] ?? 0)**2 + ($xpm[5] ?? 0)**2);
$angularSpeed = $speedMag / $dist * Constants::RADTODEG;
printf("\nDistance: %.9f AU\n", $dist);
printf("Speed magnitude: %.9e AU/day\n", $speedMag);
printf("Approximate angular speed: %.6f°/day\n", $angularSpeed);
printf("Expected Moon speed: ~13.5°/day\n");
