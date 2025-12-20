<?php
/**
 * Test JPL with equatorial-to-ecliptic conversion
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$ephFile = 'de406e.eph';
$ephPath = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl';

$jpl = JplEphemeris::getInstance();

$ss = [];
$serr = null;
$result = $jpl->open($ss, $ephFile, $ephPath, $serr);
if ($result !== JplConstants::OK) {
    echo "ERROR: $serr\n";
    exit(1);
}

$jd = 2460000.5;

// Get Earth heliocentric (equatorial from JPL)
$rrd = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

echo "Earth heliocentric (raw equatorial J2000 from JPL):\n";
printf("  X: %15.9f AU\n", $rrd[0]);
printf("  Y: %15.9f AU\n", $rrd[1]);
printf("  Z: %15.9f AU\n", $rrd[2]);

// Convert to ecliptic
$ecl = equatorialToEcliptic($rrd);
echo "\nEarth heliocentric (ecliptic J2000 after conversion):\n";
printf("  X: %15.9f AU\n", $ecl[0]);
printf("  Y: %15.9f AU\n", $ecl[1]);
printf("  Z: %15.9f AU\n", $ecl[2]);

echo "\nswetest reference (ecliptic):\n";
echo "  X: -0.904891727\n";
echo "  Y:  0.400876861\n";
echo "  Z:  0.000000852\n";

printf("\nDiff X: %.9f\n", $ecl[0] - (-0.904891727));
printf("Diff Y: %.9f\n", $ecl[1] - 0.400876861);
printf("Diff Z: %.9f\n", $ecl[2] - 0.000000852);

echo "\n=== Mars ===\n";
$rrd = [];
$jpl->pleph($jd, JplConstants::J_MARS, JplConstants::J_SUN, $rrd, $serr);

echo "Mars heliocentric (raw equatorial):\n";
printf("  X: %15.9f AU\n", $rrd[0]);
printf("  Y: %15.9f AU\n", $rrd[1]);
printf("  Z: %15.9f AU\n", $rrd[2]);

$ecl = equatorialToEcliptic($rrd);
echo "\nMars heliocentric (ecliptic after conversion):\n";
printf("  X: %15.9f AU\n", $ecl[0]);
printf("  Y: %15.9f AU\n", $ecl[1]);
printf("  Z: %15.9f AU\n", $ecl[2]);

echo "\nswetest reference (ecliptic):\n";
echo "  X: -0.667032539\n";
echo "  Y:  1.478519021\n";
echo "  Z:  0.047298527\n";

printf("\nDiff X: %.9f\n", $ecl[0] - (-0.667032539));
printf("Diff Y: %.9f\n", $ecl[1] - 1.478519021);
printf("Diff Z: %.9f\n", $ecl[2] - 0.047298527);

$jpl->close();

function equatorialToEcliptic(array $eq): array
{
    // Mean obliquity at J2000.0 in radians
    $eps = deg2rad(23.4392911);
    $cosEps = cos($eps);
    $sinEps = sin($eps);

    $x = $eq[0];
    $y = $eq[1];
    $z = $eq[2];

    $xEcl = $x;
    $yEcl = $y * $cosEps + $z * $sinEps;
    $zEcl = -$y * $sinEps + $z * $cosEps;

    $vx = $eq[3];
    $vy = $eq[4];
    $vz = $eq[5];

    $vxEcl = $vx;
    $vyEcl = $vy * $cosEps + $vz * $sinEps;
    $vzEcl = -$vy * $sinEps + $vz * $cosEps;

    return [$xEcl, $yEcl, $zEcl, $vxEcl, $vyEcl, $vzEcl];
}
