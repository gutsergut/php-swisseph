<?php
/**
 * Test JPL at segment midpoint
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

// Test at segment midpoint (JD 2460016.5)
$jd = 2460016.5;
echo "=== JD $jd (segment midpoint, t=0.5) ===\n\n";

$rrd = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

echo "PHP Earth helio (equatorial):\n";
printf("  X: %.9f\n", $rrd[0]);
printf("  Y: %.9f\n", $rrd[1]);
printf("  Z: %.9f\n", $rrd[2]);

$ecl = equatorialToEcliptic($rrd);
echo "\nPHP Earth helio (ecliptic):\n";
printf("  X: %.9f\n", $ecl[0]);
printf("  Y: %.9f\n", $ecl[1]);
printf("  Z: %.9f\n", $ecl[2]);

echo "\nswetest reference:\n";
echo "  X: -0.984331853\n";
echo "  Y:  0.135921621\n";
echo "  Z:  0.000001907\n";

$au = $jpl->getAu();
printf("\nDiff X: %.6f AU = %.0f km\n", $ecl[0] - (-0.984331853), ($ecl[0] - (-0.984331853)) * $au);
printf("Diff Y: %.6f AU = %.0f km\n", $ecl[1] - 0.135921621, ($ecl[1] - 0.135921621) * $au);

// Also check internal state
$ref = new ReflectionClass($jpl);
$nrl = $ref->getProperty('nrl');
$nrl->setAccessible(true);
$nrlVal = $nrl->getValue($jpl);
echo "\nRecord number: $nrlVal\n";

// Calculate expected record
$etMn = floor($jd - 0.5) + 0.5;
$nr = (int)(($etMn - $ss[0]) / $ss[2]) + 2;
echo "Expected record: $nr\n";

// Show normalized t
$t = ($etMn - (($nr - 2) * $ss[2] + $ss[0])) / $ss[2];
echo "Normalized t: $t\n";

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
        $eq[3] ?? 0,
        ($eq[4] ?? 0) * $cosEps + ($eq[5] ?? 0) * $sinEps,
        -($eq[4] ?? 0) * $sinEps + ($eq[5] ?? 0) * $cosEps,
    ];
}
