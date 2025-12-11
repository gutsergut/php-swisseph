<?php

declare(strict_types=1);

/**
 * DEBUG version of eclipse_when_loc to compare with C
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Eclipses\EclipseUtils;
use Swisseph\VectorMath;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjdStart = 2460400.5; // March 15, 2024
$geopos = [-96.8, 32.8, 0.0];
$ifl = Constants::SEFLG_SWIEPH;
$backward = 0;

echo "=== DEBUG: eclipse_when_loc step-by-step ===\n";
echo "Start: JD $tjdStart\n";
echo "Location: lon={$geopos[0]}, lat={$geopos[1]}, alt={$geopos[2]}\n\n";

// Calculate K
$K = (int)(($tjdStart - Constants::J2000) / 365.2425 * 12.3685);
if ($backward) {
    $K++;
} else {
    $K--;
}

echo "Initial K = $K\n";

// Calculate T
$T = $K / 1236.85;
$T2 = $T * $T;
$T3 = $T2 * $T;
$T4 = $T3 * $T;

echo "T = $T\n";

// Calculate F
$F = EclipseUtils::degNorm(160.7108 + 390.67050274 * $K
           - 0.0016341 * $T2
           - 0.00000227 * $T3
           + 0.000000011 * $T4);
$Ff = $F;
if ($Ff > 180) {
    $Ff -= 180;
}

echo "F = {$F}°, Ff = {$Ff}°\n";

$max_tries = 10;
$try_count = 0;

while ($Ff > 21 && $Ff < 159 && $try_count < $max_tries) {
    echo "No eclipse possible (Ff not near node), trying next K...\n";

    if ($backward) {
        $K--;
    } else {
        $K++;
    }

    $try_count++;
    echo "Try $try_count: K = $K\n";

    $T = $K / 1236.85;
    $T2 = $T * $T;
    $T3 = $T2 * $T;
    $T4 = $T3 * $T;

    $F = EclipseUtils::degNorm(160.7108 + 390.67050274 * $K
               - 0.0016341 * $T2
               - 0.00000227 * $T3
               + 0.000000011 * $T4);
    $Ff = $F;
    if ($Ff > 180) {
        $Ff -= 180;
    }

    echo "F = {$F}°, Ff = {$Ff}°\n\n";
}

if ($Ff > 21 && $Ff < 159) {
    echo "Still no eclipse possible after $max_tries tries\n";
    exit;
}

echo "Eclipse possible! (Ff near node)\n";

// Approximate tjd
$tjd = 2451550.09765 + 29.530588853 * $K
                    + 0.0001337 * $T2
                    - 0.000000150 * $T3
                    + 0.00000000073 * $T4;

echo "Initial tjd (Meeus formula) = $tjd\n";

// Mean anomalies
$M = EclipseUtils::degNorm(2.5534 + 29.10535669 * $K
                    - 0.0000218 * $T2
                    - 0.00000011 * $T3);

$Mm = EclipseUtils::degNorm(201.5643 + 385.81693528 * $K
                    + 0.1017438 * $T2
                    + 0.00001239 * $T3
                    + 0.000000058 * $T4);

$E = 1 - 0.002516 * $T - 0.0000074 * $T2;

echo "M = {$M}°, Mm = {$Mm}°, E = $E\n";

$M *= Constants::DEGTORAD;
$Mm *= Constants::DEGTORAD;

$tjd = $tjd - 0.4075 * sin($Mm) + 0.1721 * $E * sin($M);

echo "After corrections: tjd = $tjd\n\n";

// Set topocentric
swe_set_topo($geopos[0], $geopos[1], $geopos[2]);

$iflag = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR | $ifl;
$iflagcart = $iflag | Constants::SEFLG_XYZ;

$dtdiv = 2.0;
$dtstart = 0.5;

if ($tjd < 1900000 || $tjd > 2500000) {
    $dtstart = 2.0;
}

echo "Starting iterative refinement:\n";
echo "dtstart = $dtstart, dtdiv = $dtdiv\n\n";

$iteration = 0;

for ($dt = $dtstart; $dt > 0.00001; $dt /= $dtdiv) {
    $iteration++;

    if ($dt < 0.1) {
        $dtdiv = 3.0;
    }

    echo "--- Iteration $iteration: dt = $dt, dtdiv = $dtdiv ---\n";
    echo "Current tjd = $tjd\n";

    $dc = [];
    $xs = array_fill(0, 6, 0.0);
    $xm = array_fill(0, 6, 0.0);
    $ls = array_fill(0, 6, 0.0);
    $lm = array_fill(0, 6, 0.0);
    $x1 = array_fill(0, 6, 0.0);
    $x2 = array_fill(0, 6, 0.0);

    for ($i = 0, $t = $tjd - $dt; $i <= 2; $i++, $t += $dt) {
        $serr = null;

        if (swe_calc($t, Constants::SE_SUN, $iflagcart, $xs, $serr) === Constants::SE_ERR) {
            echo "ERROR calculating Sun: $serr\n";
            exit;
        }
        if (swe_calc($t, Constants::SE_SUN, $iflag, $ls, $serr) === Constants::SE_ERR) {
            echo "ERROR calculating Sun: $serr\n";
            exit;
        }
        if (swe_calc($t, Constants::SE_MOON, $iflagcart, $xm, $serr) === Constants::SE_ERR) {
            echo "ERROR calculating Moon: $serr\n";
            exit;
        }
        if (swe_calc($t, Constants::SE_MOON, $iflag, $lm, $serr) === Constants::SE_ERR) {
            echo "ERROR calculating Moon: $serr\n";
            exit;
        }

        $dm = sqrt(EclipseUtils::squareSum($xm));
        $ds = sqrt(EclipseUtils::squareSum($xs));

        for ($k = 0; $k < 3; $k++) {
            $x1[$k] = $xs[$k] / $ds;
            $x2[$k] = $xm[$k] / $dm;
        }

        $dc[$i] = acos(VectorMath::dotProductUnit($x1, $x2)) * Constants::RADTODEG;

        echo "  Sample $i at t = $t: dc[$i] = {$dc[$i]}°\n";

        // Debug coordinates on first iteration only
        if ($iteration === 1) {
            echo sprintf("    xs=[%.6f, %.6f, %.6f], ds=%.6f\n", $xs[0], $xs[1], $xs[2], $ds);
            echo sprintf("    xm=[%.6f, %.6f, %.6f], dm=%.6f\n", $xm[0], $xm[1], $xm[2], $dm);
            echo sprintf("    x1=[%.6f, %.6f, %.6f]\n", $x1[0], $x1[1], $x1[2]);
            echo sprintf("    x2=[%.6f, %.6f, %.6f]\n", $x2[0], $x2[1], $x2[2]);
            $dot = VectorMath::dotProductUnit($x1, $x2);
            echo sprintf("    dot(x1,x2)=%.6f, acos(dot)=%.6f rad\n", $dot, acos($dot));
        }
    }

    $dtint = 0.0;
    $dctr = 0.0;
    EclipseUtils::findMaximum($dc[0], $dc[1], $dc[2], $dt, $dtint, $dctr);

    echo "  findMaximum returned: dtint = $dtint, dctr = $dctr°\n";
    echo "  Updating: tjd += $dtint + $dt = tjd + " . ($dtint + $dt) . "\n";

    $tjd += $dtint + $dt;

    echo "  New tjd = $tjd\n\n";

    if ($iteration > 50) {
        echo "Too many iterations, stopping\n";
        break;
    }
}

echo "\n=== Final result ===\n";
echo "tjd (ET) = $tjd\n";

// Convert to UT
$serr = null;
$tret0 = $tjd - swe_deltat_ex($tjd, $ifl, $serr);
$tret0 = $tjd - swe_deltat_ex($tret0, $ifl, $serr);

echo "tret[0] (UT) = $tret0\n";

// Calculate final eclipse type
$xs = array_fill(0, 6, 0.0);
$xm = array_fill(0, 6, 0.0);
$ls = array_fill(0, 6, 0.0);
$lm = array_fill(0, 6, 0.0);

swe_calc($tjd, Constants::SE_SUN, $iflagcart, $xs, $serr);
swe_calc($tjd, Constants::SE_SUN, $iflag, $ls, $serr);
swe_calc($tjd, Constants::SE_MOON, $iflagcart, $xm, $serr);
swe_calc($tjd, Constants::SE_MOON, $iflag, $lm, $serr);

$dctr = acos(VectorMath::dotProductUnit($xs, $xm)) * Constants::RADTODEG;
$rmoon = asin(EclipseUtils::RMOON / $lm[2]) * Constants::RADTODEG;
$rsun = asin(EclipseUtils::RSUN / $ls[2]) * Constants::RADTODEG;
$rsplusrm = $rsun + $rmoon;
$rsminusrm = $rsun - $rmoon;

echo "\nFinal positions:\n";
echo "  dctr = $dctr°\n";
echo "  rmoon = $rmoon°\n";
echo "  rsun = $rsun°\n";
echo "  rsplusrm = $rsplusrm°\n";
echo "  rsminusrm = $rsminusrm°\n";
echo "  |rsminusrm| = " . abs($rsminusrm) . "°\n";

if ($dctr < $rsminusrm) {
    echo "  Type: ANNULAR (dctr < rsminusrm)\n";
} elseif ($dctr < abs($rsminusrm)) {
    echo "  Type: TOTAL (dctr < |rsminusrm|)\n";
} elseif ($dctr <= $rsplusrm) {
    echo "  Type: PARTIAL (dctr <= rsplusrm)\n";
} else {
    echo "  NO ECLIPSE (dctr > rsplusrm)\n";
}
