<?php

require __DIR__ . '/../tests/bootstrap.php';

// Test J2000.0 (2000-01-01 12:00 TT = JD 2451545.0)
swe_set_sid_mode(0, 0, 0); // Fagan/Bradley

echo "=== Testing Fagan/Bradley ayanamsha at J2000.0 ===\n\n";

// Test with UT time
$jd_ut = 2451544.5; // 2000-01-01 00:00 UT
$daya = null;
$serr = null;
swe_get_ayanamsa_ex_ut($jd_ut, 0, $daya, $serr);
printf("JD %.1f UT: %.10f°\n", $jd_ut, $daya);

// Test with TT time directly
$jd_tt = 2451545.0; // J2000.0 exactly
$daya = null;
swe_get_ayanamsa_ex($jd_tt, 0, $daya, $serr);
printf("JD %.1f TT: %.10f°\n", $jd_tt, $daya);

// Expected from swetest: 24.7364301°
echo "\nExpected from swetest (JD 2451545 TT): 24.7364301°\n";
echo "Difference: " . abs($daya - 24.7364301) * 3600 . " arcsec\n";

// Let's trace the calculation step by step
echo "\n=== Detailed calculation trace ===\n";

// Get ayanamsha data
$data = \Swisseph\Domain\Sidereal\AyanamsaData::get(0); // Fagan/Bradley
list($t0, $ayan_t0, $t0_is_UT, $prec_offset) = $data;
printf("t0 = %.6f, ayan_t0 = %.10f, t0_is_UT = %d, prec_offset = %d\n", $t0, $ayan_t0, $t0_is_UT, $prec_offset);

// Convert t0 to TT if needed
if ($t0_is_UT) {
    $dt = \Swisseph\DeltaT::deltaTSecondsFromJd($t0) / 86400.0;
    $t0_tt = $t0 + $dt;
    printf("t0 (UT->TT): %.6f + %.6f = %.6f\n", $t0, $dt, $t0_tt);
    $t0 = $t0_tt;
}

echo "\nCalculating ayanamsha for JD $jd_tt...\n";

// Vernal point at jd_tt
$x = [1.0, 0.0, 0.0];
echo "Initial vernal point (J2000 equatorial): [" . implode(", ", $x) . "]\n";

// Precess from jd_tt to J2000
if (abs($jd_tt - 2451545.0) > 0.001) {
    echo "Precess from JD $jd_tt to J2000...\n";
    \Swisseph\Precession::precess($x, $jd_tt, 0, 1);
    echo "After precession to J2000: [" . implode(", ", $x) . "]\n";
} else {
    echo "Already at J2000, no precession needed\n";
}

// Precess from J2000 to t0
if (abs($t0 - 2451545.0) > 0.001) {
    echo "Precess from J2000 to t0 ($t0)...\n";
    \Swisseph\Precession::precess($x, $t0, 0, -1);
    echo "After precession to t0: [" . implode(", ", $x) . "]\n";
}

// Convert to ecliptic at t0
$eps_t0 = \Swisseph\Obliquity::meanObliquityRadFromJdTT($t0);
printf("Obliquity at t0: %.10f rad = %.10f°\n", $eps_t0, rad2deg($eps_t0));

$x_ecl = \Swisseph\Coordinates::equatorialToEcliptic($x[0], $x[1], $x[2], $eps_t0);
echo "Ecliptic coordinates at t0: [" . implode(", ", $x_ecl) . "]\n";

// Get longitude
$lon_t0 = atan2($x_ecl[1], $x_ecl[0]);
printf("Longitude at t0: %.10f rad = %.10f°\n", $lon_t0, rad2deg($lon_t0));

// Calculate ayanamsha
$ayanamsha = -rad2deg($lon_t0) + $ayan_t0;
printf("Ayanamsha = -%.10f + %.10f = %.10f°\n", rad2deg($lon_t0), $ayan_t0, $ayanamsha);

echo "\nFinal result: $ayanamsha°\n";
echo "Expected:     24.7364301°\n";
echo "Difference:   " . abs($ayanamsha - 24.7364301) * 3600 . " arcsec\n";
