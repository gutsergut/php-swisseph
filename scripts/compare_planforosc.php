<?php
/**
 * Compare planForOscElem output between PHP and C
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Enable debug mode
putenv('DEBUG_OSCU=1');

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd_et = 2451545.0007388; // Same as C

echo "=== Compare planForOscElem ===\n\n";

// Get equatorial J2000 XYZ from swe_calc (same flags as C)
$iflJ2000 = Constants::SEFLG_SWIEPH |
            Constants::SEFLG_J2000 |
            Constants::SEFLG_EQUATORIAL |
            Constants::SEFLG_XYZ |
            Constants::SEFLG_TRUEPOS |
            Constants::SEFLG_NONUT |
            Constants::SEFLG_SPEED |
            Constants::SEFLG_HELCTR;

$x = [];
$serr = '';
$ret = swe_calc($tjd_et, Constants::SE_JUPITER, $iflJ2000, $x, $serr);

echo "PHP swe_calc:\n";
printf("  pos=[%.15f, %.15f, %.15f] AU\n", $x[0], $x[1], $x[2]);
printf("  vel=[%.15f, %.15f, %.15f] AU/day\n", $x[3], $x[4], $x[5]);

echo "\nC swe_calc (reference):\n";
echo "  pos=[4.001173648382598, 2.736583520466859, 1.075513616827893] AU\n";
echo "  vel=[-0.004568322760392, 0.005881457158385, 0.002632301855108] AU/day\n";

echo "\nDifferences:\n";
$c_pos = [4.001173648382598, 2.736583520466859, 1.075513616827893];
$c_vel = [-0.004568322760392, 0.005881457158385, 0.002632301855108];
printf("  dx=%.2e, dy=%.2e, dz=%.2e AU\n",
    $x[0] - $c_pos[0], $x[1] - $c_pos[1], $x[2] - $c_pos[2]);
printf("  dvx=%.2e, dvy=%.2e, dvz=%.2e AU/day\n",
    $x[3] - $c_vel[0], $x[4] - $c_vel[1], $x[5] - $c_vel[2]);

// Now manually run planForOscElem
echo "\n=== Running planForOscElem manually ===\n";

// The input is equatorial J2000 XYZ
$xx = [$x[0], $x[1], $x[2], $x[3], $x[4], $x[5]];

// iflg0 = (iflag & (SEFLG_EPHMASK|SEFLG_NONUT)) | SEFLG_SPEED | SEFLG_TRUEPOS;
$iflg0 = (Constants::SEFLG_SWIEPH | Constants::SEFLG_NONUT) |
         Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS | Constants::SEFLG_HELCTR;

// Step 1: ICRS to J2000 (frame bias)
echo "\nStep 1: Frame bias\n";
$xx = \Swisseph\Bias::apply(
    $xx,
    $tjd_et,
    $iflg0,
    \Swisseph\Bias::MODEL_IAU_2006,
    false,
    \Swisseph\JplHorizonsApprox::SEMOD_JPLHORA_DEFAULT
);
printf("  After bias: [%.10f, %.10f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
printf("  Velocity:   [%.15f, %.15f, %.15f]\n", $xx[3], $xx[4], $xx[5]);

// Step 2: Precession J2000 -> date
echo "\nStep 2: Precession to date\n";
\Swisseph\Precession::precess($xx, $tjd_et, $iflg0, -1, null);
$vel = [$xx[3], $xx[4], $xx[5]];
\Swisseph\Precession::precess($vel, $tjd_et, $iflg0, -1, null);
$xx[3] = $vel[0];
$xx[4] = $vel[1];
$xx[5] = $vel[2];
printf("  After prec: [%.10f, %.10f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
printf("  Velocity:   [%.15f, %.15f, %.15f]\n", $xx[3], $xx[4], $xx[5]);

// Calculate obliquity
$eps = \Swisseph\Obliquity::calc($tjd_et, $iflg0, 0, null);
$seps = sin($eps);
$ceps = cos($eps);
printf("\nObliquity: eps=%.10f rad, seps=%.10f, ceps=%.10f\n", $eps, $seps, $ceps);

// Step 3: Nutation (equatorial mean -> true)
echo "\nStep 3: Nutation\n";
$nutModel = \Swisseph\Nutation::selectModelFromFlags($iflg0);
[$dpsi, $deps] = \Swisseph\Nutation::calc($tjd_et, $nutModel, false);
printf("  dpsi=%.15f rad, deps=%.15f rad\n", $dpsi, $deps);

$nutMatrix = \Swisseph\NutationMatrix::build($dpsi, $deps, $eps, $seps, $ceps);
$xTemp = \Swisseph\NutationMatrix::apply($nutMatrix, $xx);
$velTemp = \Swisseph\NutationMatrix::apply($nutMatrix, [$xx[3], $xx[4], $xx[5]]);
$xx = [$xTemp[0], $xTemp[1], $xTemp[2], $velTemp[0], $velTemp[1], $velTemp[2]];

printf("  After nutation: [%.10f, %.10f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
printf("  Velocity:       [%.15f, %.15f, %.15f]\n", $xx[3], $xx[4], $xx[5]);

echo "\nC after nutation (Step 3):\n";
echo "  [4.0013720082, 2.7363659692, 1.0753291605]\n";
echo "  Velocity: [-0.0045678878, 0.0064435094, 0.0000755820]\n";

// Wait - C shows velocity AFTER ecliptic transform includes vz ~ 7.5e-5
// but before that should be equatorial velocity. Let me check.

// Step 4: Transform to ecliptic
echo "\nStep 4: Transform to ecliptic\n";
$xOut = [];
\Swisseph\Coordinates::coortrf2([$xx[0], $xx[1], $xx[2]], $xOut, $seps, $ceps);
$velOut = [];
\Swisseph\Coordinates::coortrf2([$xx[3], $xx[4], $xx[5]], $velOut, $seps, $ceps);
$xx = [$xOut[0], $xOut[1], $xOut[2], $velOut[0], $velOut[1], $velOut[2]];

printf("  After ecliptic: [%.10f, %.10f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
printf("  Velocity:       [%.15f, %.15f, %.15f]\n", $xx[3], $xx[4], $xx[5]);

echo "\nC after ecliptic transform:\n";
echo "  [4.0013720082, 2.9383080879, -0.1018680589]\n";

// Step 5: Apply nutation to ecliptic
echo "\nStep 5: Nutation to ecliptic\n";
$snut = sin($deps);
$cnut = cos($deps);

$xOut = [];
\Swisseph\Coordinates::coortrf2([$xx[0], $xx[1], $xx[2]], $xOut, $snut, $cnut);
$velOut = [];
\Swisseph\Coordinates::coortrf2([$xx[3], $xx[4], $xx[5]], $velOut, $snut, $cnut);
$xx = [$xOut[0], $xOut[1], $xOut[2], $velOut[0], $velOut[1], $velOut[2]];

printf("  Final: [%.10f, %.10f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
printf("  Velocity: [%.15f, %.15f, %.15f]\n", $xx[3], $xx[4], $xx[5]);

echo "\nC final (swi_plan_for_osc_elem OUTPUT):\n";
echo "  [4.0013720082, 2.9383109363, -0.1017858660]\n";
echo "  Velocity: [-0.0045678878, 0.0064435094, 0.0000755820]\n";

// Calculate node vector
echo "\n=== Node vector calculation ===\n";
$z = $xx[2];
$vz = $xx[5];
$fac = $z / $vz;
$sgn = $vz / abs($vz);

printf("  z=%.15f, vz=%.15f\n", $z, $vz);
printf("  fac=%.10f days\n", $fac);

$xn = [];
for ($j = 0; $j <= 2; $j++) {
    $xn[$j] = ($xx[$j] - $fac * $xx[$j + 3]) * $sgn;
}

printf("  xn=[%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);
$node_lon = rad2deg(atan2($xn[1], $xn[0]));
if ($node_lon < 0) $node_lon += 360.0;
printf("  node lon = %.10f°\n", $node_lon);

echo "\nC node vector inputs [1]:\n";
echo "  fac=-1346.694563087260121\n";
echo "  xn=[-2.150177618015464, 11.615749959117714, 0.000000000000000]\n";
echo "  -> node lon=100.4872459078°, r=11.8130821931 AU\n";
