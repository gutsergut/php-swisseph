<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use function Swisseph\swe_calc;

// Enable debug output in PlanetHelper
// Actually, let's directly call the method and add debug output

// Manually test dt value
$r = new ReflectionClass('Swisseph\PlanetHelper');
$method = $r->getMethod('outputForPlanetHeliocentric');
$method->setAccessible(true);

$jd_tt = 2451545.0;  // J2000.0
$iflag = Constants::SEFLG_HELIOCENTRIC | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_SPEED;

$planetFunc = fn(float $t) => Swisseph\Mars::heliocentricRectEclAU($t);

// Add debug wrapper
$debugFunc = function(float $t) use ($planetFunc) {
    static $count = 0;
    $count++;
    $result = $planetFunc($t);
    if ($count <= 3) {  // Log first 3 calls
        error_log("Call #$count: t=$t, result=[" . implode(', ', $result) . "]");
    }
    return $result;
};

error_log("=== Testing dt value in PlanetHelper ===");
$result = $method->invoke(null, $jd_tt, $iflag, $debugFunc);

echo "Result: [" . implode(', ', $result) . "]\n";
echo "Speed: " . sqrt($result[3]**2 + $result[4]**2 + $result[5]**2) . " AU/day\n";
