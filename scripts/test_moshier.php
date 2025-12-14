<?php
/**
 * Quick test for Moshier ephemeris calculations
 * Compares PHP results with swetest64.exe -emos
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Moshier\MoshierConstants;

echo "=== Moshier Ephemeris Test ===\n\n";

// Test date: J2000.0 = 1.1.2000 12:00 TT
$jd = 2451545.0;
echo "Julian Day: $jd (J2000.0 = 1.1.2000 12:00 TT)\n\n";

// Reference values from swetest64.exe -emos -hel -b1.1.2000 -ut12:00:00
$reference = [
    0 => ['name' => 'Mercury',  'lon' => 253.7736855, 'lat' => -3.0221778, 'dist' => 0.466470761],
    1 => ['name' => 'Venus',    'lon' => 182.5935143, 'lat' =>  3.2646936, 'dist' => 0.720212465],
    2 => ['name' => 'Earth',    'lon' => 100.5002119, 'lat' =>  0.0139176, 'dist' => 0.0], // EMB approx
    3 => ['name' => 'Mars',     'lon' => 359.4388683, 'lat' => -1.4197691, 'dist' => 1.391203641],
    4 => ['name' => 'Jupiter',  'lon' =>  36.2881182, 'lat' => -1.1745991, 'dist' => 4.965382558],
    5 => ['name' => 'Saturn',   'lon' =>  45.7164527, 'lat' => -2.3031961, 'dist' => 9.183858131],
    6 => ['name' => 'Uranus',   'lon' => 316.4135648, 'lat' => -0.6848449, 'dist' => 19.924011910],
    7 => ['name' => 'Neptune',  'lon' => 303.9239270, 'lat' =>  0.2420252, 'dist' => 30.120613514],
    8 => ['name' => 'Pluto',    'lon' => 250.5411542, 'lat' => 11.1616749, 'dist' => 30.223223649],
];

echo "Comparison with swetest64.exe -emos -hel:\n";
echo str_repeat('-', 90) . "\n";
printf("%-10s %12s %12s %12s %12s %12s\n",
       "Planet", "PHP Lon", "Ref Lon", "Δ Lon (\")", "Δ Lat (\")", "Δ Dist (AU)");
echo str_repeat('-', 90) . "\n";

$maxLonError = 0;
$maxLatError = 0;

foreach ($reference as $iplm => $ref) {
    $pobj = [0.0, 0.0, 0.0];

    try {
        MoshierPlanetCalculator::moshplan2($jd, $iplm, $pobj);

        // Convert radians to degrees
        $lon = rad2deg($pobj[0]);
        $lat = rad2deg($pobj[1]);
        $dist = $pobj[2];

        // Normalize longitude to 0-360
        $lon = fmod($lon, 360.0);
        if ($lon < 0) $lon += 360.0;

        // Calculate differences
        $dLon = ($lon - $ref['lon']) * 3600;  // arcsec
        $dLat = ($lat - $ref['lat']) * 3600;  // arcsec
        $dDist = $dist - $ref['dist'];

        if ($iplm !== 2) {  // Skip Earth for max error
            $maxLonError = max($maxLonError, abs($dLon));
            $maxLatError = max($maxLatError, abs($dLat));
        }

        $status = (abs($dLon) < 60 && abs($dLat) < 60) ? '✓' : '⚠';

        printf("%-10s %12.6f %12.6f %+12.2f %+12.2f %+12.8f %s\n",
               $ref['name'], $lon, $ref['lon'], $dLon, $dLat, $dDist, $status);
    } catch (\Exception $e) {
        printf("%-10s ERROR: %s\n", $ref['name'], $e->getMessage());
    }
}

echo str_repeat('-', 90) . "\n";
printf("Max longitude error: %.2f arcsec\n", $maxLonError);
printf("Max latitude error: %.2f arcsec\n", $maxLatError);

echo "\nNote: Earth distance is 0 because this is EMB (Earth-Moon Barycenter) at origin.\n";
echo "For geocentric calculations, embofs_mosh() correction is needed.\n";
