<?php
/**
 * Debug: Check rmax and other scaling factors for Moon
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== Moon rmax and scaling factors ===\n\n";

$jd = 2451545.0;

// Trigger calculation
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

$swed = SwedState::getInstance();
$moonPdp = $swed->pldat[SwephConstants::SEI_MOON];

echo "Moon plan_data:\n";
echo "  rmax = " . sprintf("%.15e", $moonPdp->rmax ?? 0) . "\n";
echo "  tfstart = " . sprintf("%.10f", $moonPdp->tfstart ?? 0) . "\n";
echo "  ncoe = " . ($moonPdp->ncoe ?? 'null') . "\n";
echo "  neval = " . ($moonPdp->neval ?? 'null') . "\n";
echo "  dseg = " . sprintf("%.10f", $moonPdp->dseg ?? 0) . "\n";
echo "  iflg = 0x" . dechex($moonPdp->iflg ?? 0) . "\n";

// First few coefficients
$segp = $moonPdp->segp ?? [];
echo "\nFirst 5 X coefficients:\n";
for ($i = 0; $i < 5; $i++) {
    echo sprintf("  c[%d] = %.15e\n", $i, $segp[$i] ?? 0);
}

// Moon file info
$moonFdp = $swed->fidat[SwephConstants::SEI_FILE_MOON];
echo "\nMoon file info:\n";
echo "  fnam = {$moonFdp->fnam}\n";
echo "  fversion = " . ($moonFdp->fversion ?? 'null') . "\n";
echo "  iflg = 0x" . dechex($moonFdp->iflg ?? 0) . "\n";

// Test if lndx0 is set correctly
echo "\n  lndx0 = " . ($moonPdp->lndx0 ?? 'null') . "\n";

// Check what segment we're reading
echo "\n  Segment info:\n";
$iseg = (int)(($jd - $moonPdp->tfstart) / $moonPdp->dseg);
echo "    iseg = $iseg\n";
echo "    fpos for coefficients = " . ($moonPdp->lndx0 + $iseg * 3) . "\n";
