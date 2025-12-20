<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\NutationMatrix;
use Swisseph\Nutation;
use Swisseph\Obliquity;
use Swisseph\Math;

echo "=== Nutation Matrix Tests ===\n\n";

// Test epoch: J2000.0
$jd = 2451545.0;

// Get nutation
[$dpsi, $deps] = Nutation::calcIau2000A($jd);
echo "Nutation at J2000.0:\n";
echo sprintf("  dpsi = %.6f\" (%.10f rad)\n", $dpsi * 3600, $dpsi);
echo sprintf("  deps = %.6f\" (%.10f rad)\n", $deps * 3600, $deps);

// Get mean obliquity
$epsMean = Obliquity::calc($jd, 0, 0, null);
echo sprintf("  Mean obliquity = %.6f° (%.10f rad)\n", Math::radToDeg($epsMean), $epsMean);
echo "\n";

// Build nutation matrix
$matrix = NutationMatrix::build($dpsi, $deps, $epsMean);

echo "Nutation matrix (3x3):\n";
for ($i = 0; $i < 3; $i++) {
    echo sprintf(
        "  [%14.10f %14.10f %14.10f]\n",
        $matrix[$i * 3 + 0],
        $matrix[$i * 3 + 1],
        $matrix[$i * 3 + 2]
    );
}
echo "\n";

// Verify matrix properties
// 1. Check determinant (should be ~1 for rotation matrix)
$det =
    $matrix[0] * ($matrix[4] * $matrix[8] - $matrix[5] * $matrix[7]) -
    $matrix[1] * ($matrix[3] * $matrix[8] - $matrix[5] * $matrix[6]) +
    $matrix[2] * ($matrix[3] * $matrix[7] - $matrix[4] * $matrix[6]);

echo sprintf("Determinant: %.15f (should be ~1)\n", $det);

// 2. Check orthogonality: M * M^T should be identity
// Calculate first row of M * M^T
$mtm00 = $matrix[0] * $matrix[0] + $matrix[1] * $matrix[1] + $matrix[2] * $matrix[2];
$mtm01 = $matrix[0] * $matrix[3] + $matrix[1] * $matrix[4] + $matrix[2] * $matrix[5];
$mtm02 = $matrix[0] * $matrix[6] + $matrix[1] * $matrix[7] + $matrix[2] * $matrix[8];

echo sprintf("M*M^T[0,0]: %.15f (should be ~1)\n", $mtm00);
echo sprintf("M*M^T[0,1]: %.15e (should be ~0)\n", $mtm01);
echo sprintf("M*M^T[0,2]: %.15e (should be ~0)\n", $mtm02);
echo "\n";

// Test applying matrix to a vector
$testVec = [1.0, 0.0, 0.0]; // Unit vector along X axis
$result = NutationMatrix::apply($matrix, $testVec);

echo "Apply matrix to test vector [1, 0, 0]:\n";
echo sprintf("  Result: [%.10f, %.10f, %.10f]\n", $result[0], $result[1], $result[2]);
echo sprintf("  (Should be first column of matrix)\n\n");

// Test at different epoch (current date)
$jd2024 = 2460676.5; // ~2025-01-01
[$dpsi2, $deps2] = Nutation::calcIau2000A($jd2024);
$epsMean2 = Obliquity::calc($jd2024, 0, 0, null);
$matrix2 = NutationMatrix::build($dpsi2, $deps2, $epsMean2);

echo "Nutation at 2025-01-01:\n";
echo sprintf("  dpsi = %.6f\"\n", $dpsi2 * 3600);
echo sprintf("  deps = %.6f\"\n", $deps2 * 3600);

$det2 =
    $matrix2[0] * ($matrix2[4] * $matrix2[8] - $matrix2[5] * $matrix2[7]) -
    $matrix2[1] * ($matrix2[3] * $matrix2[8] - $matrix2[5] * $matrix2[6]) +
    $matrix2[2] * ($matrix2[3] * $matrix2[7] - $matrix2[4] * $matrix2[6]);

echo sprintf("  Determinant: %.15f (should be ~1)\n", $det2);

echo "\n✅ All tests complete!\n";
