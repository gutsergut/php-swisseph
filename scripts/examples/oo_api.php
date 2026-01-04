<?php

/**
 * Example: Using the Object-Oriented API
 *
 * This demonstrates the modern, fluent API for Swiss Ephemeris calculations.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Swisseph\OO\Swisseph;
use Swisseph\Constants as C;

// Initialize Swiss Ephemeris with ephemeris path
$sweph = new Swisseph(__DIR__ . '/../../eph');

// Example 1: Calculate planet position with fluent API
echo "=== Example 1: Fluent Planet Calculation ===\n";

$jd = 2451545.0; // J2000.0

$jupiter = $sweph->jupiter($jd);

if ($jupiter->isSuccess()) {
    echo "Jupiter at J2000.0:\n";
    echo "  Longitude: {$jupiter->longitude}°\n";
    echo "  Latitude: {$jupiter->latitude}°\n";
    echo "  Distance: {$jupiter->distance} AU\n";
    echo "  Speed: {$jupiter->longitudeSpeed}°/day\n";
} else {
    echo "Error: {$jupiter->error}\n";
}

echo "\n";

// Example 2: Calculate all planets
echo "=== Example 2: All Planets ===\n";

$planets = [
    'Sun' => $sweph->sun($jd),
    'Moon' => $sweph->moon($jd),
    'Mercury' => $sweph->mercury($jd),
    'Venus' => $sweph->venus($jd),
    'Mars' => $sweph->mars($jd),
    'Jupiter' => $sweph->jupiter($jd),
    'Saturn' => $sweph->saturn($jd),
];

foreach ($planets as $name => $result) {
    if ($result->isSuccess()) {
        printf("%-8s: %7.3f° (speed: %+.4f°/day)\n",
            $name,
            $result->longitude,
            $result->longitudeSpeed
        );
    }
}

echo "\n";

// Example 3: Houses calculation
echo "=== Example 3: Houses (Placidus) ===\n";

$houses = $sweph->houses($jd, 50.0, 10.0, 'P'); // Berlin coordinates

if ($houses->isSuccess()) {
    echo "Angles:\n";
    echo "  Ascendant: {$houses->ascendant}°\n";
    echo "  MC: {$houses->mc}°\n";
    echo "  Vertex: {$houses->vertex}°\n";

    echo "\nHouse Cusps:\n";
    for ($i = 1; $i <= 12; $i++) {
        printf("  House %2d: %.3f°\n", $i, $houses->getCusp($i));
    }
}

echo "\n";

// Example 4: Date conversion
echo "=== Example 4: Date Conversion ===\n";

$date = $sweph->dateFromJulianDay($jd);
printf("JD %.1f = %04d-%02d-%02d %02d:%02d:%02d\n",
    $jd,
    $date['year'],
    $date['month'],
    $date['day'],
    (int)$date['hour'],
    (int)(($date['hour'] - (int)$date['hour']) * 60),
    (int)(((($date['hour'] - (int)$date['hour']) * 60) - (int)(($date['hour'] - (int)$date['hour']) * 60)) * 60)
);

$jdFromDate = $sweph->julianDay(2000, 1, 1, 12.0);
echo "2000-01-01 12:00:00 = JD $jdFromDate\n";

echo "\n";

// Example 5: Sidereal calculations
echo "=== Example 5: Sidereal (Lahiri) ===\n";

$sweph->setSiderealMode(C::SE_SIDM_LAHIRI);
$sweph->enableSidereal();

$jupiterSid = $sweph->jupiter($jd);

if ($jupiterSid->isSuccess()) {
    echo "Jupiter (Sidereal/Lahiri):\n";
    echo "  Longitude: {$jupiterSid->longitude}°\n";

    // Calculate difference
    $diff = $jupiter->longitude - $jupiterSid->longitude;
    echo "  Ayanamsha: {$diff}°\n";
}

// Disable sidereal for next calculations
$sweph->disableSidereal();

echo "\n";

// Example 6: Topocentric calculations
echo "=== Example 6: Topocentric Moon ===\n";

$sweph->setTopocentric(10.0, 50.0, 100.0); // Berlin, 100m altitude

$moonTopo = $sweph->moon($jd);

if ($moonTopo->isSuccess()) {
    echo "Moon (Topocentric, Berlin):\n";
    echo "  Longitude: {$moonTopo->longitude}°\n";
    echo "  Latitude: {$moonTopo->latitude}°\n";
    echo "  Distance: {$moonTopo->distance} AU\n";
}

$sweph->disableTopocentric();

echo "\n";

// Example 7: Equatorial coordinates
echo "=== Example 7: Equatorial Coordinates (RA/Dec) ===\n";

$sweph->enableEquatorial();

$jupiterEq = $sweph->jupiter($jd);

if ($jupiterEq->isSuccess()) {
    echo "Jupiter (Equatorial):\n";
    echo "  Right Ascension: {$jupiterEq->rightAscension}°\n";
    echo "  Declination: {$jupiterEq->declination}°\n";
    echo "  Distance: {$jupiterEq->distance} AU\n";

    // Convert RA to hours
    $raHours = $jupiterEq->rightAscension / 15.0;
    $raH = (int)$raHours;
    $raM = (int)(($raHours - $raH) * 60);
    $raS = ((($raHours - $raH) * 60) - $raM) * 60;
    printf("  RA (h:m:s): %02dh %02dm %.2fs\n", $raH, $raM, $raS);
}

$sweph->disableEquatorial();

echo "\n";

// Example 8: Custom flags
echo "=== Example 8: Custom Calculation Flags ===\n";

$sweph->setDefaultFlags(
    C::SEFLG_SWIEPH |
    C::SEFLG_SPEED |
    C::SEFLG_EQUATORIAL |
    C::SEFLG_SIDEREAL
);

$sweph->setSiderealMode(C::SE_SIDM_FAGAN_BRADLEY);

$saturn = $sweph->saturn($jd);

if ($saturn->isSuccess()) {
    echo "Saturn (Sidereal/Fagan-Bradley, Equatorial):\n";
    echo "  Right Ascension: {$saturn->rightAscension}°\n";
    echo "  Declination: {$saturn->declination}°\n";
}

echo "\n=== Examples Complete ===\n";
