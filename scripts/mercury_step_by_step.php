<?php
/**
 * Step-by-step Mercury calculation comparison with C
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierConstants;
use Swisseph\Moshier\Tables\MercuryTable;

const J2000 = 2451545.0;
const TIMESCALE = 3652500.0;
const STR = 4.8481368110953599359e-6;

$jdUT = 2460476.0;
$deltaT = 69.184 / 86400.0;
$jdTT = $jdUT + $deltaT;

echo "=== Mercury Calculation Step by Step ===\n\n";
echo sprintf("JD_TT: %.10f\n", $jdTT);

$T = ($jdTT - J2000) / TIMESCALE;
echo sprintf("T = (%.10f - %.1f) / %.1f = %.15e\n\n", $jdTT, J2000, TIMESCALE, $T);

$plan = MercuryTable::get();

// Process argTbl following the C algorithm exactly
$argTbl = $plan->argTbl;
$lonTbl = $plan->lonTbl;
$latTbl = $plan->latTbl;
$radTbl = $plan->radTbl;

// Build ss/cc arrays
echo "--- Building sin/cos tables ---\n";
$ss = [];
$cc = [];

for ($i = 0; $i < 9; $i++) {
    $j = $plan->maxHarmonic[$i];
    if ($j > 0) {
        $freqT = MoshierConstants::FREQS[$i] * $T;
        $mod = MoshierConstants::mods3600($freqT);
        $arg = ($mod + MoshierConstants::PHASES[$i]) * STR;

        echo sprintf("Planet %d: FREQ=%.4f, phase=%.4f, arg=%.10f rad\n",
            $i, MoshierConstants::FREQS[$i], MoshierConstants::PHASES[$i], $arg);

        // sscc
        $su = sin($arg);
        $cu = cos($arg);
        $ss[$i] = [0 => $su];
        $cc[$i] = [0 => $cu];

        $sv = 2.0 * $su * $cu;
        $cv = $cu * $cu - $su * $su;
        $ss[$i][1] = $sv;
        $cc[$i][1] = $cv;

        for ($k = 2; $k < $j; $k++) {
            $s = $su * $cv + $cu * $sv;
            $cv = $cu * $cv - $su * $sv;
            $sv = $s;
            $ss[$i][$k] = $sv;
            $cc[$i][$k] = $cv;
        }
    }
}
echo "\n";

// Now process the table
$pIdx = 0;
$lIdx = 0;
$sl = 0.0;

echo "--- Processing longitude terms ---\n";
$termNum = 0;

while (true) {
    $np = $argTbl[$pIdx++];

    if ($np < 0) {
        echo "End marker reached\n";
        break;
    }

    if ($np === 0) {
        // Polynomial term
        $nt = $argTbl[$pIdx++];
        $cu = $lonTbl[$lIdx++];
        for ($ip = 0; $ip < $nt; $ip++) {
            $cu = $cu * $T + $lonTbl[$lIdx++];
        }
        $contribution = MoshierConstants::mods3600($cu);
        $sl += $contribution;
        echo sprintf("Term %d: POLYNOMIAL (nt=%d) -> %.6f arcsec (%.6f°)\n",
            $termNum++, $nt, $contribution, $contribution / 3600.0);
        continue;
    }

    // Periodic term
    $k1 = 0;
    $cv = 0.0;
    $sv = 0.0;

    for ($ip = 0; $ip < $np; $ip++) {
        $j = $argTbl[$pIdx++];
        $m = $argTbl[$pIdx++] - 1;

        if ($j !== 0) {
            $k = ($j < 0) ? -$j : $j;
            $k -= 1;

            $su_val = $ss[$m][$k] ?? 0.0;
            if ($j < 0) {
                $su_val = -$su_val;
            }
            $cu_val = $cc[$m][$k] ?? 0.0;

            if ($k1 === 0) {
                $sv = $su_val;
                $cv = $cu_val;
                $k1 = 1;
            } else {
                $t = $su_val * $cv + $cu_val * $sv;
                $cv = $cu_val * $cv - $su_val * $sv;
                $sv = $t;
            }
        }
    }

    $nt = $argTbl[$pIdx++];

    $cu = $lonTbl[$lIdx++];
    $su = $lonTbl[$lIdx++];
    for ($ip = 0; $ip < $nt; $ip++) {
        $cu = $cu * $T + $lonTbl[$lIdx++];
        $su = $su * $T + $lonTbl[$lIdx++];
    }

    $contribution = $cu * $cv + $su * $sv;
    $sl += $contribution;

    if ($termNum < 20) {
        echo sprintf("Term %d: PERIODIC (np=%d, nt=%d) -> %.6f arcsec, total=%.6f\n",
            $termNum++, $np, $nt, $contribution, $sl);
    } else {
        $termNum++;
    }
}

echo sprintf("\nTotal terms processed: %d\n", $termNum);
echo sprintf("Final sl = %.6f arcsec = %.10f°\n", $sl, $sl / 3600.0);
echo sprintf("Final longitude (radians): %.15f\n", $sl * STR);

// swetest reference
echo "\nswetest reference: 89.2584683° (mean of date)\n";
echo sprintf("Difference: %.6f°\n", 89.2584683 - $sl / 3600.0);
