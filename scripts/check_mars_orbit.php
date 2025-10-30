<?php

require_once __DIR__ . '/../vendor/autoload.php';

$jd_tt = 2451545.0;  // J2000.0

// Mars elements
$a = 1.523679; // AU
$e = 0.09340062;

echo "=== Mars orbital elements J2000.0 ===\n\n";
echo "Semi-major axis: $a AU\n";
echo "Eccentricity: $e\n";
echo "Perihelion: " . ($a * (1 - $e)) . " AU\n";
echo "Aphelion: " . ($a * (1 + $e)) . " AU\n\n";

// Get actual position
[$x, $y, $z] = Swisseph\Mars::heliocentricRectEclAU($jd_tt);
$r = sqrt($x**2 + $y**2 + $z**2);

echo "Actual position from Mars.php:\n";
echo "  x=$x, y=$y, z=$z\n";
echo "  r=$r AU\n\n";

if ($r < $a * (1 - $e)) {
    echo "❌ Distance LESS than perihelion!\n";
} elseif ($r > $a * (1 + $e)) {
    echo "❌ Distance GREATER than aphelion!\n";
} else {
    echo "✅ Distance within orbital range\n";
}

// Expected circular orbit speed at this distance
$GM = 1.32712440017987e+20; // m^3/s^2
$r_m = $r * 1.495978707e11; // meters
$v_circular = sqrt($GM / $r_m); // m/s
$v_circular_auday = $v_circular * 86400 / 1.495978707e11; // AU/day

echo "\nCircular orbit speed at r=$r AU:\n";
echo "  v = " . ($v_circular / 1000) . " km/s\n";
echo "  v = $v_circular_auday AU/day\n";

// Actual speed via numerical differentiation
$dt = 0.0001;
[$xp, $yp, $zp] = Swisseph\Mars::heliocentricRectEclAU($jd_tt + $dt);
[$xm, $ym, $zm] = Swisseph\Mars::heliocentricRectEclAU($jd_tt - $dt);
$vx = ($xp - $xm) / (2 * $dt);
$vy = ($yp - $ym) / (2 * $dt);
$vz = ($zp - $zm) / (2 * $dt);
$v = sqrt($vx**2 + $vy**2 + $vz**2);
$v_ms = $v * 1.495978707e11 / 86400;

echo "\nActual speed from numerical differentiation:\n";
echo "  v = " . ($v_ms / 1000) . " km/s\n";
echo "  v = $v AU/day\n";
echo "  Ratio vs circular: " . ($v / $v_circular_auday) . "\n";
