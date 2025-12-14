<?php
/**
 * Find which test pollutes the cache before Venus speed test
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

use Swisseph\Constants;

echo "=== Testing Venus Speed with Different Flag Sequences ===\n\n";

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0; // J2000.0
define('SE_VENUS', 3);
define('SEFLG_SPEED', 256);
define('SEFLG_EQUATORIAL', 2048);
define('SEFLG_RADIANS', 32768);

// Test 1: Just SEFLG_SPEED
$xx = [];
$serr = '';
swe_calc_ut($jd, SE_VENUS, SEFLG_SPEED, $xx, $serr);
echo "1. First Venus (SEFLG_SPEED): lon={$xx[0]}, speed={$xx[3]} deg/day\n";
$speedOK = abs($xx[3]) >= 0.2 && abs($xx[3]) <= 3.5;
echo "   " . ($speedOK ? "OK" : "ERROR: out of range!") . "\n\n";

// Test 2: Then equatorial+radians
$xx = [];
$flags = SEFLG_SPEED | SEFLG_EQUATORIAL | SEFLG_RADIANS;
swe_calc_ut($jd, SE_VENUS, $flags, $xx, $serr);
echo "2. Venus (SEFLG_SPEED|SEFLG_EQUATORIAL|SEFLG_RADIANS): RA={$xx[0]} rad, speed={$xx[3]} rad/day\n";
echo "   (speed in deg/day: " . rad2deg($xx[3]) . ")\n\n";

// Test 3: Back to normal
$xx = [];
swe_calc_ut($jd, SE_VENUS, SEFLG_SPEED, $xx, $serr);
echo "3. Second Venus (SEFLG_SPEED): lon={$xx[0]}, speed={$xx[3]} deg/day\n";
$speedOK = abs($xx[3]) >= 0.2 && abs($xx[3]) <= 3.5;
echo "   " . ($speedOK ? "OK" : "ERROR: speed out of range!") . "\n";

if (!$speedOK) {
    echo "\n=== BUG REPRODUCED ===\n";
    echo "The equatorial+radians call corrupts subsequent speed calculations!\n";
}
