<?php

// Moon radius calculation
$diameter = 3475000.0;
$AUNIT = 1.49597870700e11;
$dd = 0.002590;  // AU
$RADTODEG = 57.29577951308232;

$rdi = asin($diameter / 2.0 / $AUNIT / $dd) * $RADTODEG;
echo "Apparent Moon radius (no refraction): $rdi degrees\n";

// Refraction at horizon
$refr = 0.583333; // approx
$rdi_with_refr = $rdi + $refr;
echo "Apparent Moon radius (with refraction): $rdi_with_refr degrees\n";

// Compare with debug output: 0.55988843311234°
echo "\nExpected from debug: 0.55988843311234°\n";
echo "Difference: " . ($rdi_with_refr - 0.55988843311234) . "°\n";
