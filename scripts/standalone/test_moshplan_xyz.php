<?php
/**
 * Test full moshplan() pipeline against swetest -emos -hel -fx
 *
 * moshplan() returns heliocentric equatorial J2000 cartesian coordinates
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierPlanetCalculator;

// SEI_* indices from sweph.h
const SEI_EARTH = 0;
const SEI_MERCURY = 2;
const SEI_VENUS = 3;
const SEI_MARS = 4;
const SEI_JUPITER = 5;
const SEI_SATURN = 6;
const SEI_URANUS = 7;
const SEI_NEPTUNE = 8;
const SEI_PLUTO = 9;

// JD for 2024-06-15 12:00 UT
$jdUT = 2460477.0;
$deltaT = 69.184 / 86400.0;
$jdTT = $jdUT + $deltaT;

echo "=== Testing moshplan() Full Pipeline ===\n\n";
echo sprintf("JD (UT): %.6f\n", $jdUT);
echo sprintf("JD (TT): %.6f\n", $jdTT);
echo sprintf("ΔT: %.2f seconds\n\n", $deltaT * 86400.0);

// Reference data from swetest with -j2000:
// cmd /c "swetest64.exe -b15.6.2024 -ut12:00 -emos -hel -p12345678 -fPx -head -j2000"
$reference = [
    'Mercury' => [0.005808984, 0.272373163, 0.144899909],
    'Venus'   => [-0.021137519, 0.655433422, 0.296260119],
    'Mars'    => [1.391364875, 0.018227076, -0.029178382],
    'Jupiter' => [2.477376940, 4.037695816, 1.670363989],
    'Saturn'  => [9.257772092, -2.482028457, -1.423786329],
    'Uranus'  => [11.748074111, 14.416022358, 6.147531309],
    'Neptune' => [29.865417000, -0.897558217, -1.110843521],
];

$planets = [
    'Mercury' => SEI_MERCURY,
    'Venus'   => SEI_VENUS,
    'Mars'    => SEI_MARS,
    'Jupiter' => SEI_JUPITER,
    'Saturn'  => SEI_SATURN,
    'Uranus'  => SEI_URANUS,
    'Neptune' => SEI_NEPTUNE,
];

echo "--- Heliocentric Equatorial J2000 Cartesian Comparison ---\n\n";
echo sprintf("%-10s %12s %12s %12s    %10s %10s %10s\n",
    'Planet', 'ΔX (km)', 'ΔY (km)', 'ΔZ (km)', 'X_ref', 'Y_ref', 'Z_ref');
echo str_repeat('-', 100) . "\n";

$AU_TO_KM = 149597870.7;

foreach ($planets as $name => $ipli) {
    $xpret = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
    $xeret = null;
    $serr = null;

    $ret = MoshierPlanetCalculator::moshplan($jdTT, $ipli, $xpret, $xeret, $serr);

    if ($ret !== 0) {
        echo "$name: ERROR - $serr\n";
        continue;
    }

    $ref = $reference[$name];

    $dx = ($xpret[0] - $ref[0]) * $AU_TO_KM;
    $dy = ($xpret[1] - $ref[1]) * $AU_TO_KM;
    $dz = ($xpret[2] - $ref[2]) * $AU_TO_KM;

    echo sprintf("%-10s %+12.1f %+12.1f %+12.1f    %+10.6f %+10.6f %+10.6f\n",
        $name, $dx, $dy, $dz, $ref[0], $ref[1], $ref[2]);
}

echo "\n=== Test completed ===\n";
echo "Note: Moshier accuracy is ~50\" for inner planets, ~5\" for outer planets\n";
echo "Expected XYZ errors: ~10,000 km for Mercury (0.3 AU), ~50,000 km for Neptune (30 AU)\n";
echo "These errors correspond to angular accuracy ~20-50 arcsec\n";
