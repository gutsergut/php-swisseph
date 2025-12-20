<?php

declare(strict_types=1);

namespace Swisseph;

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Test coordinate transformations ===\n\n";

// Test vector in equatorial coordinates (J2000)
$xx = [1.0, 0.5, 0.25, 0.01, -0.005, 0.002];

echo "Initial vector: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xx)) . "]\n\n";

// Test 1: Bias
echo "1. Apply bias (ICRS → J2000):\n";
$xx1 = Bias::apply($xx, Bias::MODEL_IAU_2006, false, true);
echo "   Result: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xx1)) . "]\n";
$hasNan1 = count(array_filter($xx1, fn($v) => is_nan($v))) > 0;
echo "   Has NAN: " . ($hasNan1 ? "YES ❌" : "NO ✓") . "\n\n";

// Test 2: Precession
echo "2. Apply precession (J2000 → 2024):\n";
$xx2 = $xx1;
Precession::precess($xx2, 2460676.5, 0, -1, null);
echo "   Result: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xx2)) . "]\n";
$hasNan2 = count(array_filter($xx2, fn($v) => is_nan($v))) > 0;
echo "   Has NAN: " . ($hasNan2 ? "YES ❌" : "NO ✓") . "\n\n";

// Test 3: Nutation
echo "3. Calculate and apply nutation:\n";
$tjd = 2460676.5;
[$dpsi, $deps] = Nutation::calcIau2000A($tjd);
$eps = Obliquity::calc($tjd, 0, 0, null);
echo "   dpsi=" . sprintf('%.6f"', $dpsi * 3600) . ", deps=" . sprintf('%.6f"', $deps * 3600) . ", eps=" . sprintf('%.6f°', \Swisseph\Math::radToDeg($eps)) . "\n";

$nutMatrix = NutationMatrix::build($dpsi, $deps, $eps);
$xx3 = NutationMatrix::apply($nutMatrix, $xx2);
echo "   Result: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xx3)) . "]\n";
$hasNan3 = count(array_filter($xx3, fn($v) => is_nan($v))) > 0;
echo "   Has NAN: " . ($hasNan3 ? "YES ❌" : "NO ✓") . "\n\n";

// Test 4: Equatorial to ecliptic
echo "4. Transform equatorial → ecliptic:\n";
$seps = sin($eps);
$ceps = cos($eps);
$xx4 = [];
Coordinates::coortrf2($xx3, $xx4, $seps, $ceps);
echo "   Result: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xx4)) . "]\n";
$hasNan4 = count(array_filter($xx4, fn($v) => is_nan($v))) > 0;
echo "   Has NAN: " . ($hasNan4 ? "YES ❌" : "NO ✓") . "\n\n";

echo "Summary: " . (($hasNan1 || $hasNan2 || $hasNan3 || $hasNan4) ? "ERRORS FOUND" : "All OK ✅") . "\n";
