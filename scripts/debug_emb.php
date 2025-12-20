<?php
/**
 * Debug full EMB->Earth transformation
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

$au = $jpl->getAu();
$emrat = $jpl->getEmrat();

echo "AU = $au km\n";
echo "EMRAT = $emrat\n\n";

$jd = 2459984.5;

// Get EMB relative to barycenter
$embBary = [];
$jpl->pleph($jd, JplConstants::J_EMB, JplConstants::J_SBARY, $embBary, $serr);
echo "EMB relative to barycenter:\n";
printf("  X: %.9f AU\n", $embBary[0]);
printf("  Y: %.9f AU\n", $embBary[1]);
printf("  Z: %.9f AU\n", $embBary[2]);

// Get Sun relative to barycenter
$sunBary = [];
$jpl->pleph($jd, JplConstants::J_SUN, JplConstants::J_SBARY, $sunBary, $serr);
echo "\nSun relative to barycenter:\n";
printf("  X: %.9f AU\n", $sunBary[0]);
printf("  Y: %.9f AU\n", $sunBary[1]);
printf("  Z: %.9f AU\n", $sunBary[2]);

// EMB helio = EMB_bary - Sun_bary
$embHelio = [
    $embBary[0] - $sunBary[0],
    $embBary[1] - $sunBary[1],
    $embBary[2] - $sunBary[2],
];
echo "\nEMB heliocentric (manual):\n";
printf("  X: %.9f AU\n", $embHelio[0]);
printf("  Y: %.9f AU\n", $embHelio[1]);
printf("  Z: %.9f AU\n", $embHelio[2]);

// Get Moon relative to Earth
$moonGeo = [];
$jpl->pleph($jd, JplConstants::J_MOON, JplConstants::J_EARTH, $moonGeo, $serr);
echo "\nMoon relative to Earth:\n";
printf("  X: %.9f AU\n", $moonGeo[0]);
printf("  Y: %.9f AU\n", $moonGeo[1]);
printf("  Z: %.9f AU\n", $moonGeo[2]);

// Earth helio = EMB helio - Moon/(1+EMRAT)
// But wait - in pleph, J_EARTH already accounts for this!

// Get Earth relative to Sun directly
$earthHelio = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $earthHelio, $serr);
echo "\nEarth heliocentric (from pleph):\n";
printf("  X: %.9f AU\n", $earthHelio[0]);
printf("  Y: %.9f AU\n", $earthHelio[1]);
printf("  Z: %.9f AU\n", $earthHelio[2]);

// Convert to ecliptic
$earthEcl = equatorialToEcliptic($earthHelio);
echo "\nEarth heliocentric ecliptic:\n";
printf("  X: %.9f AU\n", $earthEcl[0]);
printf("  Y: %.9f AU\n", $earthEcl[1]);
printf("  Z: %.9f AU\n", $earthEcl[2]);

echo "\nswetest reference:\n";
echo "  X: -0.755127890 AU\n";
echo "  Y:  0.634738454 AU\n";
echo "  Z: -0.000000906 AU\n";

printf("\nDiff X: %.6f AU = %.0f km\n", $earthEcl[0] - (-0.755127890), ($earthEcl[0] - (-0.755127890)) * $au);

// Also check raw EMB coefficients
$ref = new ReflectionClass($jpl);
$pv = $ref->getProperty('pv');
$pv->setAccessible(true);
$pvVal = $pv->getValue($jpl);

echo "\n=== Internal pv array ===\n";
echo "pv[12..17] (EMB index 2 * 6):\n";
for ($i = 12; $i < 18; $i++) {
    printf("  pv[%d] = %.9f\n", $i, $pvVal[$i]);
}

echo "\npvsun:\n";
$pvsun = $ref->getProperty('pvsun');
$pvsun->setAccessible(true);
$pvsunVal = $pvsun->getValue($jpl);
for ($i = 0; $i < 6; $i++) {
    printf("  pvsun[%d] = %.9f\n", $i, $pvsunVal[$i]);
}

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
