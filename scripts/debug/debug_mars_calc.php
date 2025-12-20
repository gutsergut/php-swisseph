<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

$tjd = 2451545.0; // J2000.0
$ipl = Constants::SE_MARS;

echo "=== Testing Mars coordinates ===\n\n";

// Test 1: Simple geocentric ecliptic
echo "Test 1: Geocentric ecliptic (default)\n";
$x1 = [];
$serr = '';
$iflag1 = Constants::SEFLG_SPEED;
$ret = PlanetsFunctions::calc($tjd, $ipl, $iflag1, $x1, $serr);
echo "iflag=0x" . dechex($iflag1) . " ret=$ret\n";
echo "Position: lon={$x1[0]}°, lat={$x1[1]}°, dist={$x1[2]} AU\n";
echo "Speed: dlon={$x1[3]}°/day, dlat={$x1[4]}°/day, ddist={$x1[5]} AU/day\n\n";

// Test 2: Heliocentric ecliptic
echo "Test 2: Heliocentric ecliptic\n";
$x2 = [];
$iflag2 = Constants::SEFLG_HELCTR | Constants::SEFLG_SPEED;
$ret = PlanetsFunctions::calc($tjd, $ipl, $iflag2, $x2, $serr);
echo "iflag=0x" . dechex($iflag2) . " ret=$ret\n";
echo "Position: lon={$x2[0]}°, lat={$x2[1]}°, dist={$x2[2]} AU\n";
echo "Speed: dlon={$x2[3]}°/day, dlat={$x2[4]}°/day, ddist={$x2[5]} AU/day\n\n";

// Test 3: Heliocentric rectangular (J2000)
echo "Test 3: Heliocentric rectangular J2000 (как в osculating)\n";
$x3 = [];
$iflag3 = Constants::SEFLG_HELCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ |
          Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$ret = PlanetsFunctions::calc($tjd, $ipl, $iflag3, $x3, $serr);
echo "iflag=0x" . dechex($iflag3) . " ret=$ret\n";
echo "Position: x={$x3[0]}, y={$x3[1]}, z={$x3[2]} AU\n";
echo "Velocity: vx={$x3[3]}, vy={$x3[4]}, vz={$x3[5]} AU/day\n";

$r = sqrt($x3[0]*$x3[0] + $x3[1]*$x3[1] + $x3[2]*$x3[2]);
$v = sqrt($x3[3]*$x3[3] + $x3[4]*$x3[4] + $x3[5]*$x3[5]);
echo "Distance: $r AU\n";
echo "Speed: $v AU/day = " . ($v * 149597870.7 / 86400) . " km/s\n";

// Expected values for Mars heliocentric on J2000.0
echo "\nExpected values for Mars on J2000.0:\n";
echo "Distance: ~1.4-1.6 AU\n";
echo "Speed: ~24 km/s (circular orbit)\n\n";

// Test 4: Check if problem is in coordinate conversion
echo "Test 4: Heliocentric ecliptic → manual conversion to rectangular\n";
if ($x2[2] > 0 && $x2[2] < 10) {
    $lon = deg2rad($x2[0]);
    $lat = deg2rad($x2[1]);
    $r = $x2[2];

    $x_rect = $r * cos($lat) * cos($lon);
    $y_rect = $r * cos($lat) * sin($lon);
    $z_rect = $r * sin($lat);

    echo "Manual rectangular: x=$x_rect, y=$y_rect, z=$z_rect AU\n";
    $r_check = sqrt($x_rect*$x_rect + $y_rect*$y_rect + $z_rect*$z_rect);
    echo "Distance check: $r_check AU\n";
}

if ($ret < 0) {
    echo "\nERROR: $serr\n";
}
