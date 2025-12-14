<?php
/**
 * Run all tests from same process to reproduce Venus issue
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

function testVenusSpeed(): bool {
    $xx = [];
    $serr = null;
    $ret = swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xx, $serr);
    $ok = abs($xx[3]) >= 0.2 && abs($xx[3]) <= 3.5;
    if (!$ok) {
        echo "  Venus speed = {$xx[3]} (expected 0.2-3.5)\n";
    }
    return $ok;
}

// Various JD values from different tests
$testDates = [
    2415020.5,   // old date
    2451545.0,   // J2000
    2460000.0,
    2460677.0,
    2461000.0,
];

$planets = [
    Constants::SE_SUN,
    Constants::SE_MOON,
    Constants::SE_MERCURY,
    Constants::SE_VENUS,
    Constants::SE_MARS,
    Constants::SE_JUPITER,
    Constants::SE_SATURN,
    Constants::SE_URANUS,
    Constants::SE_NEPTUNE,
    Constants::SE_PLUTO,
    Constants::SE_MEAN_NODE,
    Constants::SE_TRUE_NODE,
    Constants::SE_OSCU_APOG,
    Constants::SE_CHIRON,
];

echo "Running many swe_calc calls...\n";
$count = 0;

foreach ($testDates as $jd) {
    foreach ($planets as $pl) {
        $xx = [];
        $serr = null;
        swe_calc($jd, $pl, Constants::SEFLG_SPEED, $xx, $serr);
        swe_calc($jd, $pl, Constants::SEFLG_SPEED | Constants::SEFLG_EQUATORIAL, $xx, $serr);
        swe_calc($jd, $pl, Constants::SEFLG_SPEED | Constants::SEFLG_HELCTR, $xx, $serr);
        $count += 3;
    }
}

echo "Executed $count calls\n";
echo "Venus check after planets: " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";

// Test houses functions
$cusps = [];
$ascmc = [];
swe_houses(2451545.0, 52.5, 13.4, 'P', $cusps, $ascmc);
echo "Venus check after houses: " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";

// Test gauquelin sectors
$cusps2 = [];
$ascmc2 = [];
$ascmc3 = [];
swe_houses_ex2(2451545.0, Constants::SEFLG_SPEED, 52.5, 13.4, 'G', $cusps2, $ascmc2, $ascmc3);
echo "Venus check after gauquelin: " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";

// Test sidereal
swe_set_sid_mode(Constants::SE_SIDM_LAHIRI, 0, 0);
$xx = [];
swe_calc(2451545.0, Constants::SE_MARS, Constants::SEFLG_SIDEREAL | Constants::SEFLG_SPEED, $xx, $serr);
echo "Venus check after sidereal: " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";

// Test fixed stars
$star = 'Aldebaran';
$xx = [];
swe_fixstar($star, 2451545.0, Constants::SEFLG_SPEED, $xx, $serr);
echo "Venus check after fixstar: " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";
