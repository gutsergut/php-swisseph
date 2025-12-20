<?php
/**
 * Debug: Compare PHP pleph with swetest for DE200 at J2000
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

JplEphemeris::resetInstance();

$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = null;
$result = $jpl->open($ss, 'de200.eph', 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/', $serr);

if ($result !== 0) {
    die("Open failed: $serr\n");
}

echo "=== DE200 at J2000 (JD 2451545.0) ===\n";
echo "DE Number: " . $jpl->getDenum() . "\n";
echo "AU: " . number_format($jpl->getAu(), 6) . " km\n";

$jd = 2451545.0;

// Test Mercury
$pv = [];
$result = $jpl->pleph($jd, JplConstants::J_MERCURY, JplConstants::J_SBARY, $pv, $serr);

echo "\nPHP Mercury barycentric:\n";
printf("  X = %.15f AU\n", $pv[0]);
printf("  Y = %.15f AU\n", $pv[1]);
printf("  Z = %.15f AU\n", $pv[2]);

echo "\nswetest Mercury barycentric (from cmd):\n";
echo "  X = -0.137272417 AU\n";
echo "  Y = -0.403230979 AU\n";
echo "  Z = -0.201402609 AU\n";

echo "\nDifference:\n";
$dx = $pv[0] - (-0.137272417);
$dy = $pv[1] - (-0.403230979);
$dz = $pv[2] - (-0.201402609);
printf("  dX = %.9f AU = %.0f km\n", $dx, $dx * 149597870.66);
printf("  dY = %.9f AU = %.0f km\n", $dy, $dy * 149597870.66);
printf("  dZ = %.9f AU = %.0f km\n", $dz, $dz * 149597870.66);

// Now let's check step-by-step what values we get
echo "\n=== Internal Debug ===\n";

$reflection = new ReflectionClass($jpl);
$propPv = $reflection->getProperty('pv');
$propPv->setAccessible(true);
$allPv = $propPv->getValue($jpl);

$propPvsun = $reflection->getProperty('pvsun');
$propPvsun->setAccessible(true);
$pvsun = $propPvsun->getValue($jpl);

echo "pvsun (Sun barycentric):\n";
printf("  X = %.15f AU\n", $pvsun[0]);
printf("  Y = %.15f AU\n", $pvsun[1]);
printf("  Z = %.15f AU\n", $pvsun[2]);

echo "\npv[0:6] (Mercury in pv array):\n";
printf("  X = %.15f AU\n", $allPv[0]);
printf("  Y = %.15f AU\n", $allPv[1]);
printf("  Z = %.15f AU\n", $allPv[2]);

// swetest Sun barycentric
echo "\nswetest Sun barycentric:\n";
echo "  X = -0.007136451 AU\n";
echo "  Y = -0.002647029 AU\n";
echo "  Z = -0.000922950 AU\n";

echo "\nExpected Mercury raw from JPL (before Sun subtraction):\n";
echo "Mercury_bary = Mercury_raw (since doBary=true)\n";
echo "Mercury_helio = Mercury_raw - Sun_bary\n";
