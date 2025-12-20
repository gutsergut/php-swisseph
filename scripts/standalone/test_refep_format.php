<?php
/**
 * Test interleaved vs sequential refep format
 */

require_once __DIR__ . '/../vendor/autoload.php';
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwephReader;
use Swisseph\SwephFile\ChebyshevInterpolation;

\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2460000.0;

$swed = SwedState::getInstance();
$serr = '';

$fname = 'sepl_18.se1';
$ifno = SwephConstants::SEI_FILE_PLANET;

SwephReader::openAndReadHeader($ifno, $fname, __DIR__ . '/../../eph/ephe', $serr);

$ipli = SwephConstants::SEI_MERCURY;
$pdp = &$swed->pldat[$ipli];

SwephReader::getNewSegment($tjd, $ipli, $ifno, $serr);

$ncoe = $pdp->ncoe;

// Constants
$TWOPI = 6.283185307179586476925287;
$seps2000 = 0.39777715572793088;
$ceps2000 = 0.91748206215761929;

// Calculate orbital elements
$t = $pdp->tseg0 + $pdp->dseg / 2.0;
$tdiff = ($t - $pdp->telem) / 365250.0;

$qav = $pdp->qrot + $tdiff * $pdp->dqrot;
$pav = $pdp->prot + $tdiff * $pdp->dprot;

$omtild = $pdp->peri + $tdiff * $pdp->dperi;
$i = (int)($omtild / $TWOPI);
$omtild -= $i * $TWOPI;
$com = cos($omtild);
$som = sin($omtild);

// Function to compute with given refep interpretation
function computeWithRefep($pdp, $ncoe, $tjd, $qav, $pav, $com, $som, $interleaved) {
    $x = [];

    // Copy raw coefficients
    for ($i = 0; $i < $ncoe; $i++) {
        $x[$i] = [
            $pdp->segp[$i],
            $pdp->segp[$i + $ncoe],
            $pdp->segp[$i + 2 * $ncoe]
        ];
    }

    // Add reference ellipse
    if ($pdp->iflg & 4) { // SEI_FLG_ELLIPSE
        for ($i = 0; $i < $ncoe; $i++) {
            if ($interleaved) {
                // Interleaved: refep[2*i] = x, refep[2*i+1] = y
                $refepx_i = $pdp->refep[2 * $i];
                $refepy_i = $pdp->refep[2 * $i + 1];
            } else {
                // Sequential: refepx = refep[0..n-1], refepy = refep[n..2n-1]
                $refepx_i = $pdp->refep[$i];
                $refepy_i = $pdp->refep[$ncoe + $i];
            }
            $x[$i][0] = $pdp->segp[$i] + $com * $refepx_i - $som * $refepy_i;
            $x[$i][1] = $pdp->segp[$i + $ncoe] + $com * $refepy_i + $som * $refepx_i;
        }
    }

    // Construct rotation matrix
    $cosih2 = 1.0 / (1.0 + $qav * $qav + $pav * $pav);

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

    // Rotate coefficients
    $neval = 0;
    for ($i = 0; $i < $ncoe; $i++) {
        $xrot = $x[$i][0] * $uix[0] + $x[$i][1] * $uiy[0] + $x[$i][2] * $uiz[0];
        $yrot = $x[$i][0] * $uix[1] + $x[$i][1] * $uiy[1] + $x[$i][2] * $uiz[1];
        $zrot = $x[$i][0] * $uix[2] + $x[$i][1] * $uiy[2] + $x[$i][2] * $uiz[2];

        if (abs($xrot) + abs($yrot) + abs($zrot) >= 1e-14) {
            $neval = $i;
        }

        $x[$i][0] = $xrot;
        $x[$i][1] = $yrot;
        $x[$i][2] = $zrot;
    }

    // Chebyshev interpolation
    $t_norm = ($tjd - $pdp->tseg0) / $pdp->dseg;
    $t_norm = $t_norm * 2.0 - 1.0;

    $xp = [];
    for ($coord = 0; $coord < 3; $coord++) {
        $coeffs = [];
        for ($i = 0; $i < $ncoe; $i++) {
            $coeffs[$i] = $x[$i][$coord];
        }
        $xp[$coord] = ChebyshevInterpolation::evaluate($t_norm, $coeffs, $neval);
    }

    return $xp;
}

echo "=== Testing refep interpretation ===\n\n";

// Print raw coefficients BEFORE any processing
echo "Raw Chebyshev coefficients from file (first 5):\n";
for ($i = 0; $i < 5; $i++) {
    printf("  coef[%d]: X=%.15e, Y=%.15e, Z=%.15e\n",
        $i, $pdp->segp[$i], $pdp->segp[$i + $ncoe], $pdp->segp[$i + 2*$ncoe]);
}

echo "\nOrbital elements:\n";
printf("  qrot=%.15f, dqrot=%.15e\n", $pdp->qrot, $pdp->dqrot);
printf("  prot=%.15f, dprot=%.15e\n", $pdp->prot, $pdp->dprot);
printf("  peri=%.15f, dperi=%.15e\n", $pdp->peri, $pdp->dperi);
printf("  telem=%.6f\n", $pdp->telem);

echo "\nComputed orbital params:\n";
printf("  qav = %.15f\n", $qav);
printf("  pav = %.15f\n", $pav);
printf("  omtild = %.15f (com=%.10f, som=%.10f)\n", $omtild, $com, $som);

// Manual rotation matrix
$cosih2 = 1.0 / (1.0 + $qav * $qav + $pav * $pav);
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
$uiz = [
    2.0 * $pav * $cosih2,
    -2.0 * $qav * $cosih2,
    (1.0 - $qav * $qav - $pav * $pav) * $cosih2
];
echo "\nRotation matrix (should transform orbital->ecliptic J2000):\n";
printf("  uix = [%.10f, %.10f, %.10f]\n", $uix[0], $uix[1], $uix[2]);
printf("  uiy = [%.10f, %.10f, %.10f]\n", $uiy[0], $uiy[1], $uiy[2]);
printf("  uiz = [%.10f, %.10f, %.10f]\n", $uiz[0], $uiz[1], $uiz[2]);

// Compute with sequential
$xp_seq = computeWithRefep($pdp, $ncoe, $tjd, $qav, $pav, $com, $som, false);
echo "Sequential (current) interpretation:\n";
printf("  x = %.15f\n", $xp_seq[0]);
printf("  y = %.15f\n", $xp_seq[1]);
printf("  z = %.15f\n", $xp_seq[2]);
$dist_seq = sqrt($xp_seq[0]**2 + $xp_seq[1]**2 + $xp_seq[2]**2);
printf("  dist = %.9f AU\n", $dist_seq);

echo "\nInterleaved interpretation:\n";
$xp_int = computeWithRefep($pdp, $ncoe, $tjd, $qav, $pav, $com, $som, true);
printf("  x = %.15f\n", $xp_int[0]);
printf("  y = %.15f\n", $xp_int[1]);
printf("  z = %.15f\n", $xp_int[2]);
$dist_int = sqrt($xp_int[0]**2 + $xp_int[1]**2 + $xp_int[2]**2);
printf("  dist = %.9f AU\n", $dist_int);

echo "\n=== Reference from swetest64.exe ===\n";
echo "  x = 0.101780886\n";
echo "  y = -0.441188298\n";
echo "  z = -0.045389862\n";
echo "  dist = 0.456273 AU\n";
