<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Bias;
use Swisseph\Constants;

echo "=== Frame Bias Tests ===\n\n";

// Test vector: position at J2000.0 [x, y, z] in AU
$testPos = [1.0, 0.5, 0.25];

echo "Test position (ICRS): [" . implode(', ', $testPos) . "]\n\n";

// Test parameters
$tjd = 2451545.0; // J2000.0
$iflag = 0; // No special flags for basic test

// Test IAU 2006 model (default)
echo "--- IAU 2006 Model ---\n";
$matrix2006 = Bias::getMatrix(Bias::MODEL_IAU_2006);
echo "Bias matrix (first row): [" . sprintf('%.15f, %.15f, %.15f', $matrix2006[0], $matrix2006[1], $matrix2006[2]) . "]\n";

// Apply forward transformation (ICRS → J2000)
$j2000_2006 = Bias::apply($testPos, $tjd, $iflag, Bias::MODEL_IAU_2006, false);
echo "After ICRS → J2000:      [" . sprintf('%.15f, %.15f, %.15f', $j2000_2006[0], $j2000_2006[1], $j2000_2006[2]) . "]\n";

// Apply backward transformation (J2000 → ICRS)
$icrs_back = Bias::apply($j2000_2006, $tjd, $iflag, Bias::MODEL_IAU_2006, true);
echo "After J2000 → ICRS:      [" . sprintf('%.15f, %.15f, %.15f', $icrs_back[0], $icrs_back[1], $icrs_back[2]) . "]\n";

// Check round-trip error
$error = sqrt(
    pow($icrs_back[0] - $testPos[0], 2) +
    pow($icrs_back[1] - $testPos[1], 2) +
    pow($icrs_back[2] - $testPos[2], 2)
);
echo "Round-trip error: " . sprintf('%.2e', $error) . " AU (should be ~0)\n\n";

// Test IAU 2000 model
echo "--- IAU 2000 Model ---\n";
$matrix2000 = Bias::getMatrix(Bias::MODEL_IAU_2000);
echo "Bias matrix (first row): [" . sprintf('%.15f, %.15f, %.15f', $matrix2000[0], $matrix2000[1], $matrix2000[2]) . "]\n";

$j2000_2000 = Bias::apply($testPos, $tjd, $iflag, Bias::MODEL_IAU_2000, false);
echo "After ICRS → J2000:      [" . sprintf('%.15f, %.15f, %.15f', $j2000_2000[0], $j2000_2000[1], $j2000_2000[2]) . "]\n";

// Difference between models
$diff = [
    $j2000_2006[0] - $j2000_2000[0],
    $j2000_2006[1] - $j2000_2000[1],
    $j2000_2006[2] - $j2000_2000[2],
];
$diffMag = sqrt($diff[0]**2 + $diff[1]**2 + $diff[2]**2);
echo "Difference 2006-2000:    [" . sprintf('%.2e, %.2e, %.2e', $diff[0], $diff[1], $diff[2]) . "] (mag: " . sprintf('%.2e', $diffMag) . " AU)\n\n";

// Test with velocities
echo "--- Test with velocities ---\n";
$testPosVel = [1.0, 0.5, 0.25, 0.01, -0.005, 0.002]; // [x, y, z, dx, dy, dz]
$iflagSpeed = Constants::SEFLG_SPEED;
$j2000Vel = Bias::apply($testPosVel, $tjd, $iflagSpeed, Bias::MODEL_IAU_2006, false);
echo "Position+velocity (ICRS):  [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $testPosVel)) . "]\n";
echo "Position+velocity (J2000): [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $j2000Vel)) . "]\n";

// Round-trip with velocities
$icrsVelBack = Bias::apply($j2000Vel, $tjd, $iflagSpeed, Bias::MODEL_IAU_2006, true);
$errorPos = sqrt(($icrsVelBack[0] - $testPosVel[0])**2 + ($icrsVelBack[1] - $testPosVel[1])**2 + ($icrsVelBack[2] - $testPosVel[2])**2);
$errorVel = sqrt(($icrsVelBack[3] - $testPosVel[3])**2 + ($icrsVelBack[4] - $testPosVel[4])**2 + ($icrsVelBack[5] - $testPosVel[5])**2);
echo "Round-trip error (pos):    " . sprintf('%.2e', $errorPos) . " AU\n";
echo "Round-trip error (vel):    " . sprintf('%.2e', $errorVel) . " AU/day\n\n";

// Test MODEL_NONE
echo "--- MODEL_NONE Test ---\n";
$noChange = Bias::apply($testPos, $tjd, $iflag, Bias::MODEL_NONE, false);
$unchanged = ($noChange[0] === $testPos[0] && $noChange[1] === $testPos[1] && $noChange[2] === $testPos[2]);
echo "Input:  [" . implode(', ', $testPos) . "]\n";
echo "Output: [" . implode(', ', $noChange) . "]\n";
echo "Unchanged: " . ($unchanged ? "✓ YES" : "✗ NO") . "\n\n";

// Verify matrix properties
echo "--- Matrix Properties ---\n";
// Determinant should be ~1
$m = $matrix2006;
$det =
    $m[0] * ($m[4] * $m[8] - $m[5] * $m[7]) -
    $m[1] * ($m[3] * $m[8] - $m[5] * $m[6]) +
    $m[2] * ($m[3] * $m[7] - $m[4] * $m[6]);
echo "Determinant (IAU 2006): " . sprintf('%.15f', $det) . " (should be ~1)\n";

// Orthogonality: first row · second row should be 0
$dot01 = $m[0] * $m[3] + $m[1] * $m[4] + $m[2] * $m[5];
echo "Row0 · Row1:            " . sprintf('%.2e', $dot01) . " (should be ~0)\n";

echo "\n✅ All tests complete!\n";
