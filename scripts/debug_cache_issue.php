<?php

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

// Test 1: Mercury first
echo "=== Test 1: Mercury at JD 2460677 ===\n";
$xx = [];
$serr = null;
swe_calc(2460677.0, Constants::SE_MERCURY, Constants::SEFLG_SPEED, $xx, $serr);
echo "Mercury lon_speed: {$xx[3]} deg/day\n";

$swed = SwedState::getInstance();
echo "SUNBARY teval: {$swed->pldat[SwephConstants::SEI_SUNBARY]->teval}\n";
echo "EARTH teval: {$swed->pldat[SwephConstants::SEI_EARTH]->teval}\n";

// Test 2: Venus after Mercury
echo "\n=== Test 2: Venus at JD 2451545 ===\n";
$xx = [];
$serr = null;
swe_calc(2451545.0, Constants::SE_VENUS, Constants::SEFLG_SPEED, $xx, $serr);
echo "Venus lon_speed: {$xx[3]} deg/day\n";

echo "SUNBARY teval: {$swed->pldat[SwephConstants::SEI_SUNBARY]->teval}\n";
echo "EARTH teval: {$swed->pldat[SwephConstants::SEI_EARTH]->teval}\n";
echo "VENUS x[3]: {$swed->pldat[SwephConstants::SEI_VENUS]->x[3]}\n";

// Is speed reasonable?
if (abs($xx[3]) < 0.2 || abs($xx[3]) > 3.5) {
    echo "ERROR: Venus speed out of range!\n";
} else {
    echo "OK: Venus speed in range\n";
}
