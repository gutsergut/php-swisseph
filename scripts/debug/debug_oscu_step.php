<?php
/**
 * Debug osculating nodes calculation step by step
 * Compare each step with C code output
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_ut = 2451545.0; // J2000 at UT 12:00
$tjd_et = $tjd_ut + swe_deltat($tjd_ut);

echo "=== Debug Osculating Nodes Calculation ===\n\n";
echo "tjd_et = $tjd_et\n\n";

// Step 1: Get heliocentric position to determine dt
$iflg0 = (Constants::SEFLG_SWIEPH | Constants::SEFLG_NONUT) |
         Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS | Constants::SEFLG_HELCTR;

$x = [];
$serr = '';
$ret = swe_calc($tjd_et, Constants::SE_JUPITER, $iflg0, $x, $serr);

echo "Step 1: Initial heliocentric polar position\n";
echo "  iflg0 = 0x" . dechex($iflg0) . "\n";
printf("  x = [lon=%.10f°, lat=%.10f°, dist=%.10f AU]\n", $x[0], $x[1], $x[2]);
printf("  v = [dlon=%.15f, dlat=%.15f, ddist=%.15f]\n", $x[3], $x[4], $x[5]);

// Calculate dt like C code
$dt = 0.0001 * 10 * $x[2]; // NODE_CALC_INTV * 10 * distance
echo "\ndt = $dt days (NODE_CALC_INTV * 10 * dist)\n\n";

// Step 2: Get equatorial J2000 XYZ positions at t-dt, t, t+dt
$iflJ2000 = Constants::SEFLG_SWIEPH |
            Constants::SEFLG_J2000 |
            Constants::SEFLG_EQUATORIAL |
            Constants::SEFLG_XYZ |
            Constants::SEFLG_TRUEPOS |
            Constants::SEFLG_NONUT |
            Constants::SEFLG_SPEED |
            Constants::SEFLG_HELCTR;

echo "Step 2: Equatorial J2000 XYZ positions\n";
echo "  iflJ2000 = 0x" . dechex($iflJ2000) . "\n\n";

$xpos = [];
for ($i = 0; $i <= 2; $i++) {
    $t = $tjd_et + ($i - 1) * $dt;
    $xpos[$i] = [];
    $ret = swe_calc($t, Constants::SE_JUPITER, $iflJ2000, $xpos[$i], $serr);

    echo "  [$i] t=$t\n";
    printf("      pos=[%.15f, %.15f, %.15f] AU\n",
        $xpos[$i][0], $xpos[$i][1], $xpos[$i][2]);
    printf("      vel=[%.15f, %.15f, %.15f] AU/day\n",
        $xpos[$i][3], $xpos[$i][4], $xpos[$i][5]);
}

// The node vector formula: xn = (x - (z/vz) * v) * sgn(vz)
// This is where the error comes from - vz is ~1e-10 off from C
echo "\nStep 3: Node vector calculation (at i=1)\n";
$i = 1;
$z = $xpos[$i][2];
$vz = $xpos[$i][5];
$fac = $z / $vz;
$sgn = $vz / abs($vz);

printf("  z  = %.15f AU\n", $z);
printf("  vz = %.15f AU/day\n", $vz);
printf("  fac = z/vz = %.10f days\n", $fac);
printf("  sgn = %.1f\n", $sgn);

// Calculate node vector
$xn = [];
for ($j = 0; $j <= 2; $j++) {
    $xn[$j] = ($xpos[$i][$j] - $fac * $xpos[$i][$j + 3]) * $sgn;
}

printf("  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

// Node longitude
$node_lon = rad2deg(atan2($xn[1], $xn[0]));
if ($node_lon < 0) $node_lon += 360.0;
printf("  Raw equatorial node lon = %.10f°\n", $node_lon);

echo "\n=== C REFERENCE VALUES (from debug output) ===\n";
echo "Expected ascending node = 100.5194687°\n";
echo "PHP ascending node      = " . $node_lon . " (before full transform)\n";

// The key issue: fac is calculated from velocities
// If C has vz = X and PHP has vz = X + 1e-10
// And z ~ 2 AU, the difference in node position = 2 * 1e-10 / vz^2 * vz ~ 2 * 1e-10 / vz
// If vz ~ 0.001, then error ~ 2 * 1e-10 / 0.001 = 2e-7 AU
// At distance ~5 AU, angle error = 2e-7 / 5 radians ~ 8e-9 radians ~ 0.0017"

// But wait - the issue is much bigger (20")
// Let's check what the actual vz values should be

echo "\n=== SENSITIVITY ANALYSIS ===\n";
$vz_values = [$vz, $vz * 1.001, $vz * 0.999, $vz + 1e-10, $vz - 1e-10];
foreach ($vz_values as $test_vz) {
    $test_fac = $z / $test_vz;
    $test_xn = [];
    for ($j = 0; $j <= 2; $j++) {
        $test_xn[$j] = ($xpos[$i][$j] - $test_fac * $xpos[$i][$j + 3]) * $sgn;
    }
    $test_lon = rad2deg(atan2($test_xn[1], $test_xn[0]));
    if ($test_lon < 0) $test_lon += 360.0;
    printf("  vz=%.15f -> lon=%.10f° (diff from PHP = %.2f\")\n",
        $test_vz, $test_lon, ($test_lon - $node_lon) * 3600);
}
