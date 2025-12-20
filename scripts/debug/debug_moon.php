<?php
/**
 * Debug Earth/Moon positions
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
swe_set_ephe_path($ephePath);

$tjdEt = 2451545.0;

// Get Moon geocentric
$moonFlags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 |
             Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;

$xmoon = [];
$serr = '';
swe_calc($tjdEt, Constants::SE_MOON, $moonFlags, $xmoon, $serr);

echo "PHP Moon (geocentric J2000 equatorial XYZ):\n";
echo sprintf("  xmoon = [%.9f, %.9f, %.9f]\n\n", $xmoon[0], $xmoon[1], $xmoon[2]);

echo "C Moon reference:\n";
echo "  xmoon = [-0.001949007, -0.001783176, -0.000508842]\n\n";

echo "Difference:\n";
$cMoon = [-0.001949007, -0.001783176, -0.000508842];
echo sprintf("  dX = %.9f AU = %.2f km\n", $xmoon[0] - $cMoon[0], ($xmoon[0] - $cMoon[0]) * 149597870.7);
echo sprintf("  dY = %.9f AU = %.2f km\n", $xmoon[1] - $cMoon[1], ($xmoon[1] - $cMoon[1]) * 149597870.7);
echo sprintf("  dZ = %.9f AU = %.2f km\n", $xmoon[2] - $cMoon[2], ($xmoon[2] - $cMoon[2]) * 149597870.7);

// Earth-Moon ratio
$MRAT = 81.30056907419062; // EARTH_MOON_MRAT from Constants

echo "\n=== EMB calculation ===\n";
// EMB = Earth + Moon / (MRAT + 1)
// Earth = EMB - Moon / (MRAT + 1)
// If C has xear_c = EMB - moon_c/MRAT_eff
// and PHP has xear_php = EMB - moon_php/MRAT_eff
// then diff = (moon_c - moon_php) / MRAT_eff

$moonDiff = [
    $xmoon[0] - $cMoon[0],
    $xmoon[1] - $cMoon[1],
    $xmoon[2] - $cMoon[2],
];

echo "Moon difference causes Earth difference of:\n";
echo sprintf("  dX_ear = %.9f AU = %.2f km\n", -$moonDiff[0] / ($MRAT + 1), -$moonDiff[0] / ($MRAT + 1) * 149597870.7);
echo sprintf("  dY_ear = %.9f AU = %.2f km\n", -$moonDiff[1] / ($MRAT + 1), -$moonDiff[1] / ($MRAT + 1) * 149597870.7);
echo sprintf("  dZ_ear = %.9f AU = %.2f km\n", -$moonDiff[2] / ($MRAT + 1), -$moonDiff[2] / ($MRAT + 1) * 149597870.7);
