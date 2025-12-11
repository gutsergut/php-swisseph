<?php

declare(strict_types=1);

/**
 * Quick test for swe_houses_ex function.
 * Validates that swe_houses_ex produces same results as swe_houses_ex2 (except speeds).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

function test_swe_houses_ex(): void
{
    echo "=== Testing swe_houses_ex() ===\n\n";

    // Test parameters: London, 2024-01-01 12:00 UT
    $jd_ut = 2460310.0; // 2024-01-01 12:00 UT
    $geolat = 51.5074;  // London latitude
    $geolon = -0.1278;  // London longitude
    $iflag = Constants::SEFLG_SWIEPH;

    $house_systems = [
        'P' => 'Placidus',
        'K' => 'Koch',
        'O' => 'Porphyrius',
        'R' => 'Regiomontanus',
        'C' => 'Campanus',
        'E' => 'Equal',
        'W' => 'Whole Sign',
        'B' => 'Alcabitus',
    ];

    echo "Testing houses_ex vs houses_ex2 (should match except speeds)\n";
    echo "Location: London (51.51°N, 0.13°W)\n";
    echo "Date: 2024-01-01 12:00 UT (JD $jd_ut)\n\n";

    foreach ($house_systems as $hsys => $name) {
        echo "--- $name ($hsys) ---\n";

        // Call swe_houses_ex
        $cusp1 = [];
        $ascmc1 = [];
        $ret1 = swe_houses_ex($jd_ut, $iflag, $geolat, $geolon, $hsys, $cusp1, $ascmc1);

        // Call swe_houses_ex2
        $cusp2 = [];
        $ascmc2 = [];
        $cusp_speed2 = [];
        $ascmc_speed2 = [];
        $serr2 = null;
        $ret2 = swe_houses_ex2(
            $jd_ut, $iflag, $geolat, $geolon, $hsys,
            $cusp2, $ascmc2, $cusp_speed2, $ascmc_speed2, $serr2
        );

        // Validate return codes
        if ($ret1 !== $ret2) {
            echo "✗ Return codes differ: ex=$ret1, ex2=$ret2\n";
            continue;
        }

        if ($ret1 !== Constants::SE_OK) {
            echo "✗ Error: $serr2\n";
            continue;
        }

        // Compare cusps
        $cusp_match = true;
        for ($i = 1; $i <= 12; $i++) {
            $diff = abs($cusp1[$i] - $cusp2[$i]);
            if ($diff > 0.0001) {
                echo "✗ Cusp $i differs: ex={$cusp1[$i]}, ex2={$cusp2[$i]}, diff=$diff\n";
                $cusp_match = false;
            }
        }

        // Compare ascmc
        $ascmc_match = true;
        for ($i = 0; $i < 10; $i++) {
            $diff = abs($ascmc1[$i] - $ascmc2[$i]);
            if ($diff > 0.0001) {
                $ascmc_names = ['Asc', 'MC', 'ARMC', 'Vertex', 'EqAsc', 'CoAsc1', 'CoAsc2', 'PolarAsc', '[8]', '[9]'];
                echo "✗ ASCMC[$i] ({$ascmc_names[$i]}) differs: ex={$ascmc1[$i]}, ex2={$ascmc2[$i]}, diff=$diff\n";
                $ascmc_match = false;
            }
        }

        if ($cusp_match && $ascmc_match) {
            printf("✓ Houses match perfectly\n");
            printf("  Asc: %7.3f°, MC: %7.3f°, Cusp[1]: %7.3f°\n",
                $ascmc1[0], $ascmc1[1], $cusp1[1]);
        }

        echo "\n";
    }
}

function test_swe_houses_ex_with_flags(): void
{
    echo "=== Testing swe_houses_ex() with different flags ===\n\n";

    $jd_ut = 2460310.0;
    $geolat = 51.5074;
    $geolon = -0.1278;
    $hsys = 'P'; // Placidus

    $test_flags = [
        ['flag' => Constants::SEFLG_SWIEPH, 'name' => 'SWIEPH (default)'],
        ['flag' => Constants::SEFLG_SWIEPH | Constants::SEFLG_NONUT, 'name' => 'SWIEPH + NONUT'],
        ['flag' => Constants::SEFLG_SIDEREAL, 'name' => 'SIDEREAL (Lahiri)'],
    ];

    // Set sidereal mode for sidereal test
    swe_set_sid_mode(Constants::SE_SIDM_LAHIRI, 0, 0);

    echo "Testing Placidus houses with different ephemeris flags\n\n";

    foreach ($test_flags as $test) {
        $iflag = $test['flag'];
        $name = $test['name'];

        $cusp = [];
        $ascmc = [];
        $ret = swe_houses_ex($jd_ut, $iflag, $geolat, $geolon, $hsys, $cusp, $ascmc);

        if ($ret === Constants::SE_OK) {
            printf("✓ %-25s Asc: %7.3f°, MC: %7.3f°, H1: %7.3f°\n",
                $name, $ascmc[0], $ascmc[1], $cusp[1]);
        } else {
            echo "✗ $name failed\n";
        }
    }

    echo "\n";
}

function test_comparison_with_basic_houses(): void
{
    echo "=== Testing swe_houses_ex vs swe_houses (basic) ===\n\n";

    $jd_ut = 2460310.0;
    $geolat = 51.5074;
    $geolon = -0.1278;
    $hsys = 'P';

    // Call basic swe_houses (no iflag)
    $cusp_basic = [];
    $ascmc_basic = [];
    $ret_basic = swe_houses($jd_ut, $geolat, $geolon, $hsys, $cusp_basic, $ascmc_basic);

    // Call swe_houses_ex with iflag=0
    $cusp_ex = [];
    $ascmc_ex = [];
    $ret_ex = swe_houses_ex($jd_ut, 0, $geolat, $geolon, $hsys, $cusp_ex, $ascmc_ex);

    echo "Comparing swe_houses() vs swe_houses_ex(iflag=0)\n";
    echo "Both should produce identical results\n\n";

    if ($ret_basic === $ret_ex && $ret_basic === Constants::SE_OK) {
        $match = true;

        for ($i = 1; $i <= 12; $i++) {
            $diff = abs($cusp_basic[$i] - $cusp_ex[$i]);
            if ($diff > 0.0001) {
                echo "✗ Cusp $i differs: basic={$cusp_basic[$i]}, ex={$cusp_ex[$i]}\n";
                $match = false;
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $diff = abs($ascmc_basic[$i] - $ascmc_ex[$i]);
            if ($diff > 0.0001) {
                echo "✗ ASCMC[$i] differs: basic={$ascmc_basic[$i]}, ex={$ascmc_ex[$i]}\n";
                $match = false;
            }
        }

        if ($match) {
            echo "✓ swe_houses and swe_houses_ex(iflag=0) produce identical results\n";
            printf("  Asc: %7.3f°, MC: %7.3f°\n", $ascmc_basic[0], $ascmc_basic[1]);
        }
    } else {
        echo "✗ Return codes differ or error occurred\n";
    }

    echo "\n";
}

// Run all tests
try {
    test_swe_houses_ex();
    test_swe_houses_ex_with_flags();
    test_comparison_with_basic_houses();

    echo "✓ All swe_houses_ex tests completed successfully!\n";
} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
