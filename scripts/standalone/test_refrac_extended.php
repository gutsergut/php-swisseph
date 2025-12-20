<?php
/**
 * Test script for swe_refrac_extended()
 * Verifies extended refraction calculations with lapse rate
 */

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

echo "=== swe_refrac_extended() Test ===\n\n";

// Test parameters
$geoalt = 0.0;           // Sea level
$atpress = 1013.25;      // Standard atmospheric pressure (millibars)
$attemp = 15.0;          // Standard temperature (Celsius)
$lapse_rate = 0.0065;    // Standard lapse rate (K/m)

echo "Test conditions:\n";
echo "  Observer altitude: {$geoalt} m\n";
echo "  Atmospheric pressure: {$atpress} mbar\n";
echo "  Temperature: {$attemp} °C\n";
echo "  Lapse rate: {$lapse_rate} K/m\n\n";

// Test 1: True to apparent altitude for various altitudes
echo "Test 1: True to Apparent Altitude\n";
echo "====================================\n";

$test_altitudes = [-5, 0, 5, 10, 20, 30, 45, 60, 75, 90];

foreach ($test_altitudes as $true_alt) {
    $dret = null;
    $apparent_alt = swe_refrac_extended(
        $true_alt,
        $geoalt,
        $atpress,
        $attemp,
        $lapse_rate,
        Constants::SE_TRUE_TO_APP,
        $dret
    );
    
    printf("True alt: %6.2f° => Apparent alt: %7.4f° (refr: %7.4f°, dip: %7.4f°)\n",
        $true_alt, $apparent_alt, $dret[2], $dret[3]);
}

echo "\n";

// Test 2: Apparent to true altitude
echo "Test 2: Apparent to True Altitude\n";
echo "===================================\n";

foreach ($test_altitudes as $apparent_alt_input) {
    $dret = null;
    $true_alt = swe_refrac_extended(
        $apparent_alt_input,
        $geoalt,
        $atpress,
        $attemp,
        $lapse_rate,
        Constants::SE_APP_TO_TRUE,
        $dret
    );
    
    printf("Apparent alt: %6.2f° => True alt: %7.4f° (refr: %7.4f°, dip: %7.4f°)\n",
        $apparent_alt_input, $true_alt, $dret[2], $dret[3]);
}

echo "\n";

// Test 3: Roundtrip test (true -> apparent -> true)
echo "Test 3: Roundtrip Test (True -> Apparent -> True)\n";
echo "====================================================\n";

foreach ([0, 10, 30, 45, 60, 89] as $original_true_alt) {
    $dret = null;
    
    // True to apparent
    $apparent_alt = swe_refrac_extended(
        $original_true_alt,
        $geoalt,
        $atpress,
        $attemp,
        $lapse_rate,
        Constants::SE_TRUE_TO_APP,
        $dret
    );
    
    // Apparent back to true
    $recovered_true_alt = swe_refrac_extended(
        $apparent_alt,
        $geoalt,
        $atpress,
        $attemp,
        $lapse_rate,
        Constants::SE_APP_TO_TRUE,
        $dret
    );
    
    $error = abs($recovered_true_alt - $original_true_alt);
    $status = $error < 0.001 ? "✓ PASS" : "✗ FAIL";
    
    printf("%s  Original: %6.2f° => Apparent: %7.4f° => Recovered: %7.4f° (error: %.6f°)\n",
        $status, $original_true_alt, $apparent_alt, $recovered_true_alt, $error);
}

echo "\n";

// Test 4: Effect of observer altitude
echo "Test 4: Effect of Observer Altitude on Dip\n";
echo "============================================\n";

$altitudes = [0, 10, 100, 1000, 2000, 5000];
$test_angle = 0.0;

foreach ($altitudes as $obs_alt) {
    $dret = null;
    swe_refrac_extended(
        $test_angle,
        $obs_alt,
        $atpress,
        $attemp,
        $lapse_rate,
        Constants::SE_TRUE_TO_APP,
        $dret
    );
    
    printf("Observer at %5d m => Dip: %7.4f°\n", $obs_alt, $dret[3]);
}

echo "\n";

// Test 5: Extreme conditions
echo "Test 5: Extreme Conditions\n";
echo "===========================\n";

// Very low altitude (below horizon)
$dret = null;
$result = swe_refrac_extended(
    -10.0,
    0.0,
    $atpress,
    $attemp,
    $lapse_rate,
    Constants::SE_TRUE_TO_APP,
    $dret
);
printf("Very low altitude (-10°): result = %.4f° (should return input)\n", $result);

// Altitude > 90° (should be corrected to 180-alt)
$dret = null;
$result = swe_refrac_extended(
    95.0,
    0.0,
    $atpress,
    $attemp,
    $lapse_rate,
    Constants::SE_TRUE_TO_APP,
    $dret
);
printf("Altitude > 90° (95°): result = %.4f° (should use 85°)\n", $result);

// High pressure (mountain top)
$dret = null;
$result_high_p = swe_refrac_extended(
    10.0,
    0.0,
    1030.0,  // High pressure
    $attemp,
    $lapse_rate,
    Constants::SE_TRUE_TO_APP,
    $dret
);
printf("High pressure (1030 mbar) at 10°: apparent = %.4f°\n", $result_high_p);

// Low pressure
$dret = null;
$result_low_p = swe_refrac_extended(
    10.0,
    0.0,
    900.0,  // Low pressure
    $attemp,
    $lapse_rate,
    Constants::SE_TRUE_TO_APP,
    $dret
);
printf("Low pressure (900 mbar) at 10°: apparent = %.4f°\n", $result_low_p);

echo "\n=== All tests completed ===\n";
