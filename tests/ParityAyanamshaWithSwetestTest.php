<?php

require_once __DIR__ . '/bootstrap.php';

use Swisseph\Constants;

$swetest =
    'c:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\' .
    'с-swisseph\\swisseph\\windows\\programs\\swetest64.exe';

echo "=== Ayanamsha Parity Test with swetest64.exe ===\n\n";

$test_dates = [
    ['date' => '1.1.2000', 'jd_ut' => 2451544.5, 'label' => 'J2000.0 (2000-01-01 00:00 UT)'],
    ['date' => '1.1.1950', 'jd_ut' => 2433282.5, 'label' => 'B1950 (1950-01-01 00:00 UT)'],
    ['date' => '26.10.2025', 'jd_ut' => 2460680.5, 'label' => '2025-10-26 00:00 UT'],
];

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
        $cmd = "\"{$swetest}\" -b{$test_date['date']} -ut -p -ay{$mode} -fPl -head";
        $output = [];
        exec($cmd, $output);

        $swetest_value = null;
        if (!empty($output)) {
            $line = trim($output[0]);
            if (preg_match('/ayanamsa\s+([\d\.\-]+)/', $line, $matches)) {
                $swetest_value = (float) $matches[1];
            }
        }

        swe_set_sid_mode($mode, 0, 0);
        $jd_ut = $test_date['jd_ut'];
        $daya = null;
        $serr = null;
        swe_get_ayanamsa_ex_ut($jd_ut, 0, $daya, $serr);

        if ($swetest_value !== null && $daya !== null) {
            $diff_deg = abs($daya - $swetest_value);
            $diff_arcsec = $diff_deg * 3600.0;
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
            echo sprintf("%-25s  ERROR\n", $name);
        }
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Legend: ✓ = good (<1\"), ~ = acceptable (<10\"), ✗ = poor (>=10\")\n";
echo "=== Test completed ===\n";
