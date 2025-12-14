<?php
/**
 * Bisect test to find which test breaks Venus speed
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;

function testVenusSpeed(): bool {
    $xx = [];
    $serr = null;
    $ret = swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xx, $serr);
    return abs($xx[3]) >= 0.2 && abs($xx[3]) <= 3.5;
}

// Simulate what tests before Venus do
$jd = 2451545.0;
$jd2 = 2460677.0;

echo "Initial Venus check: " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";

// Run some planets
$planets = [
    Constants::SE_MERCURY,
    Constants::SE_MARS,
    Constants::SE_JUPITER,
    Constants::SE_SATURN,
    Constants::SE_SUN,
    Constants::SE_MOON,
];

foreach ($planets as $pl) {
    $xx = [];
    swe_calc($jd, $pl, Constants::SEFLG_SPEED, $xx, $serr);
    swe_calc($jd2, $pl, Constants::SEFLG_SPEED, $xx, $serr);
    echo "After SE_$pl: Venus = " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";
}

// Try swe_pheno
$attr = [];
swe_pheno_ut($jd, Constants::SE_MOON, 0, $attr, $serr);
echo "After Moon pheno: Venus = " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";

swe_pheno_ut($jd, Constants::SE_VENUS, 0, $attr, $serr);
echo "After Venus pheno: Venus = " . (testVenusSpeed() ? "PASS" : "FAIL") . "\n";
