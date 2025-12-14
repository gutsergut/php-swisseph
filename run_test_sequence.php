<?php
/**
 * Run tests up to Venus to isolate the cache pollution
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0;
$serr = '';

echo "Running test sequence to find cache pollution...\n\n";

// All the tests that run BEFORE VenusSpeed in alphabetical order
$testPlanets = [
    ['name' => 'Earth', 'ipl' => 14],
    ['name' => 'Gauquelin', 'ipl' => -1], // Special test
    ['name' => 'Jupiter', 'ipl' => 5],
    ['name' => 'Mars', 'ipl' => 4],
    ['name' => 'Mercury', 'ipl' => 2],
    ['name' => 'Moon', 'ipl' => 1],
    ['name' => 'Neptune', 'ipl' => 8],
    ['name' => 'Pluto', 'ipl' => 9],
    ['name' => 'Saturn', 'ipl' => 6],
    ['name' => 'Sun', 'ipl' => 0],
    ['name' => 'Uranus', 'ipl' => 7],
];

define('SEFLG_SPEED', 256);
define('SEFLG_EQUATORIAL', 2048);
define('SEFLG_RADIANS', 32768);

function checkVenusSpeed($jd, &$serr): array {
    $xx = [];
    swe_calc_ut($jd, 3, SEFLG_SPEED, $xx, $serr);
    $speed = $xx[3] ?? 0;
    $ok = abs($speed) >= 0.2 && abs($speed) <= 3.5;
    return ['speed' => $speed, 'ok' => $ok];
}

// Baseline Venus check
echo "=== Baseline Venus Speed ===\n";
$result = checkVenusSpeed($jd, $serr);
echo "Speed: {$result['speed']} deg/day " . ($result['ok'] ? "OK" : "ERROR") . "\n\n";

// Now run through test patterns for each planet
foreach ($testPlanets as $planet) {
    if ($planet['ipl'] < 0) continue;

    echo "=== After {$planet['name']} Tests ===\n";

    // Simulate: testPlanetDefaultSuccess
    $xx = [];
    swe_calc_ut($jd, $planet['ipl'], SEFLG_SPEED, $xx, $serr);
    echo "Default: lon={$xx[0]}, speed={$xx[3]}\n";

    // Simulate: testPlanetEquatorialRadians
    $xx = [];
    swe_calc_ut($jd, $planet['ipl'], SEFLG_SPEED | SEFLG_EQUATORIAL | SEFLG_RADIANS, $xx, $serr);
    echo "EquatorialRadians: RA={$xx[0]}, speed={$xx[3]}\n";

    // Simulate: testPlanetSpeed
    $xx = [];
    swe_calc_ut($jd, $planet['ipl'], SEFLG_SPEED, $xx, $serr);
    echo "Speed test: speed={$xx[3]}\n";

    // Check Venus after this planet's tests
    $result = checkVenusSpeed($jd, $serr);
    echo "Venus Speed: {$result['speed']} deg/day " . ($result['ok'] ? "OK" : "ERROR!") . "\n\n";

    if (!$result['ok']) {
        echo "=== FOUND POLLUTER: {$planet['name']} ===\n";
        break;
    }
}

// Final check with all flags that VenusTest uses
echo "\n=== Final Venus Test Sequence ===\n";
// testVenusDefaultSuccess
$xx = [];
swe_calc_ut($jd, 3, SEFLG_SPEED, $xx, $serr);
echo "testVenusDefaultSuccess: lon={$xx[0]}, speed={$xx[3]}\n";

// testVenusEquatorialRadians
$xx = [];
swe_calc_ut($jd, 3, SEFLG_SPEED | SEFLG_EQUATORIAL | SEFLG_RADIANS, $xx, $serr);
echo "testVenusEquatorialRadians: RA={$xx[0]}, speed={$xx[3]}\n";

// testVenusSpeed
$xx = [];
swe_calc_ut($jd, 3, SEFLG_SPEED, $xx, $serr);
$ok = abs($xx[3]) >= 0.2 && abs($xx[3]) <= 3.5;
echo "testVenusSpeed: speed={$xx[3]} deg/day " . ($ok ? "OK" : "ERROR!") . "\n";
