<?php
/**
 * Test full moshplan() pipeline against swetest -emos
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Moshier\MoshierConstants;

// SEI_* indices from swephexp.h (internal planet indices)
define('SEI_EARTH', 0);
define('SEI_MOON', 1);
define('SEI_EMB', 2);      // Earth-Moon barycenter
define('SEI_MERCURY', 2);  // Moshier: Mercury = 2
define('SEI_VENUS', 3);    // Moshier: Venus = 3
define('SEI_MARS', 4);
define('SEI_JUPITER', 5);
define('SEI_SATURN', 6);
define('SEI_URANUS', 7);
define('SEI_NEPTUNE', 8);
define('SEI_PLUTO', 9);

// Test date: 2024-06-15 12:00 UT -> JD_UT = 2460476.0
$jdUT = 2460476.0;
$deltaT = 69.184 / 86400.0;  // Approximate delta T in days
$jdTT = $jdUT + $deltaT;

echo "=== Testing moshplan() Full Pipeline ===\n\n";
echo sprintf("JD (UT): %.6f\n", $jdUT);
echo sprintf("JD (TT): %.6f\n", $jdTT);
echo sprintf("ΔT: %.2f seconds\n\n", $deltaT * 86400.0);

// Planet mapping: internal index -> name
$planets = [
    SEI_MERCURY => 'Mercury',
    SEI_VENUS   => 'Venus',
    SEI_MARS    => 'Mars',
    SEI_JUPITER => 'Jupiter',
    SEI_SATURN  => 'Saturn',
    SEI_URANUS  => 'Uranus',
    SEI_NEPTUNE => 'Neptune',
    SEI_PLUTO   => 'Pluto',
];

// Also test Earth
echo "--- Earth (via SEI_EARTH=0) ---\n";
$xpret = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
$xeret = null;
$serr = null;

$ret = MoshierPlanetCalculator::moshplan(
    $jdTT,
    SEI_EARTH,  // 0
    $xpret,
    $xeret,
    $serr
);

if ($ret !== 0) {
    echo "ERROR: $serr\n";
} else {
    echo sprintf("  Position: [%.10f, %.10f, %.10f] AU\n", $xpret[0], $xpret[1], $xpret[2]);
    echo sprintf("  Speed:    [%.10f, %.10f, %.10f] AU/day\n", $xpret[3], $xpret[4], $xpret[5]);

    // Compute distance
    $dist = sqrt($xpret[0] ** 2 + $xpret[1] ** 2 + $xpret[2] ** 2);
    echo sprintf("  Distance: %.10f AU\n", $dist);
}
echo "\n";

echo "--- Planets (heliocentric equatorial J2000 cartesian) ---\n\n";

foreach ($planets as $ipli => $name) {
    $xpret = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
    $xeret = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
    $serr = null;

    $ret = MoshierPlanetCalculator::moshplan(
        $jdTT,
        $ipli,
        $xpret,
        $xeret,
        $serr
    );

    if ($ret !== 0) {
        echo "$name: ERROR - $serr\n";
        continue;
    }

    // Compute distance from Sun
    $dist = sqrt($xpret[0] ** 2 + $xpret[1] ** 2 + $xpret[2] ** 2);

    // Compute speed magnitude
    $speedMag = sqrt($xpret[3] ** 2 + $xpret[4] ** 2 + $xpret[5] ** 2);

    echo sprintf("%-10s Position: [%+.10f, %+.10f, %+.10f] AU\n",
        $name, $xpret[0], $xpret[1], $xpret[2]);
    echo sprintf("           Speed:    [%+.10e, %+.10e, %+.10e] AU/day\n",
        $xpret[3], $xpret[4], $xpret[5]);
    echo sprintf("           Distance: %.6f AU, Speed: %.6e AU/day\n\n", $dist, $speedMag);
}

echo "=== Test completed ===\n";
