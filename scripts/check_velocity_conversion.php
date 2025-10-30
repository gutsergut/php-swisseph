<?php

// Test 2 coordinates
$lon = 327.96812521501; // degrees
$lat = -1.0676776600394; // degrees
$r = 1.8497202617423; // AU
$dlon = 0.77580982562197; // degrees/day
$dlat = 0.012469541946988; // degrees/day
$ddist = 0.0054169313543517; // AU/day

echo "=== Converting angular velocity to linear velocity ===\n\n";

// Convert to radians
$lon_rad = deg2rad($lon);
$lat_rad = deg2rad($lat);
$dlon_rad = deg2rad($dlon);
$dlat_rad = deg2rad($dlat);

echo "Angular velocity:\n";
echo "  dlon = $dlon °/day = $dlon_rad rad/day\n";
echo "  dlat = $dlat °/day = $dlat_rad rad/day\n";
echo "  ddist = $ddist AU/day\n\n";

// Spherical to rectangular position
$x = $r * cos($lat_rad) * cos($lon_rad);
$y = $r * cos($lat_rad) * sin($lon_rad);
$z = $r * sin($lat_rad);

echo "Position (spherical → rectangular):\n";
echo "  x = $x AU\n";
echo "  y = $y AU\n";
echo "  z = $z AU\n\n";

// Spherical velocity to rectangular velocity
// vx = dr/dt * cos(lat)*cos(lon) - r*sin(lat)*dlat/dt*cos(lon) - r*cos(lat)*sin(lon)*dlon/dt
// vy = dr/dt * cos(lat)*sin(lon) - r*sin(lat)*dlat/dt*sin(lon) + r*cos(lat)*cos(lon)*dlon/dt
// vz = dr/dt * sin(lat) + r*cos(lat)*dlat/dt

$vx = $ddist * cos($lat_rad) * cos($lon_rad)
    - $r * sin($lat_rad) * $dlat_rad * cos($lon_rad)
    - $r * cos($lat_rad) * sin($lon_rad) * $dlon_rad;

$vy = $ddist * cos($lat_rad) * sin($lon_rad)
    - $r * sin($lat_rad) * $dlat_rad * sin($lon_rad)
    + $r * cos($lat_rad) * cos($lon_rad) * $dlon_rad;

$vz = $ddist * sin($lat_rad)
    + $r * cos($lat_rad) * $dlat_rad;

echo "Velocity (calculated from angular):\n";
echo "  vx = $vx AU/day\n";
echo "  vy = $vy AU/day\n";
echo "  vz = $vz AU/day\n\n";

$v = sqrt($vx*$vx + $vy*$vy + $vz*$vz);
echo "Speed: $v AU/day = " . ($v * 149597870.7 / 86400) . " km/s\n\n";

echo "Actual velocity from Test 3:\n";
echo "  vx = 0.01787945414064 AU/day\n";
echo "  vy = 0.018352489955082 AU/day\n";
echo "  vz = 0.00030155785644816 AU/day\n\n";

echo "Comparison:\n";
echo "  vx ratio: " . (0.01787945414064 / $vx) . "\n";
echo "  vy ratio: " . (0.018352489955082 / $vy) . "\n";
echo "  vz ratio: " . (0.00030155785644816 / $vz) . "\n";
