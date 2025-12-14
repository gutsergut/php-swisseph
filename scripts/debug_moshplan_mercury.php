<?php
/**
 * Debug moshplan() for Mercury step-by-step
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Coordinates;

// Constants from MoshierPlanetCalculator
const STR = 4.8481368110953599359e-6;
const SEPS2000 = 0.3977771559319137;
const CEPS2000 = 0.9174821430670688;
const J2000 = 2451545.0;

// SEI indices
const SEI_MERCURY = 2;

// JD for 2024-06-15 12:00 UT
$jdUT = 2460477.0;
$deltaT = 69.184 / 86400.0;
$jdTT = $jdUT + $deltaT;

echo "=== Debug moshplan() for Mercury ===\n\n";
echo sprintf("JD (TT): %.10f\n\n", $jdTT);

// Reference from swetest -emos -hel -p2 -j2000:
echo "Reference (swetest -emos -hel -p2 -j2000):\n";
echo "  X = 0.005808984 AU\n";
echo "  Y = 0.272373163 AU\n";
echo "  Z = 0.144899909 AU\n\n";

// Step 1: Get polar ecliptic J2000 from moshplan2
echo "--- Step 1: moshplan2() polar output ---\n";
// Use reflection to access private moshplan2
$reflection = new ReflectionClass(MoshierPlanetCalculator::class);
$method = $reflection->getMethod('moshplan2');
$method->setAccessible(true);

$xp = [0.0, 0.0, 0.0];
$iplm = 0; // Moshier Mercury index = 0
$method->invokeArgs(null, [$jdTT, $iplm, &$xp]);

echo sprintf("  L (rad)   = %.12f\n", $xp[0]);
echo sprintf("  L (deg)   = %.8f°\n", rad2deg($xp[0]));
echo sprintf("  B (rad)   = %.12f\n", $xp[1]);
echo sprintf("  B (deg)   = %.8f°\n", rad2deg($xp[1]));
echo sprintf("  R (AU)    = %.12f\n", $xp[2]);
echo "\n";

// Compare with swetest ecliptic polar
echo "Expected from swetest -lbr (ecliptic J2000):\n";
echo "  L = 89.2584683°\n";
echo "  B = 4.5756412°\n";
echo "  R = 0.308572306 AU\n\n";

// Step 2: Convert to cartesian
echo "--- Step 2: polCart() ---\n";
$xCart = [0.0, 0.0, 0.0];
Coordinates::polCart($xp, $xCart);
echo sprintf("  X (ecl) = %.12f AU\n", $xCart[0]);
echo sprintf("  Y (ecl) = %.12f AU\n", $xCart[1]);
echo sprintf("  Z (ecl) = %.12f AU\n", $xCart[2]);
echo "\n";

// Step 3: Convert ecliptic to equatorial J2000
echo "--- Step 3: coortrf2() ecliptic->equatorial ---\n";
echo sprintf("  SEPS2000 = %.16f\n", SEPS2000);
echo sprintf("  CEPS2000 = %.16f\n", CEPS2000);
$xEq = [0.0, 0.0, 0.0];
Coordinates::coortrf2($xCart, $xEq, -SEPS2000, CEPS2000);
echo sprintf("  X (eq J2000) = %.12f AU\n", $xEq[0]);
echo sprintf("  Y (eq J2000) = %.12f AU\n", $xEq[1]);
echo sprintf("  Z (eq J2000) = %.12f AU\n", $xEq[2]);
echo "\n";

// Step 4: Run full moshplan()
echo "--- Step 4: Full moshplan() output ---\n";
$xpret = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
$xeret = null;
$serr = null;
$ret = MoshierPlanetCalculator::moshplan($jdTT, SEI_MERCURY, $xpret, $xeret, $serr);
if ($ret !== 0) {
    echo "ERROR: $serr\n";
    exit(1);
}
echo sprintf("  X = %.12f AU\n", $xpret[0]);
echo sprintf("  Y = %.12f AU\n", $xpret[1]);
echo sprintf("  Z = %.12f AU\n", $xpret[2]);
echo "\n";

// Step 5: Compare
echo "--- Step 5: Comparison ---\n";
$AU_TO_KM = 149597870.7;
$ref = [0.005808984, 0.272373163, 0.144899909];
$dx = ($xpret[0] - $ref[0]) * $AU_TO_KM;
$dy = ($xpret[1] - $ref[1]) * $AU_TO_KM;
$dz = ($xpret[2] - $ref[2]) * $AU_TO_KM;
echo sprintf("  ΔX = %+.1f km\n", $dx);
echo sprintf("  ΔY = %+.1f km\n", $dy);
echo sprintf("  ΔZ = %+.1f km\n", $dz);

echo "\n=== Debug completed ===\n";
