<?php

require __DIR__ . '/../src/Math.php';
require __DIR__ . '/../src/Coordinates.php';

use Swisseph\Math;
use Swisseph\Coordinates;

// Use mean obliquity approx ~ 23.439291° in radians
$eps = Math::degToRad(23.439291);

// Random-ish input in radians
$lon = Math::degToRad(123.456);
$lat = Math::degToRad(-12.345);
$dist = 1.23456;

list($ra, $dec, $d1) = Coordinates::eclipticToEquatorialRad($lon, $lat, $dist, $eps);
list($lon2, $lat2, $d2) = Coordinates::equatorialToEclipticRad($ra, $dec, $d1, $eps);

$dlon = abs(Math::normAngleRad($lon2) - Math::normAngleRad($lon));
$dlon = min($dlon, abs($dlon - Math::TWO_PI)); // handle wraparound
$dlat = abs($lat2 - $lat);
$ddist = abs($d2 - $dist);

if ($dlon > 1e-12 || $dlat > 1e-12 || $ddist > 1e-12) {
    fwrite(STDERR, sprintf("Roundtrip failed: dlon=%.3e dlat=%.3e dd=%.3e\n", $dlon, $dlat, $ddist));
    exit(1);
}

echo "OK\n";
