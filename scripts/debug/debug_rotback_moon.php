<?php
/**
 * Debug: Trace rotateBack parameters for Moon
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== Trace rotateBack for Moon ===\n\n";

$jd = 2451545.0;

// Trigger calculation
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

$swed = SwedState::getInstance();
$pdp = $swed->pldat[SwephConstants::SEI_MOON];

echo "Moon plan_data:\n";
$nco = $pdp->ncoe;

// Get middle time of segment
$t = $pdp->tseg0 + $pdp->dseg / 2.0;
$tdiff = ($t - $pdp->telem) / 365250.0;

echo "  tseg0 = " . sprintf("%.15f", $pdp->tseg0) . "\n";
echo "  dseg  = " . sprintf("%.15f", $pdp->dseg) . "\n";
echo "  t (mid) = " . sprintf("%.15f", $t) . "\n";
echo "  telem = " . sprintf("%.15f", $pdp->telem) . "\n";
echo "  tdiff = " . sprintf("%.15f", $tdiff) . "\n";

// For Moon: calculate qav and pav
$TWOPI = 6.283185307179586476925287;

$dn = $pdp->prot + $tdiff * $pdp->dprot;
echo "\n  prot = " . sprintf("%.15f", $pdp->prot) . "\n";
echo "  dprot = " . sprintf("%.15f", $pdp->dprot) . "\n";
echo "  dn (before mod) = " . sprintf("%.15f", $dn) . "\n";

$i = (int)($dn / $TWOPI);
$dn_mod = $dn - $i * $TWOPI;
echo "  dn (after mod) = " . sprintf("%.15f", $dn_mod) . "\n";
echo "  i (int div) = $i\n";

$qrot_val = $pdp->qrot + $tdiff * $pdp->dqrot;
echo "\n  qrot = " . sprintf("%.15f", $pdp->qrot) . "\n";
echo "  dqrot = " . sprintf("%.15f", $pdp->dqrot) . "\n";
echo "  qrot_val (qrot + tdiff*dqrot) = " . sprintf("%.15f", $qrot_val) . "\n";

$qav = $qrot_val * cos($dn_mod);
$pav = $qrot_val * sin($dn_mod);

echo "\n  cos(dn) = " . sprintf("%.15f", cos($dn_mod)) . "\n";
echo "  sin(dn) = " . sprintf("%.15f", sin($dn_mod)) . "\n";
echo "  qav = " . sprintf("%.15f", $qav) . "\n";
echo "  pav = " . sprintf("%.15f", $pav) . "\n";

// cosih2
$cosih2 = 1.0 / (1.0 + $qav * $qav + $pav * $pav);
echo "\n  cosih2 = " . sprintf("%.15f", $cosih2) . "\n";

// uiz, uix, uiy
$uiz = [
    2.0 * $pav * $cosih2,
    -2.0 * $qav * $cosih2,
    (1.0 - $qav * $qav - $pav * $pav) * $cosih2
];

$uix = [
    (1.0 + $qav * $qav - $pav * $pav) * $cosih2,
    2.0 * $qav * $pav * $cosih2,
    -2.0 * $pav * $cosih2
];

$uiy = [
    2.0 * $qav * $pav * $cosih2,
    (1.0 - $qav * $qav + $pav * $pav) * $cosih2,
    2.0 * $qav * $cosih2
];

echo "\n  uix = [" . sprintf("%.15f, %.15f, %.15f", $uix[0], $uix[1], $uix[2]) . "]\n";
echo "  uiy = [" . sprintf("%.15f, %.15f, %.15f", $uiy[0], $uiy[1], $uiy[2]) . "]\n";
echo "  uiz = [" . sprintf("%.15f, %.15f, %.15f", $uiz[0], $uiz[1], $uiz[2]) . "]\n";

// J2000 epsilon for Moon rotation
$seps2000 = 0.39777715572793088;
$ceps2000 = 0.91748206215761929;

echo "\n  seps2000 = " . sprintf("%.17f", $seps2000) . "\n";
echo "  ceps2000 = " . sprintf("%.17f", $ceps2000) . "\n";

// First coefficients BEFORE rotation (raw from file)
// These were captured in debug_moon_chebyshev.php
echo "\n=== First X,Y,Z coefficients (raw from file) ===\n";
echo "From debug_moon_chebyshev.php output:\n";
echo "  X[0] = 1.812267789721264e-4\n";
echo "  Y[0] = 1.063485212406740e-3\n";
echo "  Z[0] = 3.815692302899038e-4\n";

// These would be rotated by rotateBack
// The rotation for first coefficient:
$x0 = 1.812267789721264e-4;
$y0 = 1.063485212406740e-3;
$z0 = 3.815692302899038e-4;

// First rotation (orbital plane to ecliptic)
$xrot = $x0 * $uix[0] + $y0 * $uiy[0] + $z0 * $uiz[0];
$yrot = $x0 * $uix[1] + $y0 * $uiy[1] + $z0 * $uiz[1];
$zrot = $x0 * $uix[2] + $y0 * $uiy[2] + $z0 * $uiz[2];

echo "\nAfter orbital->ecliptic rotation:\n";
echo "  xrot = " . sprintf("%.15f", $xrot) . "\n";
echo "  yrot = " . sprintf("%.15f", $yrot) . "\n";
echo "  zrot = " . sprintf("%.15f", $zrot) . "\n";

// Second rotation (ecliptic to equator J2000) - Moon only
$yrot2 = $ceps2000 * $yrot - $seps2000 * $zrot;
$zrot2 = $seps2000 * $yrot + $ceps2000 * $zrot;

echo "\nAfter ecliptic->equator rotation (Moon only):\n";
echo "  xrot2 = " . sprintf("%.15f", $xrot) . " (unchanged)\n";
echo "  yrot2 = " . sprintf("%.15f", $yrot2) . "\n";
echo "  zrot2 = " . sprintf("%.15f", $zrot2) . "\n";
