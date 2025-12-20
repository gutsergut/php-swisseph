<?php
/**
 * Debug: Compare precession step by step with C
 */
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Precession;
use Swisseph\Bias;
use Swisseph\Nutation;
use Swisseph\NutationMatrix;
use Swisseph\Obliquity;
use Swisseph\Coordinates;

$jd = 2451545.0;

// C INPUT to swi_plan_for_osc_elem for Jupiter (i=1):
$xx = [4.0011770235, 2.7365791752, 1.0755116721, -0.0045683156, 0.0058814620, 0.0026323038];

echo "=== Debug precession chain for Jupiter ===\n\n";
echo "INPUT (J2000 equatorial XYZ):\n";
printf("  x = %.10f\n", $xx[0]);
printf("  y = %.10f\n", $xx[1]);
printf("  z = %.10f\n", $xx[2]);

// C: iflg0 = SEFLG_SWIEPH | SEFLG_SPEED | SEFLG_TRUEPOS | SEFLG_HELCTR
// = 2 | 256 | 32768 | 8 = 33034 = 0x810A
$iflg0 = 0x810A; // без SEFLG_NONUT, без SEFLG_J2000

echo "\niflg0 = 0x" . dechex($iflg0) . "\n";
echo "SEFLG_ICRS = " . (($iflg0 & Constants::SEFLG_ICRS) ? "yes" : "no") . "\n";
echo "SEFLG_J2000 = " . (($iflg0 & Constants::SEFLG_J2000) ? "yes" : "no") . "\n";
echo "SEFLG_NONUT = " . (($iflg0 & Constants::SEFLG_NONUT) ? "yes" : "no") . "\n";

// Step 1: Bias (ICRS to J2000)
echo "\n=== Step 1: Bias (ICRS to J2000) ===\n";
if (!($iflg0 & Constants::SEFLG_ICRS)) {
    echo "Applying bias...\n";
    $xxBias = Bias::apply(
        $xx,
        $jd,
        $iflg0,
        Bias::MODEL_IAU_2006,
        false,
        \Swisseph\JplHorizonsApprox::SEMOD_JPLHORA_DEFAULT
    );
    printf("After bias: [%.10f, %.10f, %.10f]\n", $xxBias[0], $xxBias[1], $xxBias[2]);
    printf("  delta: [%.10e, %.10e, %.10e]\n",
        $xxBias[0] - $xx[0], $xxBias[1] - $xx[1], $xxBias[2] - $xx[2]);
} else {
    echo "Bias skipped (SEFLG_ICRS set)\n";
    $xxBias = $xx;
}

// Step 2: Precession (J2000 to date)
echo "\n=== Step 2: Precession (J2000 to date) ===\n";
$useJ2000 = ($iflg0 & Constants::SEFLG_J2000) !== 0;
echo "useJ2000 = " . ($useJ2000 ? "true" : "false") . "\n";

$xxPrec = $xxBias;
if (!$useJ2000) {
    echo "Applying precession (direction=-1 = J2000_TO_J)...\n";
    Precession::precess($xxPrec, $jd, $iflg0, -1, null);
    printf("After precession: [%.10f, %.10f, %.10f]\n", $xxPrec[0], $xxPrec[1], $xxPrec[2]);
    printf("  delta from bias: [%.10e, %.10e, %.10e]\n",
        $xxPrec[0] - $xxBias[0], $xxPrec[1] - $xxBias[1], $xxPrec[2] - $xxBias[2]);

    // Also precess velocity
    $vel = [$xxPrec[3], $xxPrec[4], $xxPrec[5]];
    Precession::precess($vel, $jd, $iflg0, -1, null);
    $xxPrec[3] = $vel[0];
    $xxPrec[4] = $vel[1];
    $xxPrec[5] = $vel[2];
} else {
    echo "Precession skipped (SEFLG_J2000 set)\n";
}

// Calculate obliquity for date
$useEpoch = $useJ2000 ? 2451545.0 : $jd;
$eps = Obliquity::calc($useEpoch, $iflg0, 0, null);
echo "\nObliquity for tjd=$jd: eps = " . rad2deg($eps) . "°\n";
$seps = sin($eps);
$ceps = cos($eps);
printf("  seps = %.10f, ceps = %.10f\n", $seps, $ceps);

// Step 3: Nutation
echo "\n=== Step 3: Nutation ===\n";
$xxNut = $xxPrec;
if (!($iflg0 & Constants::SEFLG_NONUT) && !$useJ2000) {
    echo "Applying nutation...\n";
    $nutModel = Nutation::selectModelFromFlags($iflg0);
    [$dpsi, $deps] = Nutation::calc($jd, $nutModel, false);
    printf("  dpsi = %.10f rad, deps = %.10f rad\n", $dpsi, $deps);

    $nutMatrix = NutationMatrix::build($dpsi, $deps, $eps, $seps, $ceps);

    $xTemp = NutationMatrix::apply($nutMatrix, $xxNut);
    $xxNut[0] = $xTemp[0];
    $xxNut[1] = $xTemp[1];
    $xxNut[2] = $xTemp[2];

    printf("After nutation: [%.10f, %.10f, %.10f]\n", $xxNut[0], $xxNut[1], $xxNut[2]);
    printf("  delta from precession: [%.10e, %.10e, %.10e]\n",
        $xxNut[0] - $xxPrec[0], $xxNut[1] - $xxPrec[1], $xxNut[2] - $xxPrec[2]);

    // Also nutation for velocity
    $velTemp = NutationMatrix::apply($nutMatrix, [$xxPrec[3], $xxPrec[4], $xxPrec[5]]);
    $xxNut[3] = $velTemp[0];
    $xxNut[4] = $velTemp[1];
    $xxNut[5] = $velTemp[2];
} else {
    echo "Nutation skipped\n";
}

echo "\n=== COMPARE WITH C ===\n";
echo "C AFTER nutation: [4.0013753845, 2.7363616218, 1.0753272151]\n";
printf("PHP AFTER nutation: [%.10f, %.10f, %.10f]\n", $xxNut[0], $xxNut[1], $xxNut[2]);
printf("  delta: [%.10e, %.10e, %.10e]\n",
    $xxNut[0] - 4.0013753845, $xxNut[1] - 2.7363616218, $xxNut[2] - 1.0753272151);

// Step 4: Ecliptic transform
echo "\n=== Step 4: Transform to ecliptic ===\n";
$xxEcl = [];
Coordinates::coortrf2([$xxNut[0], $xxNut[1], $xxNut[2]], $xxEcl, $seps, $ceps);
printf("After ecliptic: [%.10f, %.10f, %.10f]\n", $xxEcl[0], $xxEcl[1], $xxEcl[2]);

echo "\nC AFTER ecliptic: [4.0013753845, 2.9383033254, -0.1018681144]\n";
printf("PHP AFTER ecliptic: [%.10f, %.10f, %.10f]\n", $xxEcl[0], $xxEcl[1], $xxEcl[2]);
