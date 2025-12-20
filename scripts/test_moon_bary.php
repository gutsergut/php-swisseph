<?php
/**
 * Compare Moon geocentric
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

$jd = 2460016.5;

$moonBary = [];
$jpl->pleph($jd, JplConstants::J_MOON, JplConstants::J_SBARY, $moonBary, $serr);
echo "PHP Moon barycentric:\n";
printf("  X: %.9f AU\n", $moonBary[0]);
printf("  Y: %.9f AU\n", $moonBary[1]);
printf("  Z: %.9f AU\n", $moonBary[2]);

echo "\nswetest Moon barycentric:\n";
echo "  X: -0.994718133 AU\n";
echo "  Y:  0.122178459 AU\n";
echo "  Z:  0.053112048 AU\n";

printf("\nDiff X: %.9f\n", $moonBary[0] - (-0.994718133));
printf("Diff Y: %.9f\n", $moonBary[1] - 0.122178459);
printf("Diff Z: %.9f\n", $moonBary[2] - 0.053112048);

$jpl->close();
