<?php
/**
 * Binary search to find the polluting test
 * Run specific test files, then check Venus
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

define('SE_VENUS', 3);
define('SEFLG_SPEED', 256);

$jd = 2451545.0;

function checkVenusSpeed(): float {
    global $jd;
    $xx = [];
    $serr = null;
    swe_calc($jd, SE_VENUS, SEFLG_SPEED, $xx, $serr);
    return $xx[3];
}

// Test files in alphabetical order (matching PHPUnit)
$testFiles = [
    'AsteroidsTest',
    'AzaltRefractionTest',
    'BarycentricCoordinatesTest',
    'CalendarTest',
    'CotransAndUtilsTest',
    'DeltaTTest',
    'FictitiousPlanetsTest',
    'FixStarParityTest',
    'GauquelinHousesTest',
    'HeliocentricsTest',
    'HouseCalcKochTest',
    'HouseNormalizationTest',
    'HousesEdgeCasesTest',
    'HousesSpeedTest',
    'HousesPolarTest',
    'JupiterVsopStrategyParityTest',
    'LunarNodeTest',
    'MarsVsopStrategyParityTest',
    'MathTest',
    'MathUtilsTest',
    'MercuryVsopStrategyParityTest',
    'MoonTopoParallaxTest',
    'ObliquityTest',
    'PhenoTest',
    'SweCalcEarthTest',
    'SweCalcGauquelinTest',
    'SweCalcJupiterTest',
    'SweCalcMarsTest',
    'SweCalcMercuryTest',
    'SweCalcMoonTest',
    'SweCalcNeptuneTest',
    'SweCalcPlutoTest',
    'SweCalcSaturnTest',
    'SweCalcSunTest',
    'SweCalcUranusTest',
    'SweCalcValidationTest',
];

echo "Baseline Venus speed: " . checkVenusSpeed() . " deg/day\n\n";

// We need to actually run the tests
// Let's do this differently - run phpunit with groups

echo "Run manually to bisect:\n";
echo "vendor\\bin\\phpunit -c phpunit.xml.dist --filter \"<TestName>\" && php check_venus.php\n\n";

// Or let's check if running the tests through include changes anything
echo "Testing simulated test runs...\n\n";

// Simulate what AsteroidsTest does
echo "=== Simulating Asteroids Test ===\n";
// SE_CHIRON = 15, SE_PHOLUS = 16, etc
for ($ast = 15; $ast <= 20; $ast++) {
    $xx = [];
    $serr = null;
    swe_calc($jd, $ast, SEFLG_SPEED, $xx, $serr);
}
echo "After Asteroids: Venus speed = " . checkVenusSpeed() . " deg/day\n";

// Simulate BarycentricCoordinatesTest
echo "\n=== Simulating Barycentric Test ===\n";
// These use SEFLG_BARYCTR flag
$SEFLG_BARYCTR = 4;
$SEFLG_XYZ = 8;
$SEFLG_TRUEPOS = 16;
$xx = [];
swe_calc($jd, 0, $SEFLG_XYZ | $SEFLG_BARYCTR, $xx, $serr); // Sun bary
$xx = [];
swe_calc($jd, 2, $SEFLG_XYZ | $SEFLG_BARYCTR | $SEFLG_TRUEPOS, $xx, $serr); // Mercury
$xx = [];
swe_calc($jd, 5, $SEFLG_XYZ | $SEFLG_BARYCTR | $SEFLG_TRUEPOS, $xx, $serr); // Jupiter
echo "After Barycentric: Venus speed = " . checkVenusSpeed() . " deg/day\n";

// Simulate HeliocentricsTest
echo "\n=== Simulating Heliocentrics Test ===\n";
$SEFLG_HELCTR = 8;
for ($planet = 2; $planet <= 9; $planet++) {
    $xx = [];
    swe_calc($jd, $planet, SEFLG_SPEED | $SEFLG_HELCTR, $xx, $serr);
}
echo "After Heliocentrics: Venus speed = " . checkVenusSpeed() . " deg/day\n";

// Simulate MoonTopoParallaxTest
echo "\n=== Simulating MoonTopoParallax Test ===\n";
$SEFLG_TOPOCTR = 32768;
swe_set_topo(12.5, 52.5, 50); // Berlin
$xx = [];
swe_calc($jd, 1, SEFLG_SPEED | $SEFLG_TOPOCTR, $xx, $serr); // Moon topocentric
echo "After MoonTopo: Venus speed = " . checkVenusSpeed() . " deg/day\n";

// Check with various flag combinations
echo "\n=== Testing flag pollution ===\n";
$SEFLG_EQUATORIAL = 2048;
$SEFLG_RADIANS = 32768;

// SEFLG_RADIANS alone
$xx = [];
swe_calc($jd, SE_VENUS, $SEFLG_RADIANS, $xx, $serr);
echo "After RADIANS: Venus speed = " . checkVenusSpeed() . " deg/day\n";

// SEFLG_EQUATORIAL alone
$xx = [];
swe_calc($jd, SE_VENUS, $SEFLG_EQUATORIAL, $xx, $serr);
echo "After EQUATORIAL: Venus speed = " . checkVenusSpeed() . " deg/day\n";

// Both
$xx = [];
swe_calc($jd, SE_VENUS, $SEFLG_RADIANS | $SEFLG_EQUATORIAL, $xx, $serr);
echo "After RADIANS|EQUATORIAL: Venus speed = " . checkVenusSpeed() . " deg/day\n";

echo "\n=== FINAL Venus speed check ===\n";
$speed = checkVenusSpeed();
$ok = abs($speed) >= 0.2 && abs($speed) <= 3.5;
echo "Speed: $speed deg/day - " . ($ok ? "OK" : "ERROR!") . "\n";
