<?php
/**
 * Deep debug for moshplan2 calculation
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierConstants;
use Swisseph\Moshier\Tables\MercuryTable;

// Constants from C code
const J2000 = 2451545.0;
const TIMESCALE = 3652500.0;
const STR = 4.8481368110953599359e-6;

$jdUT = 2460476.0;  // 2024-06-15 12:00 UT
$deltaT = 69.184 / 86400.0;
$jdTT = $jdUT + $deltaT;

echo "=== Deep Debug moshplan2 ===\n\n";
echo sprintf("JD (TT): %.10f\n", $jdTT);

$T = ($jdTT - J2000) / TIMESCALE;
echo sprintf("T = %.15e\n\n", $T);

$plan = MercuryTable::get();

echo "--- Planet table info ---\n";
echo sprintf("Distance: %.15e\n", $plan->distance);
echo sprintf("maxHarmonic: [%s]\n", implode(', ', $plan->maxHarmonic));
echo "\n";

echo "--- Mean anomalies (radians) ---\n";
$freqs = MoshierConstants::FREQS;
$phases = MoshierConstants::PHASES;

// Calculate mean anomalies for each planet
for ($i = 0; $i < 9; $i++) {
    $j = $plan->maxHarmonic[$i];
    if ($j > 0) {
        $arg = MoshierConstants::mods3600($freqs[$i] * $T) + $phases[$i];
        $sr = $arg * STR;  // Convert to radians
        echo sprintf("Planet %d: arg=%.10f arcsec, sr=%.15f rad (%.6f°)\n",
            $i, $arg, $sr, rad2deg($sr));
    }
}
echo "\n";

echo "--- First polynomial term (np=0) ---\n";
// argTbl[0] = 0 (np), argTbl[1] = 3 (nt)
$argTbl = $plan->argTbl;
$lonTbl = $plan->lonTbl;

echo sprintf("np=%d, nt=%d\n", $argTbl[0], $argTbl[1]);
echo sprintf("Lon coefficients: [%.15e, %.15e, %.15e, %.15e]\n",
    $lonTbl[0], $lonTbl[1], $lonTbl[2], $lonTbl[3]);

// Polynomial: cu = c[0], then cu = cu*T + c[1], etc.
// nt=3 means we do 3 iterations, using 4 coefficients total
$nt = $argTbl[1];
$cu = $lonTbl[0];
echo sprintf("Step 0: cu = %.15e\n", $cu);
for ($ip = 0; $ip < $nt; $ip++) {
    $cu = $cu * $T + $lonTbl[$ip + 1];
    echo sprintf("Step %d: cu = cu*T + %.15e = %.15e\n", $ip + 1, $lonTbl[$ip + 1], $cu);
}
echo sprintf("Polynomial result (before mods3600): %.15e arcsec\n", $cu);
$sl = MoshierConstants::mods3600($cu);
echo sprintf("After mods3600: %.15e arcsec (%.10f°)\n", $sl, $sl / 3600.0);
echo "\n";

// Let me manually calculate what it SHOULD be
// C uses coefficients in Horner scheme:
// result = c0 + T*(c1 + T*(c2 + T*c3))
// But the loop does: cu = c0, cu = cu*T + c1, cu = cu*T + c2, cu = cu*T + c3
// which is: ((c0*T + c1)*T + c2)*T + c3 = c0*T^3 + c1*T^2 + c2*T + c3
// Coefficients are stored as: c0=highest power, c3=lowest (constant)

echo "--- Manual polynomial check ---\n";
$c0 = $lonTbl[0];  // T^3 coefficient
$c1 = $lonTbl[1];  // T^2 coefficient
$c2 = $lonTbl[2];  // T^1 coefficient
$c3 = $lonTbl[3];  // T^0 coefficient (constant term)
$manual = $c0 * pow($T, 3) + $c1 * pow($T, 2) + $c2 * $T + $c3;
echo sprintf("Manual: %.15e\n", $manual);
echo sprintf("After mods3600: %.15e (%.10f°)\n",
    MoshierConstants::mods3600($manual), MoshierConstants::mods3600($manual) / 3600.0);
echo "\n";

// Compare with expected from swetest
// Mercury: 89.26° = 321336"
echo "--- Expected from swetest ---\n";
echo sprintf("Expected longitude: ~89.26° = ~321336\"\n");
echo sprintf("Our longitude:      %.10f° = %.2f\"\n", $sl / 3600.0, $sl);
echo sprintf("Difference: %.2f° = %.2f\"\n",
    89.26 - $sl / 3600.0, (89.26 - $sl / 3600.0) * 3600.0);

echo "\n=== Done ===\n";
