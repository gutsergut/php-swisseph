<?php

/**
 * Cross-check ayanamsha calculations against swetest64.exe
 */

require_once __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$swetest = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'swetest64.exe';

if (!file_exists($swetest)) {
    echo "ERROR: swetest64.exe not found in tests directory\n";
    echo "Please copy it from с-swisseph/swisseph/windows/programs/\n";
    exit(1);
}

echo "=== Ayanamsha Parity Test with swetest64.exe ===\n\n";

// Test dates (use integer JD for cmd.exe compatibility)
$test_dates = [
    ['jd' => 2451545, 'jd_ut' => 2451544.5, 'label' => 'J2000.0 (2000-01-01 12:00 TT)'],
    ['jd' => 2433283, 'jd_ut' => 2433282.5, 'label' => 'B1950 (1950-01-01 12:00 TT)'],
    ['jd' => 2460681, 'jd_ut' => 2460680.5, 'label' => '2025-10-26 12:00 TT)'],
];

// Test modes
$test_modes = [
    0 => 'Fagan/Bradley',
    1 => 'Lahiri',
    2 => 'De Luce',
    3 => 'Raman',
    5 => 'Krishnamurti',
    18 => 'J2000',
    19 => 'J1900',
    20 => 'B1950',
    43 => 'Lahiri 1940',
];

foreach ($test_dates as $test_date) {
    echo "\n--- {$test_date['label']} ---\n";
    echo str_repeat('-', 80) . "\n";
    echo sprintf("%-25s  %15s  %15s  %10s\n", "Mode", "swetest", "PHP", "Diff (arcsec)");
    echo str_repeat('-', 80) . "\n";

    foreach ($test_modes as $mode => $name) {
        // Get value from swetest using integer JD format (for cmd.exe compatibility)
        $jd_int = $test_date['jd'];
        $cmd = "cmd /c \"{$swetest}\" -bj{$jd_int} -p -ay{$mode} -head 2>&1";
        $output = shell_exec($cmd);

        $swetest_value = null;
        if ($output) {
            // Parse output format: "Ayanamsha Lahiri    23°51'11.4022" or "-0° 0'13.9315" or "359°18'2.7015"
            // Regex: optional negative sign (with optional space), degrees, minutes, seconds
            if (preg_match('/(-?\s*\d+)°\s*(\d+)\'\s*([\d\.]+)"?/', $output, $matches)) {
                $deg = (float)str_replace(' ', '', $matches[1]); // remove spaces for "- 0" or "-0"
                $min = (float)$matches[2];
                $sec = (float)$matches[3];
                $sign = $deg < 0 ? -1 : 1;
                $abs_value = abs($deg) + $min / 60.0 + $sec / 3600.0;
                $swetest_value = $sign * $abs_value;

                // Handle values near 360° (e.g., 359°18' = -0°42')
                // Normalize to [-180, 180] range for comparison
                while ($swetest_value > 180.0) {
                    $swetest_value -= 360.0;
                }
                while ($swetest_value < -180.0) {
                    $swetest_value += 360.0;
                }
            } elseif (preg_match('/ayanamsa\s+([\d\.\-]+)/', $output, $matches)) {
                $swetest_value = (float)$matches[1];
            }
        }

        // Get value from our PHP implementation
        swe_set_sid_mode($mode, 0, 0);
        $jd_ut = $test_date['jd_ut'];
        $daya = null;
        $serr = null;
        swe_get_ayanamsa_ex_ut($jd_ut, 0, $daya, $serr);

        if ($swetest_value !== null && $daya !== null) {
            $diff_deg = abs($daya - $swetest_value);
            $diff_arcsec = $diff_deg * 3600.0;

            // Format output
            $status = $diff_arcsec < 1.0 ? '✓' : ($diff_arcsec < 10.0 ? '~' : '✗');
            echo sprintf(
                "%-25s  %15.8f  %15.8f  %10.2f %s\n",
                $name,
                $swetest_value,
                $daya,
                $diff_arcsec,
                $status
            );
        } else {
            echo sprintf("%-25s  ERROR (swetest: %s)\n", $name, $swetest_value === null ? 'parse failed' : 'ok');
        }
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Legend: ✓ = good (<1\"), ~ = acceptable (<10\"), ✗ = poor (>=10\")\n";
echo "Note: Comparison uses integer JD for swetest compatibility (±0.5 day precision)\n";
echo "=== Test completed ===\n";
