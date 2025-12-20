<?php

declare(strict_types=1);

namespace Swisseph;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

echo "=== Debug planForOscElem - Detailed Step Tracing ===\n\n";

// Test data: Mars at J2000
$tjd = 2451545.0;
$ipl = Constants::SE_MARS;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

echo "Parameters:\n";
echo "  JD: $tjd\n";
echo "  Planet: Mars\n";
echo "  Flags: $iflag\n\n";

// Get Mars position first
echo "Step 0: Get Mars position from ephemeris\n";
$xx = [];
$serr = '';
$ret = swe_calc($tjd, $ipl, $iflag, $xx, $serr);
if ($ret < 0) {
    echo "ERROR getting position: $serr\n";
    exit(1);
}
echo "  Mars position (ecliptic): [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xx)) . "]\n";
echo "  Has NAN: " . (count(array_filter($xx, fn($v) => is_nan($v))) > 0 ? "YES ❌" : "NO ✓") . "\n\n";

// Now simulate what happens in calcOsculatingNodesApsides
// We need to get the position in EQUATORIAL coordinates for osculating calculation
echo "For osculating, we need equatorial coordinates. Let's trace the transformation:\n\n";

// Simulate getting position for osculating (this is what happens in the actual code)
// The issue might be in how we get the initial position

// Let's manually trace planForOscElem steps
$testVec = [1.0, 0.5, 0.25, 0.01, -0.005, 0.002]; // Test vector

echo "=== Tracing planForOscElem steps with test vector ===\n";
echo "Initial: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $testVec)) . "]\n\n";

// Step 1: Bias
echo "Step 1: ICRS → J2000 (Bias)\n";
if (!($iflag & Constants::SEFLG_ICRS)) {
    $testVec = Bias::apply(
        $testVec,
        $tjd,
        $iflag,
        Bias::MODEL_IAU_2006,
        false,
        JplHorizonsApprox::SEMOD_JPLHORA_DEFAULT
    );
    echo "  After Bias: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $testVec)) . "]\n";
    echo "  Has NAN: " . (count(array_filter($testVec, fn($v) => is_nan($v))) > 0 ? "YES ❌" : "NO ✓") . "\n\n";
} else {
    echo "  Skipped (ICRS flag set)\n\n";
}

// Step 2: Precession
echo "Step 2: J2000 → Date (Precession)\n";
$useJ2000 = ($iflag & Constants::SEFLG_J2000) !== 0;
if (!$useJ2000) {
    $before = $testVec;
    Precession::precess($testVec, $tjd, $iflag, -1, null);
    echo "  Before Precession: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $before)) . "]\n";
    echo "  After Precession:  [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $testVec)) . "]\n";
    echo "  Has NAN: " . (count(array_filter($testVec, fn($v) => is_nan($v))) > 0 ? "YES ❌" : "NO ✓") . "\n\n";
} else {
    echo "  Skipped (J2000 flag set)\n\n";
}

// Step 3: Obliquity
echo "Step 3: Calculate Obliquity\n";
$useEpoch = $useJ2000 ? Constants::J2000 : $tjd;
$eps = Obliquity::calc($useEpoch, $iflag, 0, null);
$seps = sin($eps);
$ceps = cos($eps);
echo "  Epoch: $useEpoch\n";
echo "  Obliquity: " . sprintf('%.6f° (%.10f rad)', Math::radToDeg($eps), $eps) . "\n";
echo "  sin(eps): " . sprintf('%.10f', $seps) . "\n";
echo "  cos(eps): " . sprintf('%.10f', $ceps) . "\n";
echo "  Has NAN: " . (is_nan($eps) || is_nan($seps) || is_nan($ceps) ? "YES ❌" : "NO ✓") . "\n\n";

// Step 4: Nutation
echo "Step 4: Nutation\n";
if (!($iflag & Constants::SEFLG_NONUT) && !$useJ2000) {
    $nutModel = Nutation::selectModelFromFlags($iflag);
    [$dpsi, $deps] = Nutation::calc($tjd, $nutModel, false);
    echo "  Model: " . Nutation::getModelName($nutModel) . "\n";
    echo "  dpsi: " . sprintf('%.6f\" (%.10e rad)', $dpsi * 3600, $dpsi) . "\n";
    echo "  deps: " . sprintf('%.6f\" (%.10e rad)', $deps * 3600, $deps) . "\n";
    echo "  Has NAN: " . (is_nan($dpsi) || is_nan($deps) ? "YES ❌" : "NO ✓") . "\n";

    $snut = sin($deps);
    $cnut = cos($deps);
    $nutMatrix = NutationMatrix::build($dpsi, $deps, $eps, $seps, $ceps);

    echo "  Nutation matrix det: ";
    $det =
        $nutMatrix[0] * ($nutMatrix[4] * $nutMatrix[8] - $nutMatrix[5] * $nutMatrix[7]) -
        $nutMatrix[1] * ($nutMatrix[3] * $nutMatrix[8] - $nutMatrix[5] * $nutMatrix[6]) +
        $nutMatrix[2] * ($nutMatrix[3] * $nutMatrix[7] - $nutMatrix[4] * $nutMatrix[6]);
    echo sprintf('%.10f', $det) . "\n";
    echo "  Matrix has NAN: " . (count(array_filter($nutMatrix, fn($v) => is_nan($v))) > 0 ? "YES ❌" : "NO ✓") . "\n";

    $xTemp = NutationMatrix::apply($nutMatrix, $testVec);
    echo "  After nutation matrix: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xTemp)) . "]\n";
    echo "  Has NAN: " . (count(array_filter($xTemp, fn($v) => is_nan($v))) > 0 ? "YES ❌" : "NO ✓") . "\n\n";

    $testVec = $xTemp;
} else {
    echo "  Skipped (NONUT flag or J2000)\n\n";
}

// Step 5: Equatorial to Ecliptic
echo "Step 5: Equatorial → Ecliptic\n";
$xOut = [];
Coordinates::coortrf2($testVec, $xOut, $seps, $ceps);
echo "  After coortrf2: [" . implode(', ', array_map(fn($v) => sprintf('%.6f', $v), $xOut)) . "]\n";
echo "  Has NAN: " . (count(array_filter($xOut, fn($v) => is_nan($v))) > 0 ? "YES ❌" : "NO ✓") . "\n\n";

echo "=== End of trace ===\n";
