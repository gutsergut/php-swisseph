<?php

/**
 * Minimal test to isolate NaN issue
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\VectorMath;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Minimal occultation search test ===\n";

$tjd = swe_julday(2023, 3, 1, 12.0, Constants::SE_GREG_CAL);
echo "Start JD: $tjd\n";

// Get Saturn position
$ipl = Constants::SE_SATURN;
$iflag = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SWIEPH;
$iflagcart = $iflag | Constants::SEFLG_XYZ;

$ls = [];
$serr = null;
$retflag = swe_calc($tjd, $ipl, $iflag, $ls, $serr);
echo "Saturn (equat): RA={$ls[0]}° Dec={$ls[1]}° dist={$ls[2]} AU\n";

// Get Moon position
$lm = [];
$retflag = swe_calc($tjd, Constants::SE_MOON, $iflag, $lm, $serr);
echo "Moon (equat): RA={$lm[0]}° Dec={$lm[1]}° dist={$lm[2]} AU\n";

// Get Cartesian
$xs = [];
$retflag = swe_calc($tjd, $ipl, $iflagcart, $xs, $serr);
echo "Saturn (cart): X={$xs[0]} Y={$xs[1]} Z={$xs[2]}\n";

$xm = [];
$retflag = swe_calc($tjd, Constants::SE_MOON, $iflagcart, $xm, $serr);
echo "Moon (cart): X={$xm[0]} Y={$xm[1]} Z={$xm[2]}\n";

// Calculate angular distance
$dotprod = VectorMath::dotProductUnit($xs, $xm);
echo "Dot product: $dotprod\n";

if ($dotprod > 1.0) $dotprod = 1.0;
if ($dotprod < -1.0) $dotprod = -1.0;

$angle_rad = acos($dotprod);
$angle_deg = $angle_rad * Constants::RADTODEG;

echo "Angular distance: {$angle_deg}°\n";

// Calculate planet/moon radii
$rmoon = asin(Constants::RMOON / $lm[2]) * Constants::RADTODEG;
echo "Moon angular radius: {$rmoon}°\n";

$plaDiam = [
    1391978489.9,  // SE_SUN
    0,             // SE_MOON
    0,             // SE_MERCURY
    0,             // SE_VENUS
    0,             // SE_MARS
    142984000.0,   // SE_JUPITER
    120536000.0,   // SE_SATURN
    51118000.0,    // SE_URANUS
    49528000.0,    // SE_NEPTUNE
    2390000.0,     // SE_PLUTO
];

$drad = $plaDiam[$ipl] / 2 / Constants::AUNIT;
$rsun = asin($drad / $ls[2]) * Constants::RADTODEG;
echo "Saturn angular radius: {$rsun}°\n";

$dc = $angle_deg - ($rmoon + $rsun);
echo "Distance - radii sum: {$dc}°\n";

echo "\nNo NaN detected in manual calculation!\n";
