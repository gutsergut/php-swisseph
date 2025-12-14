<?php
/**
 * Debug script to trace Moshier calculation step by step
 * For comparison with C code
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierConstants;
use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Moshier\Tables\MercuryTable;

// J2000.0 exactly
$J = 2451545.0;
$iplm = 0; // Mercury

echo "=== Moshier Debug for Mercury at J2000.0 ===\n\n";

// Constants
$J2000 = 2451545.0;
$TIMESCALE = 3652500.0;
$STR = 4.8481368110953599359e-6;

// Step 1: Calculate T
$T = ($J - $J2000) / $TIMESCALE;
echo "Step 1: T = $T (should be 0)\n\n";

// Get planet table
$plan = MercuryTable::get();

// Build sin/cos tables like in C code
$ss = [];
$cc = [];
for ($i = 0; $i < 9; $i++) {
    $j = $plan->maxHarmonic[$i];
    if ($j > 0) {
        $sr = (MoshierConstants::mods3600(MoshierConstants::FREQS[$i] * $T)
               + MoshierConstants::PHASES[$i]) * $STR;
        // sscc inline
        $su = sin($sr);
        $cu = cos($sr);
        $ss[$i][0] = $su;
        $cc[$i][0] = $cu;
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

echo "Step 2: ss/cc tables for Mercury (i=0)\n";
printf("  ss[0][0] = %.15f, cc[0][0] = %.15f\n", $ss[0][0], $cc[0][0]);
printf("  ss[0][1] = %.15f, cc[0][1] = %.15f\n", $ss[0][1], $cc[0][1]);
echo "\n";

// Process argument table manually
$argTbl = $plan->argTbl;
$lonTbl = $plan->lonTbl;
$latTbl = $plan->latTbl;
$radTbl = $plan->radTbl;

$pIdx = 0;
$lIdx = 0;
$bIdx = 0;
$rIdx = 0;

$sl = 0.0;
$sb = 0.0;
$sr_sum = 0.0;

$termNum = 0;
echo "Step 3: Processing terms (first 5 of each type)...\n";

while (true) {
    $np = $argTbl[$pIdx++];
    if ($np < 0) break;

    $termNum++;

    if ($np === 0) {
        // Polynomial term
        $nt = $argTbl[$pIdx++];

        // Longitude
        $cu = $lonTbl[$lIdx++];
        for ($ip = 0; $ip < $nt; $ip++) {
            $cu = $cu * $T + $lonTbl[$lIdx++];
        }
        $contrib = MoshierConstants::mods3600($cu);

        if ($termNum <= 5) {
            printf("  Poly #%d: nt=%d, cu=%.10f, mods=%.10f\n", $termNum, $nt, $cu, $contrib);
        }

        $sl += $contrib;

        // Latitude
        $cu = $latTbl[$bIdx++];
        for ($ip = 0; $ip < $nt; $ip++) {
            $cu = $cu * $T + $latTbl[$bIdx++];
        }
        $sb += $cu;

        // Radius
        $cu = $radTbl[$rIdx++];
        for ($ip = 0; $ip < $nt; $ip++) {
            $cu = $cu * $T + $radTbl[$rIdx++];
        }
        $sr_sum += $cu;

        continue;
    }

    // Harmonic term
    $k1 = 0;
    $cv = 0.0;
    $sv = 0.0;

    for ($ip = 0; $ip < $np; $ip++) {
        $j = $argTbl[$pIdx++];
        $m = $argTbl[$pIdx++] - 1;

        if ($j !== 0) {
            $k = ($j < 0) ? -$j : $j;
            $k -= 1;

            $su = $ss[$m][$k];
            if ($j < 0) $su = -$su;
            $cu = $cc[$m][$k];

            if ($k1 === 0) {
                $sv = $su;
                $cv = $cu;
                $k1 = 1;
            } else {
                $t = $su * $cv + $cu * $sv;
                $cv = $cu * $cv - $su * $sv;
                $sv = $t;
            }
        }
    }

    $nt = $argTbl[$pIdx++];

    // Longitude
    $cu = $lonTbl[$lIdx++];
    $su = $lonTbl[$lIdx++];
    for ($ip = 0; $ip < $nt; $ip++) {
        $cu = $cu * $T + $lonTbl[$lIdx++];
        $su = $su * $T + $lonTbl[$lIdx++];
    }
    $contrib = $cu * $cv + $su * $sv;

    if ($termNum <= 8) {
        printf("  Harm #%d: np=%d, nt=%d, cu=%.6f, su=%.6f, cv=%.6f, sv=%.6f, contrib=%.6f\n",
               $termNum, $np, $nt, $cu, $su, $cv, $sv, $contrib);
    }

    $sl += $contrib;

    // Latitude
    $cu = $latTbl[$bIdx++];
    $su = $latTbl[$bIdx++];
    for ($ip = 0; $ip < $nt; $ip++) {
        $cu = $cu * $T + $latTbl[$bIdx++];
        $su = $su * $T + $latTbl[$lIdx++];
    }
    $sb += $cu * $cv + $su * $sv;

    // Radius
    $cu = $radTbl[$rIdx++];
    $su = $radTbl[$rIdx++];
    for ($ip = 0; $ip < $nt; $ip++) {
        $cu = $cu * $T + $radTbl[$rIdx++];
        $su = $su * $T + $radTbl[$rIdx++];
    }
    $sr_sum += $cu * $cv + $su * $sv;
}

echo "\nStep 4: Final sums\n";
printf("  sl = %.10f arcsec\n", $sl);
printf("  sb = %.10f arcsec\n", $sb);
printf("  sr_sum = %.10f\n", $sr_sum);

$lon_rad = $STR * $sl;
$lat_rad = $STR * $sb;
$dist = $STR * $plan->distance * $sr_sum + $plan->distance;

$lon_deg = rad2deg($lon_rad);
$lon_deg = fmod($lon_deg, 360.0);
if ($lon_deg < 0) $lon_deg += 360.0;

printf("\nStep 5: Final coordinates\n");
printf("  Longitude: %.10f deg\n", $lon_deg);
printf("  Latitude: %.10f deg\n", rad2deg($lat_rad));
printf("  Distance: %.12f AU\n", $dist);

echo "\nExpected: 253.7716522°\n";
printf("Difference: %.2f arcsec\n", ($lon_deg - 253.7716522) * 3600);
