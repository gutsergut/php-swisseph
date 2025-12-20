<?php
/**
 * Test script for Uranian/Fictitious planets
 *
 * Reference values from swetest64.exe:
 * cmd /c ""C:\...\swetest64.exe" -b1.1.2020 -ut12:00:00 -pJKLMNOPQ -fPlbsR -head -eswe -edir..."
 *
 * Cupido           271.1825036   0.6725756   0.0273932   41.808028742
 * Hades            98.7386306  -0.9487789  -0.0173279   49.789460312
 * Zeus             201.4412605  -0.0066473   0.0054560   59.405222671
 * Kronos           102.0454189   0.0152448  -0.0137408   63.898958264
 * Apollon          213.7486259  -0.0095290   0.0073579   70.681443799
 * Admetos          60.8997749   0.0144730  -0.0090495   72.867674087
 * Vulkanus         121.4528557   0.0128571  -0.0107405   76.336431325
 * Poseidon         224.1896045  -0.0116272   0.0079754   84.210173144
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Swe\Planets\FictitiousPlanets;
use Swisseph\SwephFile\SwedState;

// Set ephemeris path using the proper API
$ephePath = realpath(__DIR__ . '/../../eph/ephe');
if ($ephePath === false) {
    die("Ephemeris path not found\n");
}
swe_set_ephe_path($ephePath);
echo "Ephemeris path: $ephePath\n";
echo "SwedState ephepath: " . SwedState::getInstance()->ephepath . "\n\n";

// Test date: 2020-01-01 12:00:00 UT
// JD = 2458849.5 (0h UT) + 0.5 = 2458850.0 UT
// Convert to TT: add ~69 seconds delta-T
$jd_ut = 2458850.0;
$deltaT = 69.184 / 86400.0;  // approx delta-T for 2020
$jd_tt = $jd_ut + $deltaT;

echo "Test Uranian/Fictitious Planets\n";
echo "================================\n";
echo "JD (UT): $jd_ut\n";
echo "JD (TT): $jd_tt\n\n";

// Reference values from swetest64
$reference = [
    Constants::SE_CUPIDO   => ['name' => 'Cupido',   'lon' => 271.1825036, 'lat' => 0.6725756, 'speed' => 0.0273932, 'dist' => 41.808028742],
    Constants::SE_HADES    => ['name' => 'Hades',    'lon' => 98.7386306,  'lat' => -0.9487789, 'speed' => -0.0173279, 'dist' => 49.789460312],
    Constants::SE_ZEUS     => ['name' => 'Zeus',     'lon' => 201.4412605, 'lat' => -0.0066473, 'speed' => 0.0054560, 'dist' => 59.405222671],
    Constants::SE_KRONOS   => ['name' => 'Kronos',   'lon' => 102.0454189, 'lat' => 0.0152448, 'speed' => -0.0137408, 'dist' => 63.898958264],
    Constants::SE_APOLLON  => ['name' => 'Apollon',  'lon' => 213.7486259, 'lat' => -0.0095290, 'speed' => 0.0073579, 'dist' => 70.681443799],
    Constants::SE_ADMETOS  => ['name' => 'Admetos',  'lon' => 60.8997749,  'lat' => 0.0144730, 'speed' => -0.0090495, 'dist' => 72.867674087],
    Constants::SE_VULKANUS => ['name' => 'Vulkanus', 'lon' => 121.4528557, 'lat' => 0.0128571, 'speed' => -0.0107405, 'dist' => 76.336431325],
    Constants::SE_POSEIDON => ['name' => 'Poseidon', 'lon' => 224.1896045, 'lat' => -0.0116272, 'speed' => 0.0079754, 'dist' => 84.210173144],
];

// Test FictitiousPlanets::isFictitious()
echo "Testing FictitiousPlanets::isFictitious():\n";
foreach ($reference as $ipl => $ref) {
    $result = FictitiousPlanets::isFictitious($ipl);
    echo sprintf("  SE_%s (%d): %s\n", strtoupper($ref['name']), $ipl, $result ? 'YES' : 'NO');
}
echo "\n";

// Test FictitiousPlanets::getName()
echo "Testing FictitiousPlanets::getName():\n";
foreach ($reference as $ipl => $ref) {
    $name = FictitiousPlanets::getName($ipl);
    echo sprintf("  %d: %s (expected: %s)\n", $ipl, $name, $ref['name']);
}
echo "\n";

// Test raw computation (heliocentric ecliptic cartesian)
echo "Testing FictitiousPlanets::compute() - raw heliocentric ecliptic:\n";
foreach ($reference as $ipl => $ref) {
    $serr = null;
    $xp = FictitiousPlanets::compute($jd_tt, $ipl, $serr);
    if ($xp === null) {
        echo sprintf("  %s: ERROR - %s\n", $ref['name'], $serr);
    } else {
        // Convert cartesian to spherical for display
        $r = sqrt($xp[0]*$xp[0] + $xp[1]*$xp[1] + $xp[2]*$xp[2]);
        $lon = rad2deg(atan2($xp[1], $xp[0]));
        if ($lon < 0) $lon += 360;
        $lat = rad2deg(asin($xp[2] / $r));
        echo sprintf("  %s: lon=%.4f lat=%.4f r=%.4f (raw cartesian x=%.6f y=%.6f z=%.6f)\n",
            $ref['name'], $lon, $lat, $r, $xp[0], $xp[1], $xp[2]);
    }
}
echo "\n";

// Test via swe_calc (full geocentric calculation)
echo "Testing swe_calc() - geocentric ecliptic:\n";
echo str_repeat('-', 100) . "\n";
echo sprintf("%-10s %12s %12s %12s %12s | %12s %12s\n",
    "Planet", "Lon", "Lat", "Speed", "Dist", "Lon Diff", "Dist Diff");
echo str_repeat('-', 100) . "\n";

$passed = 0;
$failed = 0;

foreach ($reference as $ipl => $ref) {
    $xx = [];
    $serr = null;
    $ret = PlanetsFunctions::calc($jd_tt, $ipl, 0, $xx, $serr);

    if ($ret < 0) {
        echo sprintf("%-10s ERROR: %s\n", $ref['name'], $serr);
        $failed++;
    } else {
        $lonDiff = $xx[0] - $ref['lon'];
        $distDiff = $xx[2] - $ref['dist'];

        echo sprintf("%-10s %12.6f %12.6f %12.6f %12.6f | %+12.6f %+12.6f\n",
            $ref['name'], $xx[0], $xx[1], $xx[3], $xx[2], $lonDiff, $distDiff);

        // Check accuracy (allow 0.1 degree for now, this is a rough first test)
        if (abs($lonDiff) < 0.1) {
            $passed++;
        } else {
            $failed++;
        }
    }
}

echo str_repeat('-', 100) . "\n";
echo "\nResult: $passed passed, $failed failed\n";
