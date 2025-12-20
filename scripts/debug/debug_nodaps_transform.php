<?php
/**
 * Debug script to compare PHP vs C transformation chain for osculating nodes
 *
 * C reference (from test_asteroid_nodes.exe DEBUG output):
 * Node lon BEFORE transformations: 100.4872458934° (xn = [-2.150182790315939, 11.615777917322994, 0.0])
 * Final C result: 100.5196455351°
 *
 * PHP result: 100.4798507476° — WRONG!
 */

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path('C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe');

$tjdEt = 2451545.0; // J2000
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

// Initial node position from osculating calculator (ecliptic XYZ, mean ecliptic of date)
// This is the xn[1] value just BEFORE the transformation chain
$xn = [-2.150182790315939, 11.615777917322994, 0.0, 0.0, 0.0, 0.0]; // From C debug

echo "=== TRANSFORMATION CHAIN DEBUG ===\n\n";
echo "Input (ecliptic cartesian, mean ecliptic of date):\n";
echo sprintf("  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);
$lon = rad2deg(atan2($xn[1], $xn[0]));
if ($lon < 0) $lon += 360;
echo sprintf("  Initial lon = %.10f°\n", $lon);

// Get obliquity and nutation
$useJ2000 = false;
$oe = \Swisseph\Obliquity::calc($tjdEt, $iflag, 0, null);
$seps = sin($oe);
$ceps = cos($oe);
echo sprintf("\nObliquity: oe=%.15f rad, seps=%.10f, ceps=%.10f\n", $oe, $seps, $ceps);

$nutModel = \Swisseph\Nutation::selectModelFromFlags($iflag);
[$dpsi, $deps] = \Swisseph\Nutation::calc($tjdEt, $nutModel, false);
// Get sin/cos of nutation in obliquity (deps)
$snut = sin($deps); // This should be sin of the nutation angle for coortrf2
$cnut = cos($deps);
echo sprintf("Nutation: dpsi=%.15f, deps=%.15f rad\n", $dpsi, $deps);
echo sprintf("          snut=%.10f, cnut=%.10f\n", $snut, $cnut);

// What C uses for nutation coortrf2:
// swed.nut.snut = sin(nut_dpsi) (see swi_nutation)
// Actually C uses swed.nut which is calculated differently
// Let me check what values C uses

echo "\n=== STEP 1: Apply nutation rotation (if is_true_nodaps && !NONUT) ===\n";
echo "C code: swi_coortrf2(xp, xp, -swed.nut.snut, swed.nut.cnut)\n";
// In C, swed.nut.snut/cnut are the sines/cosines of the NUTATION ANGLE for obliquity rotation
// This is applied BEFORE ecliptic->equator

// The nutation for coortrf2 in swecl.c line 5476 uses swed.nut.snut which is sin(true_eps - mean_eps)
// Actually it's more complex - let's just check what the C output shows

// For now, let's use deps as the nutation angle
$xTemp = [];
\Swisseph\Coordinates::coortrf2($xn, $xTemp, -$snut, $cnut);
$xn[0] = $xTemp[0];
$xn[1] = $xTemp[1];
$xn[2] = $xTemp[2];
echo sprintf("After nutation rotation:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 2: Ecliptic → Equator ===\n";
echo "C code: swi_coortrf2(xp, xp, -oe->seps, oe->ceps)\n";
$xTemp = [];
\Swisseph\Coordinates::coortrf2($xn, $xTemp, -$seps, $ceps);
$xn[0] = $xTemp[0];
$xn[1] = $xTemp[1];
$xn[2] = $xTemp[2];
echo sprintf("After ecliptic→equator:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 3: Remove nutation (swi_nutate backward) ===\n";
echo "C code: swi_nutate(xp, iflag, TRUE)\n";
// This uses the nutation matrix and applies it with backward=TRUE
// backward=TRUE in C code means: x[i] = xx[0]*M[i][0] + xx[1]*M[i][1] + xx[2]*M[i][2]
// which is transposed from forward: x[i] = xx[0]*M[0][i] + xx[1]*M[1][i] + xx[2]*M[2][i]
$nutMatrix = \Swisseph\NutationMatrix::build($dpsi, $deps, $oe, $seps, $ceps);
echo "Nutation matrix (flat): [" . implode(", ", array_map(fn($v) => sprintf("%.10f", $v), $nutMatrix)) . "]\n";
$m = $nutMatrix;
$xx = [$xn[0], $xn[1], $xn[2]];
// C backward: x[i] = xx[0]*M[i][0] + xx[1]*M[i][1] + xx[2]*M[i][2]
// Our matrix is stored as: [M[0][0], M[0][1], M[0][2], M[1][0], M[1][1], M[1][2], M[2][0], M[2][1], M[2][2]]
// So M[i][j] = $m[i*3+j]
// backward: x[i] = xx[0]*M[i][0] + xx[1]*M[i][1] + xx[2]*M[i][2]
$xTemp = [
    $xx[0] * $m[0] + $xx[1] * $m[1] + $xx[2] * $m[2],  // i=0: M[0][0..2]
    $xx[0] * $m[3] + $xx[1] * $m[4] + $xx[2] * $m[5],  // i=1: M[1][0..2]
    $xx[0] * $m[6] + $xx[1] * $m[7] + $xx[2] * $m[8],  // i=2: M[2][0..2]
];
$xn[0] = $xTemp[0];
$xn[1] = $xTemp[1];
$xn[2] = $xTemp[2];
echo sprintf("After nutate backward:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 4: Precess J_TO_J2000 ===\n";
echo "C code: swi_precess(xp, tjd_et, iflag, J_TO_J2000)\n";
\Swisseph\Precession::precess($xn, $tjdEt, $iflag, 1, null); // 1 = J_TO_J2000
echo sprintf("After precession to J2000:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 5: Add barycentric Sun position ===\n";
echo "C code: xp[j] += xsun[j]\n";

// Debug: call SwephCalculator directly
$xps = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
$serr = '';
$retc = \Swisseph\SwephFile\SwephCalculator::calculate(
    $tjdEt,
    \Swisseph\SwephFile\SwephConstants::SEI_SUNBARY,
    \Swisseph\SwephFile\SwephConstants::SEI_SUNBARY,
    \Swisseph\SwephFile\SwephConstants::SEI_FILE_PLANET,
    $iflag,
    null,
    true,
    $xps,
    $serr
);
echo sprintf("Direct SwephCalculator retc=%d, xps=[%.15f, %.15f, %.15f]\n", $retc, $xps[0], $xps[1], $xps[2]);

// Also get from SwedState directly
$swed = \Swisseph\SwephFile\SwedState::getInstance();
$sunbaryPdp = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_SUNBARY];
echo sprintf("SwedState SEI_SUNBARY: x=[%.15f, %.15f, %.15f], teval=%.10f\n",
    $sunbaryPdp->x[0] ?? 0, $sunbaryPdp->x[1] ?? 0, $sunbaryPdp->x[2] ?? 0, $sunbaryPdp->teval ?? 0);

$xsun = $xps; // Use direct result
echo sprintf("Barycentric Sun: [%.15f, %.15f, %.15f]\n", $xsun[0], $xsun[1], $xsun[2]);
for ($i = 0; $i < 3; $i++) {
    $xn[$i] += $xsun[$i];
}
echo sprintf("After adding Sun:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 6: Subtract observer (geocentric Earth) ===\n";
echo "C code: xp[j] -= xobs[j]\n";

// Use swe_calc to get Earth position properly (matching C approach)
$xxEarth = [];
$serrE = '';
// C uses pedp->x which is J2000 equatorial barycentric
// Let's get Earth with BARYCTR + EQUATORIAL + J2000 + XYZ
$earthFlags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 |
              Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR;
$retE = swe_calc($tjdEt, Constants::SE_EARTH, $earthFlags, $xxEarth, $serrE);
echo sprintf("swe_calc SE_EARTH (BARYCTR|EQUATORIAL|J2000|XYZ):\n  xear = [%.15f, %.15f, %.15f]\n",
    $xxEarth[0], $xxEarth[1], $xxEarth[2]);

$xear = $xxEarth;
for ($i = 0; $i < 3; $i++) {
    $xn[$i] -= $xear[$i];
}
echo sprintf("After subtracting observer:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

// Skip light deflection and aberration (they are small corrections)
echo "\n=== STEP 7-8: Light deflection & Aberration (SKIPPED) ===\n";

echo "\n=== STEP 9: Precess J2000_TO_J ===\n";
echo "C code: swi_precess(xp, tjd_et, iflag, J2000_TO_J)\n";
\Swisseph\Precession::precess($xn, $tjdEt, $iflag, -1, null); // -1 = J2000_TO_J
echo sprintf("After precession to date:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 10: Apply nutation (forward) ===\n";
echo "C code: swi_nutate(xp, iflag, FALSE)\n";
$nutMatrix = \Swisseph\NutationMatrix::build($dpsi, $deps, $oe, $seps, $ceps);
$xTemp = \Swisseph\NutationMatrix::apply($nutMatrix, [$xn[0], $xn[1], $xn[2]]);
$xn[0] = $xTemp[0];
$xn[1] = $xTemp[1];
$xn[2] = $xTemp[2];
echo sprintf("After nutate forward:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 11: Equator → Ecliptic ===\n";
echo "C code: swi_coortrf2(xp, xp, oe->seps, oe->ceps)\n";
$xTemp = [];
\Swisseph\Coordinates::coortrf2($xn, $xTemp, $seps, $ceps);
$xn[0] = $xTemp[0];
$xn[1] = $xTemp[1];
$xn[2] = $xTemp[2];
echo sprintf("After equator→ecliptic:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 12: Apply nutation rotation to ecliptic ===\n";
echo "C code: swi_coortrf2(xp, xp, swed.nut.snut, swed.nut.cnut)\n";
$xTemp = [];
\Swisseph\Coordinates::coortrf2($xn, $xTemp, $snut, $cnut);
$xn[0] = $xTemp[0];
$xn[1] = $xTemp[1];
$xn[2] = $xTemp[2];
echo sprintf("After final nutation:\n  xn = [%.15f, %.15f, %.15f]\n", $xn[0], $xn[1], $xn[2]);

echo "\n=== STEP 13: Convert to polar (spherical) ===\n";
$r = sqrt($xn[0]*$xn[0] + $xn[1]*$xn[1] + $xn[2]*$xn[2]);
$lon = atan2($xn[1], $xn[0]);
$lat = asin($xn[2] / $r);
$lonDeg = rad2deg($lon);
if ($lonDeg < 0) $lonDeg += 360;
$latDeg = rad2deg($lat);

echo sprintf("Final result:\n");
echo sprintf("  lon = %.10f°\n", $lonDeg);
echo sprintf("  lat = %.10f°\n", $latDeg);
echo sprintf("  r   = %.10f AU\n", $r);

echo "\n=== COMPARISON ===\n";
echo sprintf("C debug result: 100.4975326997°\n");
echo sprintf("PHP calculated: %.10f°\n", $lonDeg);
echo sprintf("Difference:     %.10f° (%.4f arcsec)\n", abs($lonDeg - 100.4975326997), abs($lonDeg - 100.4975326997) * 3600);
