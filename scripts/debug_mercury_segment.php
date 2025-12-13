<?php
/**
 * Debug: check raw segment data and rotation
 */

require_once __DIR__ . '/../vendor/autoload.php';
use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwephReader;

\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2460000.0;  // 25 Feb 2023 00:00 UT

echo "=== Opening planet file and reading Mercury segment ===\n\n";

$swed = SwedState::getInstance();
$serr = '';

// Open file manually
$fname = 'sepl_18.se1';  // File for epochs around 2000
$ifno = SwephConstants::SEI_FILE_PLANET;

if (!SwephReader::openAndReadHeader($ifno, $fname, __DIR__ . '/../../eph/ephe', $serr)) {
    die("Failed to open file: $serr\n");
}

echo "File opened successfully\n";

$fdp = $swed->fidat[$ifno];
printf("File range: %.1f to %.1f\n", $fdp->tfstart, $fdp->tfend);

// Get Mercury planet data pointer
$ipli = SwephConstants::SEI_MERCURY;
$pdp = &$swed->pldat[$ipli];

// Read segment for Mercury
if (!SwephReader::getNewSegment($tjd, $ipli, $ifno, $serr)) {
    die("Failed to read segment: $serr\n");
}

echo "\n=== Segment data BEFORE rotation ===\n";
printf("iflg = 0x%X (HELIO=%d, ROTATE=%d, ELLIPSE=%d, EMBHEL=%d)\n",
    $pdp->iflg,
    ($pdp->iflg & SwephConstants::SEI_FLG_HELIO) ? 1 : 0,
    ($pdp->iflg & SwephConstants::SEI_FLG_ROTATE) ? 1 : 0,
    ($pdp->iflg & SwephConstants::SEI_FLG_ELLIPSE) ? 1 : 0,
    ($pdp->iflg & SwephConstants::SEI_FLG_EMBHEL) ? 1 : 0);
printf("ncoe = %d\n", $pdp->ncoe);
printf("tseg = [%.6f, %.6f], dseg = %.6f\n", $pdp->tseg0, $pdp->tseg1, $pdp->dseg);

echo "\nFirst 5 Chebyshev coefficients (BEFORE rotation):\n";
$ncoe = $pdp->ncoe;
for ($i = 0; $i < 5; $i++) {
    printf("  coef[%d]: X=%.15f, Y=%.15f, Z=%.15f\n",
        $i, $pdp->segp[$i], $pdp->segp[$i + $ncoe], $pdp->segp[$i + 2*$ncoe]);
}

echo "\n=== Reference ellipse data ===\n";
if ($pdp->refep !== null) {
    echo "refep is set (count=" . count($pdp->refep) . "), ncoe=$ncoe\n";

    // Test hypothesis: data is interleaved [x0, y0, x1, y1, ...]
    // C code expects: refepx = refep[0..n-1], refepy = refep[n..2n-1]
    // If file stores interleaved, we need to de-interleave

    echo "\n=== Testing reference ellipse addition ===\n";

    // Calculate omtild, com, som as in rotateBack
    $t = $pdp->tseg0 + $pdp->dseg / 2.0;
    $tdiff = ($t - $pdp->telem) / 365250.0;
    $omtild = $pdp->peri + $tdiff * $pdp->dperi;
    $TWOPI = 6.283185307179586;
    $i = (int)($omtild / $TWOPI);
    $omtild -= $i * $TWOPI;
    $com = cos($omtild);
    $som = sin($omtild);

    printf("omtild = %.15f (peri=%.10f, tdiff=%.10f)\n", $omtild, $pdp->peri, $tdiff);
    printf("com = %.15f, som = %.15f\n", $com, $som);

    // Current interpretation: sequential [x0, x1, ..., y0, y1, ...]
    echo "\nCurrent (sequential) first element after adding refep:\n";
    $refepx_0 = $pdp->refep[0];
    $refepy_0 = $pdp->refep[$ncoe];
    $x0_seq = $pdp->segp[0] + $com * $refepx_0 - $som * $refepy_0;
    $y0_seq = $pdp->segp[$ncoe] + $com * $refepy_0 + $som * $refepx_0;
    printf("  refepx[0]=%.15f, refepy[0]=%.15f\n", $refepx_0, $refepy_0);
    printf("  x[0][0] = %.15f, x[0][1] = %.15f\n", $x0_seq, $y0_seq);

    // Alternative interpretation: interleaved [x0, y0, x1, y1, ...]
    echo "\nAlternative (interleaved) first element after adding refep:\n";
    $refepx_0_int = $pdp->refep[0];
    $refepy_0_int = $pdp->refep[1];
    $x0_int = $pdp->segp[0] + $com * $refepx_0_int - $som * $refepy_0_int;
    $y0_int = $pdp->segp[$ncoe] + $com * $refepy_0_int + $som * $refepx_0_int;
    printf("  refepx[0]=%.15f, refepy[0]=%.15f\n", $refepx_0_int, $refepy_0_int);
    printf("  x[0][0] = %.15f, x[0][1] = %.15f\n", $x0_int, $y0_int);

} else {
    echo "refep is NULL\n";
}

