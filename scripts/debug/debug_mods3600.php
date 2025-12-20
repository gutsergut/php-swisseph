<?php
/**
 * Debug mods3600 and argument calculation
 */

const J2000 = 2451545.0;
const TIMESCALE = 3652500.0;
const STR = 4.8481368110953599359e-6;

$jdTT = 2460476.0008007409;
$T = ($jdTT - J2000) / TIMESCALE;

echo sprintf("T = %.17e\n", $T);

$freq0 = 53810162868.8982;  // Mercury frequency
$phase0 = 252.25090552 * 3600.0;  // Mercury phase

echo sprintf("freq0 = %.4f\n", $freq0);
echo sprintf("phase0 = %.4f\n", $phase0);

$freqT = $freq0 * $T;
echo sprintf("freq0 * T = %.17e\n", $freqT);

// mods3600: reduce to 0-1296000 arcsec (0-360°)
function mods3600(float $x): float {
    return $x - 1.296e6 * floor($x / 1.296e6);
}

$mod = mods3600($freqT);
echo sprintf("mods3600(freq*T) = %.17e\n", $mod);

$arg = $mod + $phase0;
echo sprintf("arg = mod + phase = %.17e arcsec\n", $arg);

$argRad = $arg * STR;
echo sprintf("arg in radians = %.17f\n", $argRad);
echo sprintf("arg in degrees = %.10f\n", rad2deg($argRad));

// Check: the argument should be Mercury's mean anomaly
// At J2000, Mercury's mean anomaly (M) ≈ 252.25°
// After T = 0.00244... (about 24.5 years), it should have increased

$orbitalPeriod = 87.969; // days
$daysSinceJ2000 = $jdTT - J2000;
$orbits = $daysSinceJ2000 / $orbitalPeriod;
echo sprintf("\nDays since J2000: %.4f\n", $daysSinceJ2000);
echo sprintf("Approximate orbits: %.4f\n", $orbits);
echo sprintf("Fractional orbit: %.4f (= %.2f°)\n",
    fmod($orbits, 1.0), fmod($orbits, 1.0) * 360.0);
