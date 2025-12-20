<?php
/**
 * Detailed debugging of osculating nodes/apsides NAN issue
 * Traces coordinate transformations step by step
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\NodesApsides;
use Swisseph\Julian;

// Mars J2000.0 (same as debug_oscu.php)
$jd = 2451545.0;
$ipl = Constants::SE_MARS;
$method = Constants::SE_NODBIT_OSCU;
$iflag = Constants::SEFLG_SWIEPH;

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';

echo "=== Debugging Osculating Nodes/Apsides for Mars ===\n";
echo "JD: $jd (J2000.0)\n";
echo "Planet: Mars (ipl = $ipl)\n";
echo "Method: SE_NODBIT_OSCU\n\n";

// Step 1: Call the function
echo "Step 1: Calling NodesApsides::calcOsculatingNodesApsides...\n";
$doFocalPoint = false;
$withSpeed = false;
$useBary = false;
$result = NodesApsides::calcOsculatingNodesApsides(
    $jd, $ipl, $iflag,
    $xnasc, $xndsc, $xperi, $xaphe,
    $doFocalPoint, $withSpeed, $useBary, $serr
);

echo "Return code: $result\n";
echo "Error message: " . ($serr ?: 'none') . "\n\n";

// Step 2: Check outputs
echo "Step 2: Checking output arrays:\n";
echo "xnasc (ascending node): " . print_r($xnasc, true) . "\n";
echo "xndsc (descending node): " . print_r($xndsc, true) . "\n";
echo "xperi (perihelion): " . print_r($xperi, true) . "\n";
echo "xaphe (aphelion): " . print_r($xaphe, true) . "\n";

// Step 3: Check for NAN
$hasNAN = false;
foreach ([$xnasc, $xndsc, $xperi, $xaphe] as $arr) {
    foreach ($arr as $val) {
        if (is_nan($val)) {
            $hasNAN = true;
            break 2;
        }
    }
}

if ($hasNAN) {
    echo "\n!!! NAN DETECTED !!!\n\n";

    // Step 4: Try to get intermediate coordinates
    echo "Step 4: Testing PlanetsFunctions::calc directly...\n";

    $xx = [];
    $calcSerr = '';
    $calcIflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_XYZ | Constants::SEFLG_SPEED;

    $calcResult = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
        $jd, $ipl, $calcIflag, $xx, $calcSerr
    );

    echo "PlanetsFunctions::calc result: $calcResult\n";
    echo "Error: " . ($calcSerr ?: 'none') . "\n";
    echo "Raw XYZ coordinates: " . print_r($xx, true) . "\n";

    // Check if coordinates are valid
    if (count($xx) >= 6) {
        $pos = [$xx[0], $xx[1], $xx[2]];
        $vel = [$xx[3], $xx[4], $xx[5]];

        echo "\nPosition vector (AU): [" . implode(', ', array_map(fn($v) => sprintf('%.9f', $v), $pos)) . "]\n";
        echo "Velocity vector (AU/day): [" . implode(', ', array_map(fn($v) => sprintf('%.9f', $v), $vel)) . "]\n";

        $r = sqrt($pos[0]**2 + $pos[1]**2 + $pos[2]**2);
        $v = sqrt($vel[0]**2 + $vel[1]**2 + $vel[2]**2);

        echo "Position magnitude |r| = " . sprintf('%.9f', $r) . " AU\n";
        echo "Velocity magnitude |v| = " . sprintf('%.9f', $v) . " AU/day\n";

        // Check obliquity transformation
        echo "\nStep 5: Testing obliquity transformation...\n";
        $eps = \Swisseph\Obliquity::meanObliquityRadFromJdTT(2451545.0);
        echo "Mean obliquity at J2000.0: " . sprintf('%.9f', $eps) . " rad (" . sprintf('%.6f', rad2deg($eps)) . "°)\n";

        $seps = sin($eps);
        $ceps = cos($eps);
        echo "sin(eps) = " . sprintf('%.9f', $seps) . "\n";
        echo "cos(eps) = " . sprintf('%.9f', $ceps) . "\n";

        // Transform position
        $xOut = [];
        \Swisseph\Coordinates::coortrf2($pos, $xOut, $seps, $ceps);
        echo "Transformed position: [" . implode(', ', array_map(fn($v) => sprintf('%.9f', $v), $xOut)) . "]\n";

        // Transform velocity
        $velOut = [];
        \Swisseph\Coordinates::coortrf2($vel, $velOut, $seps, $ceps);
        echo "Transformed velocity: [" . implode(', ', array_map(fn($v) => sprintf('%.9f', $v), $velOut)) . "]\n";

        // Check for NAN in transformed coordinates
        $transformHasNAN = false;
        foreach ($xOut as $v) if (is_nan($v)) $transformHasNAN = true;
        foreach ($velOut as $v) if (is_nan($v)) $transformHasNAN = true;

        if ($transformHasNAN) {
            echo "\n!!! NAN in transformed coordinates - transformation is broken !!!\n";
        } else {
            echo "\nTransformation OK - NAN must occur in orbital element extraction\n";
        }
    }
} else {
    echo "\n✓ No NAN detected - osculating calculation successful!\n";
}