echo "\n=== Orbital elements ===\n";
printf("telem = %.6f\n", $pdp->telem);
printf("prot = %.15f, dprot = %.15f\n", $pdp->prot, $pdp->dprot);
printf("qrot = %.15f, dqrot = %.15f\n", $pdp->qrot, $pdp->dqrot);
printf("peri = %.15f, dperi = %.15f\n", $pdp->peri, $pdp->dperi);

// Manually apply rotation
echo "\n=== Manual rotation check ===\n";

$t = $pdp->tseg0 + $pdp->dseg / 2.0;
$tdiff = ($t - $pdp->telem) / 365250.0;
$qav = $pdp->qrot + $tdiff * $pdp->dqrot;
$pav = $pdp->prot + $tdiff * $pdp->dprot;

printf("t = %.6f, tdiff = %.15f\n", $t, $tdiff);
printf("qav = %.15f, pav = %.15f\n", $qav, $pav);

$cosih2 = 1.0 / (1.0 + $qav * $qav + $pav * $pav);
printf("cosih2 = %.15f\n", $cosih2);

// Now apply rotateBack and Chebyshev interpolation
echo "\n=== Chebyshev interpolation after rotation ===\n";

// Apply rotateBack
\Swisseph\SwephFile\SeriesRotation::rotateBack($ipli);

echo "Coefficients AFTER rotateBack (first 5):\n";
for ($i = 0; $i < 5; $i++) {
    printf("  coef[%d]: X=%.15f, Y=%.15f, Z=%.15f\n",
        $i, $pdp->segp[$i], $pdp->segp[$i + $ncoe], $pdp->segp[$i + 2*$ncoe]);
}

// Chebyshev interpolation
$t_norm = ($tjd - $pdp->tseg0) / $pdp->dseg;
$t_norm = $t_norm * 2.0 - 1.0;
printf("\nt_norm = %.15f for JD=%.6f\n", $t_norm, $tjd);

$xp = [];
for ($i = 0; $i <= 2; $i++) {
    $coeffOffset = $i * $ncoe;
    $coeffArray = array_slice($pdp->segp, $coeffOffset, $ncoe);
    $xp[$i] = \Swisseph\SwephFile\ChebyshevInterpolation::evaluate($t_norm, $coeffArray, $pdp->neval);
}

printf("\nPHP result (JD=%.6f):\n", $tjd);
printf("  x = %.15f\n", $xp[0]);
printf("  y = %.15f\n", $xp[1]);
printf("  z = %.15f\n", $xp[2]);
$dist = sqrt($xp[0]**2 + $xp[1]**2 + $xp[2]**2);
printf("  dist = %.9f AU\n", $dist);

echo "\n=== Reference from swetest64.exe (hel, j2000, true) ===\n";
echo "  x = 0.101780886\n";
echo "  y = -0.441188298\n";
echo "  z = -0.045389862\n";
echo "  dist = 0.456273 AU\n";
