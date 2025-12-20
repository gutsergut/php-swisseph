<?php
/**
 * Analyze node longitude sensitivity to velocity errors
 */

// PHP values from our test
$php_z = -0.101785867946795;
$php_vz = 0.000075581851592;
$php_fac = $php_z / $php_vz;

// C values (from debug)
$c_z = -0.101785865951730;
$c_vz = 0.000075581998132;
$c_fac = $c_z / $c_vz;

echo "=== Z and VZ comparison ===\n";
printf("PHP: z=%.15f, vz=%.15f, fac=%.10f\n", $php_z, $php_vz, $php_fac);
printf("C:   z=%.15f, vz=%.15f, fac=%.10f\n", $c_z, $c_vz, $c_fac);
printf("Diff: dz=%.2e, dvz=%.2e, dfac=%.6f\n",
    $php_z - $c_z, $php_vz - $c_vz, $php_fac - $c_fac);

// PHP position after planForOscElem
$php_pos = [4.0013720171, 2.9383109217, -0.1017858679];
$php_vel = [-0.004567887673377, 0.006443509201730, 0.000075581851592];

// C position after swi_plan_for_osc_elem (from DEBUG output)
$c_pos = [4.001372008199768, 2.938310936315646, -0.101785865951730];
$c_vel = [-0.004567887771161, 0.006443509360363, 0.000075581998132];

echo "\n=== Position and velocity comparison ===\n";
for ($i = 0; $i < 3; $i++) {
    printf("pos[%d]: PHP=%.15f, C=%.15f, diff=%.2e\n",
        $i, $php_pos[$i], $c_pos[$i], $php_pos[$i] - $c_pos[$i]);
}
for ($i = 0; $i < 3; $i++) {
    printf("vel[%d]: PHP=%.15f, C=%.15f, diff=%.2e\n",
        $i, $php_vel[$i], $c_vel[$i], $php_vel[$i] - $c_vel[$i]);
}

// Calculate node vector for both
echo "\n=== Node vector comparison ===\n";

$php_xn = [];
for ($j = 0; $j <= 2; $j++) {
    $php_xn[$j] = ($php_pos[$j] - $php_fac * $php_vel[$j]) * 1.0; // sgn=1
}

$c_xn = [];
for ($j = 0; $j <= 2; $j++) {
    $c_xn[$j] = ($c_pos[$j] - $c_fac * $c_vel[$j]) * 1.0;
}

printf("PHP xn: [%.15f, %.15f, %.15f]\n", $php_xn[0], $php_xn[1], $php_xn[2]);
printf("C xn:   [%.15f, %.15f, %.15f]\n", $c_xn[0], $c_xn[1], $c_xn[2]);
for ($i = 0; $i < 3; $i++) {
    printf("diff[%d]: %.2e AU\n", $i, $php_xn[$i] - $c_xn[$i]);
}

// Calculate longitude
$php_lon = rad2deg(atan2($php_xn[1], $php_xn[0]));
$c_lon = rad2deg(atan2($c_xn[1], $c_xn[0]));
if ($php_lon < 0) $php_lon += 360;
if ($c_lon < 0) $c_lon += 360;

printf("\nPHP node lon: %.10f°\n", $php_lon);
printf("C node lon:   %.10f°\n", $c_lon);
printf("Difference:   %.2f arcsec\n", ($php_lon - $c_lon) * 3600);

// What if we used exact C values?
echo "\n=== Using exact C values for vz ===\n";
$test_fac = $php_z / $c_vz;  // Use C's vz with PHP's z
$test_xn = [];
for ($j = 0; $j <= 2; $j++) {
    $test_xn[$j] = ($php_pos[$j] - $test_fac * $php_vel[$j]) * 1.0;
}
$test_lon = rad2deg(atan2($test_xn[1], $test_xn[0]));
if ($test_lon < 0) $test_lon += 360;
printf("With C's vz, node lon = %.10f° (diff from C = %.2f\")\n",
    $test_lon, ($test_lon - $c_lon) * 3600);

// The real issue: the velocity difference
echo "\n=== Velocity error analysis ===\n";
$dvz = $php_vz - $c_vz;
printf("dvz = %.2e AU/day\n", $dvz);
// Effect on fac: d(fac) = z * (-1/vz^2) * dvz = -fac * dvz/vz
$dfac_from_dvz = -$c_fac * $dvz / $c_vz;
printf("Predicted dfac from dvz = %.6f days\n", $dfac_from_dvz);
printf("Actual dfac = %.6f days\n", $php_fac - $c_fac);
// Effect on xn: d(xn) = -dfac * vel - fac * dvel
// For xn[1] (which determines lon most):
$d_xn1_from_dfac = -($php_fac - $c_fac) * $php_vel[1];
$d_xn1_from_dvel = -$php_fac * ($php_vel[1] - $c_vel[1]);
printf("d_xn1 from dfac = %.2e, from dvel = %.2e, total = %.2e\n",
    $d_xn1_from_dfac, $d_xn1_from_dvel, $d_xn1_from_dfac + $d_xn1_from_dvel);
printf("Actual d_xn1 = %.2e\n", $php_xn[1] - $c_xn[1]);
