<?php
/**
 * Test Moshier integration with swe_calc().
 *
 * Compares PHP results with swetest64.exe reference values.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

$jdTT = 2460477.0008007409;  // 2024-06-15 12:00 TT
$iflag = Constants::SEFLG_MOSEPH | Constants::SEFLG_SPEED;

echo "=== Moshier Integration Test ===\n\n";
echo sprintf("JD (TT): %.10f\n", $jdTT);
echo "Flags: SEFLG_MOSEPH | SEFLG_SPEED\n\n";

// Reference values from swetest64.exe -emos -b15.6.2024 -ut12:00 -fPlbr
// swetest64.exe -p2 -emos -b15.6.2024 -ut12:00 -fPlbr
$refs = [
    ['name' => 'Mercury', 'ipl' => Constants::SE_MERCURY, 'lon' => 77.717, 'lat' => 2.049],
    ['name' => 'Venus', 'ipl' => Constants::SE_VENUS, 'lon' => 85.406, 'lat' => 1.553],
    ['name' => 'Mars', 'ipl' => Constants::SE_MARS, 'lon' => 20.099, 'lat' => 0.637],
    ['name' => 'Jupiter', 'ipl' => Constants::SE_JUPITER, 'lon' => 62.844, 'lat' => -0.498],
    ['name' => 'Saturn', 'ipl' => Constants::SE_SATURN, 'lon' => 349.082, 'lat' => 1.653],
];

echo sprintf("%-10s %12s %12s %12s %12s\n",
    "Planet", "PHP Lon", "Ref Lon", "ΔLon (\")", "ΔLat (\")");
echo str_repeat('-', 70) . "\n";

foreach ($refs as $ref) {
    $xx = [];
    $serr = '';

    $retflag = PlanetsFunctions::calc($jdTT, $ref['ipl'], $iflag, $xx, $serr);

    if ($retflag < 0) {
        echo sprintf("%-10s ERROR: %s\n", $ref['name'], $serr);
        continue;
    }

    $phpLon = $xx[0];
    $phpLat = $xx[1];

    $dLon = ($phpLon - $ref['lon']) * 3600;
    $dLat = ($phpLat - $ref['lat']) * 3600;

    // Handle wraparound
    if ($dLon > 648000) $dLon -= 1296000;
    if ($dLon < -648000) $dLon += 1296000;

    echo sprintf("%-10s %12.6f %12.6f %+12.2f %+12.2f\n",
        $ref['name'], $phpLon, $ref['lon'], $dLon, $dLat);
}

echo "\n=== Done ===\n";
