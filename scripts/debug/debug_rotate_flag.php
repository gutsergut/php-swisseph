<?php
/**
 * Debug: Check SEI_FLG_ROTATE flag for Moon
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== Check SEI_FLG_ROTATE for Moon ===\n\n";

$jd = 2451545.0;

// Trigger calculation
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

$swed = SwedState::getInstance();
$moonPdp = $swed->pldat[SwephConstants::SEI_MOON];

echo "Moon plan_data->iflg:\n";
echo "  iflg = 0x" . dechex($moonPdp->iflg) . " = " . $moonPdp->iflg . "\n";
echo "\n";
echo "SEI_FLG_ROTATE = 0x" . dechex(SwephConstants::SEI_FLG_ROTATE) . " = " . SwephConstants::SEI_FLG_ROTATE . "\n";
echo "Has ROTATE? " . (($moonPdp->iflg & SwephConstants::SEI_FLG_ROTATE) ? "YES" : "NO") . "\n";

echo "\nAll relevant flags:\n";
$flags_to_check = [
    'SEI_FLG_HELIO' => SwephConstants::SEI_FLG_HELIO,
    'SEI_FLG_ROTATE' => SwephConstants::SEI_FLG_ROTATE,
    'SEI_FLG_ELLIPSE' => SwephConstants::SEI_FLG_ELLIPSE,
    'SEI_FLG_EMBHEL' => SwephConstants::SEI_FLG_EMBHEL,
];

foreach ($flags_to_check as $name => $value) {
    $has = ($moonPdp->iflg & $value) ? 'YES' : 'NO';
    echo "  $name (0x" . dechex($value) . "): $has\n";
}

// Also check rotation parameters
echo "\nRotation parameters (for rot_back):\n";
echo "  prot = " . sprintf("%.15e", $moonPdp->prot ?? 0) . "\n";
echo "  dprot = " . sprintf("%.15e", $moonPdp->dprot ?? 0) . "\n";
echo "  qrot = " . sprintf("%.15e", $moonPdp->qrot ?? 0) . "\n";
echo "  dqrot = " . sprintf("%.15e", $moonPdp->dqrot ?? 0) . "\n";
echo "  peri = " . sprintf("%.15e", $moonPdp->peri ?? 0) . "\n";
echo "  dperi = " . sprintf("%.15e", $moonPdp->dperi ?? 0) . "\n";
echo "  telem = " . sprintf("%.10f", $moonPdp->telem ?? 0) . "\n";
