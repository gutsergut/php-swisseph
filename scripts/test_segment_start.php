<?php
/**
 * Compare PHP JPL vs swetest at segment start
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$jpl = JplEphemeris::getInstance();
$ss = [];
$jpl->open($ss, 'de406e.eph',
    'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl',
    $serr);

// Test at segment start (t=0)
$jd = 2459984.5;
echo "=== JD $jd (segment start, t=0) ===\n\n";

$rrd = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

echo "PHP Earth helio (equatorial):\n";
printf("  X: %15.9f\n", $rrd[0]);
printf("  Y: %15.9f\n", $rrd[1]);
printf("  Z: %15.9f\n", $rrd[2]);

$ecl = equatorialToEcliptic($rrd);
echo "\nPHP Earth helio (ecliptic):\n";
printf("  X: %15.9f\n", $ecl[0]);
printf("  Y: %15.9f\n", $ecl[1]);
printf("  Z: %15.9f\n", $ecl[2]);

echo "\nswetest reference:\n";
echo "  X: -0.755127890\n";
echo "  Y:  0.634738454\n";
echo "  Z: -0.000000906\n";

printf("\nDiff X: %.9f AU = %.0f km\n", $ecl[0] - (-0.755127890), ($ecl[0] - (-0.755127890)) * 149597870.691);
printf("Diff Y: %.9f AU = %.0f km\n", $ecl[1] - 0.634738454, ($ecl[1] - 0.634738454) * 149597870.691);
printf("Diff Z: %.9f AU = %.0f km\n", $ecl[2] - (-0.000000906), ($ecl[2] - (-0.000000906)) * 149597870.691);

// Now test middle of segment
echo "\n=== JD 2460000.5 (t=0.25) ===\n\n";
$jd = 2460000.5;
$rrd = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

$ecl = equatorialToEcliptic($rrd);
echo "PHP Earth helio (ecliptic):\n";
printf("  X: %15.9f\n", $ecl[0]);
printf("  Y: %15.9f\n", $ecl[1]);
printf("  Z: %15.9f\n", $ecl[2]);

echo "\nswetest reference:\n";
echo "  X: -0.904891727\n";
echo "  Y:  0.400876861\n";
echo "  Z:  0.000000852\n";

printf("\nDiff X: %.9f AU = %.0f km\n", $ecl[0] - (-0.904891727), ($ecl[0] - (-0.904891727)) * 149597870.691);
printf("Diff Y: %.9f AU = %.0f km\n", $ecl[1] - 0.400876861, ($ecl[1] - 0.400876861) * 149597870.691);
printf("Diff Z: %.9f AU = %.0f km\n", $ecl[2] - 0.000000852, ($ecl[2] - 0.000000852) * 149597870.691);

$jpl->close();

function equatorialToEcliptic(array $eq): array
{
    $eps = deg2rad(23.4392911);
    $cosEps = cos($eps);
    $sinEps = sin($eps);

    return [
        $eq[0],
        $eq[1] * $cosEps + $eq[2] * $sinEps,
        -$eq[1] * $sinEps + $eq[2] * $cosEps,
        $eq[3],
        $eq[4] * $cosEps + $eq[5] * $sinEps,
        -$eq[4] * $sinEps + $eq[5] * $cosEps,
    ];
}
