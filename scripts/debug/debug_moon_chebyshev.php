<?php
/**
 * Debug: Dump raw Chebyshev coefficients for Moon from Swiss Ephemeris files
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\ChebyshevInterpolation;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== Raw Chebyshev Coefficients Dump for Moon ===\n\n";

$jd = 2451545.0;

// Trigger Moon calculation to load coefficients
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_XYZ;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

// Get state
$swed = SwedState::getInstance();
$moonPdp = $swed->pldat[SwephConstants::SEI_MOON];

echo "Moon plan_data:\n";
echo "  teval = " . ($moonPdp->teval ?? 'null') . "\n";
echo "  tseg0 = " . ($moonPdp->tseg0 ?? 'null') . "\n";
echo "  tseg1 = " . ($moonPdp->tseg1 ?? 'null') . "\n";
echo "  dseg  = " . ($moonPdp->dseg ?? 'null') . "\n";
echo "  neval = " . ($moonPdp->neval ?? 'null') . "\n";
echo "  ncoe  = " . ($moonPdp->ncoe ?? 'null') . "\n";

echo "\n  x[] = [" . implode(", ", array_map(fn($v) => sprintf("%.15f", $v), $moonPdp->x ?? [])) . "]\n";

// Calculate normalized t
$t = null;
if (isset($moonPdp->dseg) && isset($moonPdp->tseg0) && $moonPdp->dseg != 0) {
    $t = ($jd - $moonPdp->tseg0) / $moonPdp->dseg * 2.0 - 1.0;
    echo "\n  Normalized t = $t\n";
}

// Dump first few coefficients
echo "\nChebyshev coefficients:\n";
$segp = $moonPdp->segp ?? [];
$ncoe = $moonPdp->ncoe ?? 13;
$ncomp = 3; // 3 components (X, Y, Z)

for ($comp = 0; $comp < 3; $comp++) {
    $compName = ['X', 'Y', 'Z'][$comp];
    echo "\n$compName coefficients (ncoe=$ncoe):\n";

    $coeffArray = [];
    $offset = $comp * $ncoe;
    for ($j = 0; $j < min(10, $ncoe); $j++) {
        $coef = $segp[$offset + $j] ?? 0.0;
        $coeffArray[] = $coef;
        echo sprintf("  c[%d] = %.15e\n", $j, $coef);
    }

    // Manual interpolation
    if ($t !== null) {
        // Get all coefficients for this component
        $fullCoeffs = [];
        for ($j = 0; $j < $ncoe; $j++) {
            $fullCoeffs[] = $segp[$offset + $j] ?? 0.0;
        }
        $result = ChebyshevInterpolation::evaluate($t, $fullCoeffs, $ncoe);
        echo sprintf("  Interpolated result: %.15f\n", $result);
    }
}

echo "\n=== File Info ===\n";
$moonFdp = $swed->fidat[SwephConstants::SEI_FILE_MOON];
echo "Moon file: " . ($moonFdp->fnam ?? 'null') . "\n";
echo "tfstart = " . ($moonFdp->tfstart ?? 'null') . "\n";
echo "tfend = " . ($moonFdp->tfend ?? 'null') . "\n";
echo "iflg = 0x" . dechex($moonFdp->iflg ?? 0) . "\n";

echo "\n=== Computed Result ===\n";
echo "PHP Moon XYZ: X={$xx[0]}, Y={$xx[1]}, Z={$xx[2]}\n";
echo "swetest XYZ:  X=-0.001949007, Y=-0.001838438, Z=0.000242453\n";
echo "Diff X: " . (($xx[0] - (-0.001949007)) * 149597870.7) . " km\n";
echo "Diff Y: " . (($xx[1] - (-0.001838438)) * 149597870.7) . " km\n";
echo "Diff Z: " . (($xx[2] - (0.000242453)) * 149597870.7) . " km\n";
