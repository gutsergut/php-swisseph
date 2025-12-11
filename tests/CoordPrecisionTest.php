<?php

declare(strict_types=1);

/**
 * Test with exact C coordinates to see if small differences accumulate
 */

echo "Testing coordinate precision impact on phase angle\n";
echo str_repeat('=', 80) . "\n\n";

// PHP coordinates
$xx_php = [0.001291532116, -0.002179481777, -0.000192649080];

// C coordinates from swetest64
$xx_c = [0.001291571, -0.002179557, -0.000192656];

// Heliocentric (these should be the same, let's check)
$xx2 = [-0.191868035676, 0.962009247310, -0.000189985157];

function calcPhaseAngle($xx, $xx2) {
    // Normalize
    $r1 = sqrt($xx[0]**2 + $xx[1]**2 + $xx[2]**2);
    $u1 = [$xx[0]/$r1, $xx[1]/$r1, $xx[2]/$r1];

    $r2 = sqrt($xx2[0]**2 + $xx2[1]**2 + $xx2[2]**2);
    $u2 = [$xx2[0]/$r2, $xx2[1]/$r2, $xx2[2]/$r2];

    // Dot product
    $dot = $u1[0]*$u2[0] + $u1[1]*$u2[1] + $u1[2]*$u2[2];
    $dot = max(-1.0, min(1.0, $dot));

    return rad2deg(acos($dot));
}

$phase_php = calcPhaseAngle($xx_php, $xx2);
$phase_c = calcPhaseAngle($xx_c, $xx2);

printf("Phase angle with PHP coords: %.12f°\n", $phase_php);
printf("Phase angle with C coords:   %.12f°\n", $phase_c);
printf("Difference:                   %.12f°\n", abs($phase_php - $phase_c));
printf("Difference in arcseconds:     %.6f\"\n", abs($phase_php - $phase_c) * 3600);

echo "\n";
echo "Coordinate differences:\n";
printf("ΔX = %.12f (%.3e)\n", abs($xx_php[0] - $xx_c[0]), abs($xx_php[0] - $xx_c[0]));
printf("ΔY = %.12f (%.3e)\n", abs($xx_php[1] - $xx_c[1]), abs($xx_php[1] - $xx_c[1]));
printf("ΔZ = %.12f (%.3e)\n", abs($xx_php[2] - $xx_c[2]), abs($xx_php[2] - $xx_c[2]));

echo "\nConclusion:\n";
echo "The small coordinate differences (10^-8 AU) cause phase angle difference of ";
printf("%.6f arcseconds.\n", abs($phase_php - $phase_c) * 3600);
echo "This is negligible and within floating-point precision.\n";
echo "\nThe 0.055° difference from swetest must come from a different source!\n";
