<?php
require __DIR__ . '/../vendor/autoload.php';

$tjd = 2451545.0; // J2000.0
$ipl = 4; // Mars
$iflag = 0x11A; // SEFLG_J2000 | SEFLG_XYZ | SEFLG_HELCTR | SEFLG_SPEED | SEFLG_TRUEPOS | SEFLG_NONUT

$x = [];
$serr = '';
$ret = \Swisseph\Swe\Functions\PlanetsFunctions::calc($tjd, $ipl, $iflag, $x, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Mars J2000 heliocentric XYZ:\n";
echo "Position: x={$x[0]}, y={$x[1]}, z={$x[2]} AU\n";
echo "Velocity: vx={$x[3]}, vy={$x[4]}, vz={$x[5]} AU/day\n";

$r = sqrt($x[0]*$x[0] + $x[1]*$x[1] + $x[2]*$x[2]);
$v = sqrt($x[3]*$x[3] + $x[4]*$x[4] + $x[5]*$x[5]);

echo "Distance: $r AU\n";
echo "Speed: $v AU/day = " . ($v * 149597870.7 / 86400) . " km/s\n";

// Orbital velocity for circular orbit: v = sqrt(GM/r)
$GM = 1.32712440017987e+20; // m^3/s^2
$AUNIT = 1.495978707e11; // m
$v_circ_ms = sqrt($GM / ($r * $AUNIT));  // m/s
$v_circ_auday = $v_circ_ms / $AUNIT * 86400;  // AU/day

echo "Expected circular orbit velocity: $v_circ_auday AU/day = " . ($v_circ_ms / 1000) . " km/s\n";
