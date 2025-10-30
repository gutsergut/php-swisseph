<?php

/**
 * Test Chebyshev interpolation with EXACT coefficients from C dump_coeffs.exe
 *
 * This test uses the exact coefficients that C produces after rot_back()
 * to verify our Chebyshev interpolation matches C exactly.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\SwephFile\ChebyshevInterpolation;

echo "Chebyshev Interpolation Test - Using EXACT C Coefficients\n";
echo "===========================================================\n\n";

// EXACT coefficients from C dump_coeffs.exe (all 26 coefficients for X, Y, Z)
$coeffs_x = [
    0.170281229489634,
    -3.615983868620031,
    1.950938447373298,
    2.752725294047829,
    -0.573325831409421,
    -0.308809290207820,
    0.085545368740277,
    0.002280356016978,
    -0.009888723583805,
    0.002884281498667,
    0.000489617794895,
    -0.000465874915399,
    0.000080612270626,
    0.000037120746065,
    -0.000022507809419,
    0.000001710857143,
    0.000002647012746,
    -0.000001136374409,
    -0.000000022146626,
    0.000000205012479,
    -0.000000079680736,
    -0.000000010020348,
    0.000000024924619,
    -0.000000009974439,
    -0.000000004975742,
    0.000000009974439
];

$coeffs_y = [
    -2.154392053893746,
    -1.391766169801750,
    -4.236458117908669,
    1.163286163967015,
    0.992679438280174,
    -0.212647250127914,
    -0.053604380513892,
    0.028038795552699,
    -0.004240819693797,
    -0.002445954100530,
    0.001164677308444,
    -0.000024729544849,
    -0.000134832295892,
    0.000044900498496,
    0.000005238092043,
    -0.000007615655594,
    0.000001456498601,
    0.000000661149761,
    -0.000000382195795,
    -0.000000010219769,
    0.000000059377446,
    -0.000000004641657,
    -0.000000013672470,
    0.000000004549838,
    0.000000004572793,
    -0.000000004549838
];

$coeffs_z = [
    -0.927652534840889,
    -0.508501096193210,
    -1.863414112531016,
    0.431603081350461,
    0.439462843588264,
    -0.083636242575548,
    -0.025058440967629,
    0.011964534562479,
    -0.001578084954217,
    -0.001118657024111,
    0.000487670384011,
    0.000000581238718,
    -0.000059812430220,
    0.000018430162002,
    0.000002776824968,
    -0.000003333302822,
    0.000000581666008,
    0.000000311061401,
    -0.000000174172046,
    -0.000000003929345,
    0.000000027391591,
    -0.000000001745753,
    -0.000000006467374,
    0.000000002193067,
    0.000000002081239,
    -0.000000002193067
];

// Parameters from C dump
$t_normalized = -0.475750000000000;
$neval = 25;

echo "Test parameters:\n";
echo "  t_normalized = $t_normalized\n";
echo "  neval = $neval\n";
echo "  ncoe = " . count($coeffs_x) . "\n\n";

// Interpolate
$result_x = ChebyshevInterpolation::evaluate($t_normalized, $coeffs_x, $neval);
$result_y = ChebyshevInterpolation::evaluate($t_normalized, $coeffs_y, $neval);
$result_z = ChebyshevInterpolation::evaluate($t_normalized, $coeffs_z, $neval);

echo "PHP Chebyshev interpolation results:\n";
echo "=====================================\n";
echo sprintf("  X = %.15f AU\n", $result_x);
echo sprintf("  Y = %.15f AU\n", $result_y);
echo sprintf("  Z = %.15f AU\n", $result_z);
echo "\n";

echo "C swi_echeb results (from dump_coeffs.exe):\n";
echo "============================================\n";
echo "  X = 3.994040678108119 AU\n";
echo "  Y = 2.733931833973663 AU\n";
echo "  Z = 1.074589136069630 AU\n";
echo "\n";

$c_x = 3.994040678108119;
$c_y = 2.733931833973663;
$c_z = 1.074589136069630;

$diff_x = $result_x - $c_x;
$diff_y = $result_y - $c_y;
$diff_z = $result_z - $c_z;

echo "Differences (PHP - C):\n";
echo "======================\n";
echo sprintf("  ΔX = %.15e AU (%.15f)\n", $diff_x, $diff_x);
echo sprintf("  ΔY = %.15e AU (%.15f)\n", $diff_y, $diff_y);
echo sprintf("  ΔZ = %.15e AU (%.15f)\n", $diff_z, $diff_z);
echo "\n";

if (abs($diff_x) < 1e-12 && abs($diff_y) < 1e-12 && abs($diff_z) < 1e-12) {
    echo "✓ SUCCESS: PHP Chebyshev matches C within tolerance (< 1e-12)\n";
} else {
    echo "✗ FAIL: Differences exceed tolerance\n";
}

echo "\n";
echo "Now testing coordinate transformation (equatorial → ecliptic):\n";
echo "==============================================================\n";

// Apply coordinate transformation using obliquity
$seps = 0.39777715572793088;  // sin(eps2000)
$ceps = 0.91748206215761929;  // cos(eps2000)

// Manual transformation (same as coortrf2)
$eq = [$result_x, $result_y, $result_z];
$ecl = [
    $eq[0],
    $eq[1] * $ceps + $eq[2] * $seps,
    -$eq[1] * $seps + $eq[2] * $ceps
];

echo "Input (assuming equatorial from Chebyshev):\n";
echo sprintf("  X = %.15f AU\n", $eq[0]);
echo sprintf("  Y = %.15f AU\n", $eq[1]);
echo sprintf("  Z = %.15f AU\n", $eq[2]);
echo "\n";

echo "After coortrf2 transformation to ecliptic:\n";
echo sprintf("  X = %.15f AU\n", $ecl[0]);
echo sprintf("  Y = %.15f AU\n", $ecl[1]);
echo sprintf("  Z = %.15f AU\n", $ecl[2]);
echo "\n";

echo "C transformation result:\n";
echo "  X = 3.994040678108119 AU\n";
echo "  Y = 2.935780426954430 AU\n";
echo "  Z = -0.101579372338750 AU\n";
echo "\n";

echo "Expected from swe_calc (xreturn[6..8]):\n";
echo "  X = 4.178312157164416 AU\n";
echo "  Y = 1.971334620196167 AU\n";
echo "  Z = -0.101781513877864 AU\n";
echo "\n";

echo "KEY OBSERVATION:\n";
echo "================\n";
echo "After transformation, X remains 3.994 AU (NOT 4.178 AU from swe_calc)\n";
echo "This means the 0.184 AU difference is NOT from coordinate transformation!\n";
echo "The problem must be elsewhere in the C code flow.\n";

