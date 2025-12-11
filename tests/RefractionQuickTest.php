<?php

declare(strict_types=1);

/**
 * Quick test for refraction functions.
 * Port of refraction calculations from swecl.c
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

function test_swe_refrac(): void
{
    echo "=== Testing swe_refrac() ===\n";

    // Standard atmospheric conditions
    $atpress = 1013.25; // millibars
    $attemp = 15.0;     // Celsius

    // Test cases: [true_alt, expected_apparent_alt, name]
    $test_cases = [
        [90.0, 90.0, "Zenith"],
        [45.0, 45.017, "45° altitude"],
        [15.0, 15.043, "15° altitude"],
        [5.0, 5.092, "5° altitude"],
        [0.0, 0.567, "Horizon"],
        [-0.5, 0.079, "Below horizon -0.5°"],
    ];

    echo "\n--- True to Apparent (SE_TRUE_TO_APP) ---\n";
    foreach ($test_cases as [$true_alt, $expected_app, $name]) {
        $apparent = swe_refrac($true_alt, $atpress, $attemp, Constants::SE_TRUE_TO_APP);
        $diff = abs($apparent - $expected_app);
        $status = $diff < 0.01 ? "✓" : "✗";
        printf("%s %s: true=%6.2f° → apparent=%7.4f° (expected ~%7.4f°, diff=%.4f°)\n",
            $status, $name, $true_alt, $apparent, $expected_app, $diff);
    }

    echo "\n--- Apparent to True (SE_APP_TO_TRUE) ---\n";
    // Test reverse direction
    $reverse_cases = [
        [90.0, 90.0, "Zenith"],
        [45.017, 45.0, "Apparent 45.017°"],
        [0.567, 0.0, "Apparent horizon"],
    ];

    foreach ($reverse_cases as [$app_alt, $expected_true, $name]) {
        $true_alt = swe_refrac($app_alt, $atpress, $attemp, Constants::SE_APP_TO_TRUE);
        $diff = abs($true_alt - $expected_true);
        $status = $diff < 0.01 ? "✓" : "✗";
        printf("%s %s: apparent=%7.4f° → true=%6.2f° (expected ~%6.2f°, diff=%.4f°)\n",
            $status, $name, $app_alt, $true_alt, $expected_true, $diff);
    }
}

function test_swe_refrac_extended(): void
{
    echo "\n=== Testing swe_refrac_extended() ===\n";

    $atpress = 1013.25;
    $attemp = 15.0;
    $lapse_rate = Constants::SE_LAPSE_RATE; // 0.0065 K/m

    // Test at sea level
    echo "\n--- At sea level (geoalt = 0m) ---\n";
    $geoalt = 0.0;

    $test_cases = [
        [0.0, "Horizon"],
        [5.0, "5° altitude"],
        [15.0, "15° altitude"],
        [-1.0, "Below horizon"],
    ];

    foreach ($test_cases as [$true_alt, $name]) {
        $dret = [];
        $result = swe_refrac_extended(
            $true_alt,
            $geoalt,
            $atpress,
            $attemp,
            $lapse_rate,
            Constants::SE_TRUE_TO_APP,
            $dret
        );

        printf("%s: true=%6.2f° → apparent=%7.4f°, refr=%7.4f°, dip=%7.4f°\n",
            $name, $true_alt, $result, $dret[2], $dret[3]);
    }

    // Test at elevation (e.g., mountain observatory at 2000m)
    echo "\n--- At 2000m elevation ---\n";
    $geoalt = 2000.0;

    foreach ($test_cases as [$true_alt, $name]) {
        $dret = [];
        $result = swe_refrac_extended(
            $true_alt,
            $geoalt,
            $atpress,
            $attemp,
            $lapse_rate,
            Constants::SE_TRUE_TO_APP,
            $dret
        );

        printf("%s: true=%6.2f° → apparent=%7.4f°, refr=%7.4f°, dip=%7.4f° (body %s horizon)\n",
            $name, $true_alt, $result, $dret[2], $dret[3],
            ($dret[0] != $dret[1]) ? "above" : "below");
    }

    // Test apparent to true
    echo "\n--- Apparent to True at sea level ---\n";
    $geoalt = 0.0;
    $app_test_cases = [
        [0.567, "Apparent horizon"],
        [5.0, "Apparent 5°"],
        [15.0, "Apparent 15°"],
    ];

    foreach ($app_test_cases as [$app_alt, $name]) {
        $dret = [];
        $result = swe_refrac_extended(
            $app_alt,
            $geoalt,
            $atpress,
            $attemp,
            $lapse_rate,
            Constants::SE_APP_TO_TRUE,
            $dret
        );

        printf("%s: apparent=%7.4f° → true=%6.2f°, refr=%7.4f°\n",
            $name, $app_alt, $result, $dret[2]);
    }
}

function test_swe_set_lapse_rate(): void
{
    echo "\n=== Testing swe_set_lapse_rate() ===\n";

    $atpress = 1013.25;
    $attemp = 15.0;
    $geoalt = 0.0;
    $true_alt = 0.0;

    // Test with default lapse rate
    $dret = [];
    $result1 = swe_refrac_extended(
        $true_alt, $geoalt, $atpress, $attemp,
        Constants::SE_LAPSE_RATE,
        Constants::SE_TRUE_TO_APP,
        $dret
    );

    printf("Default lapse rate (%.4f K/m): horizon refr=%7.4f°, dip=%7.4f°\n",
        Constants::SE_LAPSE_RATE, $dret[2], $dret[3]);

    // Test with different lapse rate (e.g., for different climate)
    $custom_lapse = 0.010; // Steeper temperature gradient
    swe_set_lapse_rate($custom_lapse);

    $dret2 = [];
    $result2 = swe_refrac_extended(
        $true_alt, $geoalt, $atpress, $attemp,
        $custom_lapse,
        Constants::SE_TRUE_TO_APP,
        $dret2
    );

    printf("Custom lapse rate (%.4f K/m): horizon refr=%7.4f°, dip=%7.4f°\n",
        $custom_lapse, $dret2[2], $dret2[3]);

    printf("Difference in refraction: %.4f° (%.2f%%)\n",
        $dret2[2] - $dret[2],
        abs($dret2[2] - $dret[2]) / $dret[2] * 100);

    // Reset to default
    swe_set_lapse_rate(Constants::SE_LAPSE_RATE);
}

// Run all tests
try {
    test_swe_refrac();
    test_swe_refrac_extended();
    test_swe_set_lapse_rate();

    echo "\n✓ All refraction tests completed successfully!\n";
} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
