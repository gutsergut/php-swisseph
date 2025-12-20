<?php
/**
 * Debug: Check segment info and t normalization for Moon
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== Segment and t Normalization Check ===\n\n";

$jd = 2451545.0;

// Trigger calculation
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

$swed = SwedState::getInstance();
$moonPdp = $swed->pldat[SwephConstants::SEI_MOON];

echo "Moon plan_data for JD $jd:\n";
echo "  tseg0 = " . sprintf("%.10f", $moonPdp->tseg0) . "\n";
echo "  tseg1 = " . sprintf("%.10f", $moonPdp->tseg1) . "\n";
echo "  dseg  = " . sprintf("%.10f", $moonPdp->dseg) . "\n";
echo "  ncoe  = " . $moonPdp->ncoe . "\n";
echo "  neval = " . $moonPdp->neval . "\n";

// Calculate t
$t = ($jd - $moonPdp->tseg0) / $moonPdp->dseg * 2.0 - 1.0;
echo "\nNormalized t:\n";
echo "  t = ($jd - {$moonPdp->tseg0}) / {$moonPdp->dseg} * 2 - 1\n";
echo "  t = " . sprintf("%.15f", $t) . "\n";

// Check if JD is within segment
if ($jd < $moonPdp->tseg0 || $jd > $moonPdp->tseg1) {
    echo "\n*** WARNING: JD outside segment bounds! ***\n";
} else {
    echo "\n  JD is within segment [tseg0, tseg1] ✓\n";
}

// Check t is in [-1, 1]
if ($t < -1 || $t > 1) {
    echo "*** WARNING: t outside [-1, 1]! ***\n";
} else {
    echo "  t is in [-1, 1] ✓\n";
}

// File info
$moonFdp = $swed->fidat[SwephConstants::SEI_FILE_MOON];
echo "\nMoon file: {$moonFdp->fnam}\n";
echo "  tfstart = " . sprintf("%.10f", $moonFdp->tfstart) . "\n";
echo "  tfend = " . sprintf("%.10f", $moonFdp->tfend) . "\n";
echo "  swession = " . ($moonFdp->swession ?? 'null') . "\n";

// Moon segment structure
// In Swiss Ephemeris: Moon typically has 64-day segments
$expectedDseg = 27.554551; // ~1 sidereal month
echo "\nExpected dseg (sidereal month) ≈ $expectedDseg days\n";
echo "Actual dseg = {$moonPdp->dseg} days\n";
echo "Ratio = " . ($moonPdp->dseg / $expectedDseg) . " (should be close to 1.0)\n";
