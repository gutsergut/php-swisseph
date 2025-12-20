<?php
/**
 * Debug: Check reference ellipse for Moon
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== Check Reference Ellipse for Moon ===\n\n";

$jd = 2451545.0;

// Trigger calculation
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

$swed = SwedState::getInstance();
$pdp = $swed->pldat[SwephConstants::SEI_MOON];

echo "Moon plan_data:\n";
echo "  iflg = 0x" . dechex($pdp->iflg) . "\n";
echo "  SEI_FLG_ELLIPSE = " . (($pdp->iflg & SwephConstants::SEI_FLG_ELLIPSE) ? 'YES' : 'NO') . "\n";

if ($pdp->iflg & SwephConstants::SEI_FLG_ELLIPSE) {
    echo "\nReference ellipse (refep):\n";
    $refep = $pdp->refep ?? null;
    if ($refep === null || empty($refep)) {
        echo "  WARNING: refep is NULL or empty!\n";
    } else {
        $nco = $pdp->ncoe;
        echo "  Number of coefficients (ncoe) = $nco\n";
        echo "  refep array size = " . count($refep) . " (should be " . (2 * $nco) . ")\n";

        echo "\n  First 5 refepx (X reference ellipse):\n";
        for ($i = 0; $i < min(5, $nco); $i++) {
            echo sprintf("    refepx[%d] = %.15e\n", $i, $refep[$i] ?? 0);
        }

        echo "\n  First 5 refepy (Y reference ellipse):\n";
        for ($i = 0; $i < min(5, $nco); $i++) {
            echo sprintf("    refepy[%d] = %.15e\n", $i, $refep[$nco + $i] ?? 0);
        }
    }

    // Check ellipse parameters
    echo "\n  Ellipse parameters:\n";
    echo sprintf("    peri = %.15f\n", $pdp->peri ?? 0);
    echo sprintf("    dperi = %.15f\n", $pdp->dperi ?? 0);
}

// Check raw segp
echo "\n=== Raw segp coefficients (before rotateBack) ===\n";
echo "We need to capture these BEFORE rotateBack is called.\n";
echo "Current segp (after rotateBack):\n";
$segp = $pdp->segp ?? [];
$nco = $pdp->ncoe;
echo "  X[0] = " . sprintf("%.15e", $segp[0] ?? 0) . "\n";
echo "  Y[0] = " . sprintf("%.15e", $segp[$nco] ?? 0) . "\n";
echo "  Z[0] = " . sprintf("%.15e", $segp[2*$nco] ?? 0) . "\n";
