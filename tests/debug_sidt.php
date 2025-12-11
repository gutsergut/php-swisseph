<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\DeltaT;
use Swisseph\Sidereal;
use Swisseph\Math;

// Same test case: JD 2460408.5 (2024-04-08 00:00:00 TT)
$tjd_ut = 2460408.5;

// Calculate obliquity and nutation
$x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
$serr = '';
swe_calc_ut($tjd_ut, Constants::SE_ECL_NUT, 0, $x, $serr);
$eps = $x[0];
$nut = $x[2];

echo "Input parameters:\n";
echo sprintf("  tjd_ut = %.9f\n", $tjd_ut);
echo sprintf("  eps = %.15f degrees\n", $eps);
echo sprintf("  nut = %.15f degrees\n", $nut);
echo "\n";

// Now manually step through sidtime0 calculation
echo "Step-by-step calculation (IERS 2010 path):\n";

$jd = $tjd_ut;
$jd0 = floor($jd);
$secs = $tjd_ut - $jd0;

if ($secs < 0.5) {
    $jd0 -= 0.5;
    $secs += 0.5;
} else {
    $jd0 += 0.5;
    $secs -= 0.5;
}

$secs *= 86400.0;
$tu = ($jd0 - Constants::J2000) / 36525.0;

echo sprintf("  jd0 = %.9f\n", $jd0);
echo sprintf("  secs = %.15f seconds\n", $secs);
echo sprintf("  tu = %.15f centuries\n", $tu);
echo "\n";

// ERA-based calculation
$jdrel = $tjd_ut - Constants::J2000;
$deltaT = DeltaT::deltaTSecondsFromJd($tjd_ut);
$tt = ($tjd_ut + $deltaT / 86400.0 - Constants::J2000) / 36525.0;

echo sprintf("  jdrel = %.15f days\n", $jdrel);
echo sprintf("  deltaT = %.15f seconds\n", $deltaT);
echo sprintf("  tt = %.15f centuries\n", $tt);
echo "\n";

// ERA term
$era_term = (0.7790572732640 + 1.00273781191135448 * $jdrel) * 360.0;
echo sprintf("  ERA term = %.15f degrees\n", $era_term);

$era_normalized = Math::normAngleDeg($era_term);
echo sprintf("  ERA normalized = %.15f degrees\n", $era_normalized);
echo "\n";

// Polynomial part
$poly = 0.014506 + $tt * (4612.156534 +
        $tt * (1.3915817 +
        $tt * (-0.00000044 +
        $tt * (-0.000029956 +
        $tt * -0.0000000368))));
$poly_deg = $poly / 3600.0;

echo sprintf("  Polynomial part = %.15f arcsec\n", $poly);
echo sprintf("  Polynomial part = %.15f degrees\n", $poly_deg);
echo "\n";

// Sum before non-polynomial
$gmst_before_np = $era_normalized + $poly_deg;
echo sprintf("  GMST before non-poly = %.15f degrees\n", $gmst_before_np);
echo "\n";

// Non-polynomial part (need to call the actual function)
// Since sidtimeNonPolynomialPart is private, let's just call full sidtime0
$sidt_result = Sidereal::sidtime0($tjd_ut, $eps, $nut);
echo sprintf("FINAL PHP result: %.15f hours = %.15f degrees\n", $sidt_result, $sidt_result * 15.0);
echo "\n";

// Now compare with expected C result
$expected_sidt_deg = 111.914850492678326;
$expected_sidt_hours = $expected_sidt_deg / 15.0;
echo sprintf("Expected C result: %.15f hours = %.15f degrees\n", $expected_sidt_hours, $expected_sidt_deg);
echo "\n";

$diff_deg = ($sidt_result * 15.0) - $expected_sidt_deg;
$diff_arcmin = $diff_deg * 60.0;
$diff_arcsec = $diff_deg * 3600.0;

echo "Difference:\n";
echo sprintf("  %.15f degrees\n", $diff_deg);
echo sprintf("  %.15f arcmin\n", $diff_arcmin);
echo sprintf("  %.15f arcsec\n", $diff_arcsec);
