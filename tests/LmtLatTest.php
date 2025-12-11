<?php

declare(strict_types=1);

/**
 * Quick test for LMT/LAT conversion functions.
 * Tests swe_lmt_to_lat() and swe_lat_to_lmt() conversions.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

function test_lmt_to_lat(): void
{
    echo "=== Testing swe_lmt_to_lat() ===\n\n";

    // Test date: 2024-01-01 12:00 LMT at various longitudes
    $jd_base = 2460310.0; // Roughly 2024-01-01 12:00 UT

    $test_cases = [
        ['lon' => 0.0, 'name' => 'Greenwich (0°)'],
        ['lon' => 15.0, 'name' => 'Central Europe (15°E)'],
        ['lon' => -75.0, 'name' => 'New York (-75°W)'],
        ['lon' => 120.0, 'name' => 'Beijing (120°E)'],
        ['lon' => -122.0, 'name' => 'San Francisco (-122°W)'],
    ];

    echo "Converting Local Mean Time to Local Apparent Time\n";
    echo "Date: ~2024-01-01 12:00 LMT\n\n";

    foreach ($test_cases as $test) {
        $geolon = $test['lon'];
        $name = $test['name'];

        // Create LMT for this longitude
        $tjd_lmt = $jd_base + ($geolon / 360.0);

        // Convert to LAT
        $tjd_lat = 0.0;
        $serr = null;
        $ret = swe_lmt_to_lat($tjd_lmt, $geolon, $tjd_lat, $serr);

        if ($ret === Constants::SE_OK) {
            $diff_minutes = ($tjd_lat - $tjd_lmt) * 24 * 60; // Difference in minutes
            printf("✓ %-30s LMT: %.6f → LAT: %.6f (Δ=%+.2f min)\n",
                $name, $tjd_lmt, $tjd_lat, $diff_minutes);
        } else {
            echo "✗ $name failed: $serr\n";
        }
    }

    echo "\n";
}

function test_lat_to_lmt(): void
{
    echo "=== Testing swe_lat_to_lmt() ===\n\n";

    $jd_base = 2460310.0;

    $test_cases = [
        ['lon' => 0.0, 'name' => 'Greenwich (0°)'],
        ['lon' => 30.0, 'name' => 'Cairo (30°E)'],
        ['lon' => -90.0, 'name' => 'Chicago (-90°W)'],
        ['lon' => 139.7, 'name' => 'Tokyo (139.7°E)'],
    ];

    echo "Converting Local Apparent Time to Local Mean Time\n";
    echo "Date: ~2024-01-01 12:00 LAT\n\n";

    foreach ($test_cases as $test) {
        $geolon = $test['lon'];
        $name = $test['name'];

        // Create LAT for this longitude
        $tjd_lat = $jd_base + ($geolon / 360.0);

        // Convert to LMT
        $tjd_lmt = 0.0;
        $serr = null;
        $ret = swe_lat_to_lmt($tjd_lat, $geolon, $tjd_lmt, $serr);

        if ($ret === Constants::SE_OK) {
            $diff_minutes = ($tjd_lmt - $tjd_lat) * 24 * 60; // Difference in minutes
            printf("✓ %-30s LAT: %.6f → LMT: %.6f (Δ=%+.2f min)\n",
                $name, $tjd_lat, $tjd_lmt, $diff_minutes);
        } else {
            echo "✗ $name failed: $serr\n";
        }
    }

    echo "\n";
}

function test_round_trip(): void
{
    echo "=== Testing Round-Trip Conversions ===\n\n";

    echo "Testing LMT → LAT → LMT (should return to original)\n\n";

    $jd_lmt_original = 2460310.5; // 2024-01-01 12:00 LMT
    $longitudes = [0.0, 15.0, -75.0, 120.0, 180.0, -180.0];

    $max_error = 0.0;

    foreach ($longitudes as $geolon) {
        // Forward: LMT → LAT
        $tjd_lat = 0.0;
        $serr1 = null;
        $ret1 = swe_lmt_to_lat($jd_lmt_original, $geolon, $tjd_lat, $serr1);

        // Backward: LAT → LMT
        $tjd_lmt_back = 0.0;
        $serr2 = null;
        $ret2 = swe_lat_to_lmt($tjd_lat, $geolon, $tjd_lmt_back, $serr2);

        if ($ret1 === Constants::SE_OK && $ret2 === Constants::SE_OK) {
            $error = abs($tjd_lmt_back - $jd_lmt_original);
            $error_seconds = $error * 24 * 3600; // Error in seconds

            $status = ($error_seconds < 0.01) ? "✓" : "✗";
            printf("%s Lon %+7.1f°: LMT %.6f → LAT %.6f → LMT %.6f (error: %.6f sec)\n",
                $status, $geolon, $jd_lmt_original, $tjd_lat, $tjd_lmt_back, $error_seconds);

            $max_error = max($max_error, $error_seconds);
        } else {
            echo "✗ Lon $geolon° failed\n";
        }
    }

    echo "\nMaximum round-trip error: " . sprintf("%.6f", $max_error) . " seconds\n\n";
}

function test_comparison_with_equation_of_time(): void
{
    echo "=== Testing Relationship with Equation of Time ===\n\n";

    echo "LAT - LMT should equal equation of time\n\n";

    $tjd_ut = 2460310.0; // 2024-01-01 12:00 UT
    $geolon = 0.0; // Greenwich

    // Get equation of time
    $E = 0.0;
    $serr = null;
    swe_time_equ($tjd_ut, $E, $serr);

    // Create LMT at Greenwich
    $tjd_lmt = $tjd_ut; // At Greenwich, LMT ≈ UT

    // Convert to LAT
    $tjd_lat = 0.0;
    swe_lmt_to_lat($tjd_lmt, $geolon, $tjd_lat, $serr);

    $diff = $tjd_lat - $tjd_lmt;
    $E_minutes = $E * 24 * 60; // Equation of time in minutes
    $diff_minutes = $diff * 24 * 60; // Difference in minutes

    echo "Equation of time: " . sprintf("%+.4f", $E) . " days (" . sprintf("%+.2f", $E_minutes) . " min)\n";
    echo "LAT - LMT:        " . sprintf("%+.4f", $diff) . " days (" . sprintf("%+.2f", $diff_minutes) . " min)\n";

    $error_seconds = abs($diff - $E) * 24 * 3600;

    if ($error_seconds < 0.01) {
        echo "✓ Match! (error: " . sprintf("%.6f", $error_seconds) . " sec)\n";
    } else {
        echo "✗ Mismatch! (error: " . sprintf("%.2f", $error_seconds) . " sec)\n";
    }

    echo "\n";
}

function test_different_dates(): void
{
    echo "=== Testing Different Dates (Equation of Time Variation) ===\n\n";

    $dates = [
        ['jd' => 2460310.0, 'name' => '2024-01-01 (winter)'],
        ['jd' => 2460401.0, 'name' => '2024-04-02 (spring)'],
        ['jd' => 2460492.0, 'name' => '2024-07-02 (summer)'],
        ['jd' => 2460583.0, 'name' => '2024-10-01 (autumn)'],
    ];

    $geolon = 0.0; // Greenwich

    echo "Equation of time varies throughout the year\n\n";

    foreach ($dates as $date) {
        $tjd_ut = $date['jd'];
        $name = $date['name'];

        // Get equation of time
        $E = 0.0;
        $serr = null;
        swe_time_equ($tjd_ut, $E, $serr);

        // Test LMT → LAT conversion
        $tjd_lmt = $tjd_ut;
        $tjd_lat = 0.0;
        swe_lmt_to_lat($tjd_lmt, $geolon, $tjd_lat, $serr);

        $diff = ($tjd_lat - $tjd_lmt) * 24 * 60; // minutes

        printf("%-25s Equ. of time: %+.2f min, LMT→LAT diff: %+.2f min\n",
            $name, $E * 24 * 60, $diff);
    }

    echo "\n";
}

// Run all tests
try {
    test_lmt_to_lat();
    test_lat_to_lmt();
    test_round_trip();
    test_comparison_with_equation_of_time();
    test_different_dates();

    echo "✓ All LMT/LAT conversion tests completed successfully!\n";
} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
