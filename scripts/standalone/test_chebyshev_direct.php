<?php

// Test Chebyshev interpolation directly with known coefficients

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\SwephFile\ChebyshevInterpolation;

// Known coefficients from PHP rotateBack output (all 26)
$coeffs = [
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
    -0.000022507840871,
    0.000001710857143,
    0.000002647012746,
    -0.000001136325484,
    -0.000000022174583,
    0.000000205012479,
    -0.000000079680736,
    -0.000000010023934,
    0.000000024924619,
    -0.000000009967983,
    -0.000000004972873,
    0.000000009974439
];

// t_normalized from PHP log: -0.4757500000
$t = -0.4757500000;
$neval = 25; // From PHP log: neval=25

echo "Testing Chebyshev interpolation:\n";
echo "================================\n\n";
echo "Coefficients (first 10):\n";
for ($i = 0; $i < 10; $i++) {
    echo sprintf("  coeff[%2d] = %.15f\n", $i, $coeffs[$i]);
}
echo "\n";

echo "t_normalized = $t\n";
echo "neval = $neval\n\n";

$result = ChebyshevInterpolation::evaluate($t, $coeffs, $neval);

echo "Result from Chebyshev::evaluate() = " . sprintf("%.15f", $result) . "\n";
echo "Expected from PHP log             = 3.994040678060742\n";
echo "Expected from C                   = 4.178312157164416\n";
echo "\n";
echo "Difference PHP - C                = " . sprintf("%.15f", 3.994040678060742 - 4.178312157164416) . " AU\n";

