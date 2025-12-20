<?php
/**
 * Debug script to analyze precision errors for dates far from J2000
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';
$absEphePath = realpath($ephePath);

// Test cases: dates at different distances from J2000 (2000.0)
$testCases = [
    ['year' => 2000, 'month' => 1, 'day' => 1, 'hour' => 12, 'desc' => 'J2000.0 (reference)'],
    ['year' => 2010, 'month' => 6, 'day' => 15, 'hour' => 12, 'desc' => '10 years after'],
    ['year' => 2025, 'month' => 12, 'day' => 20, 'hour' => 12, 'desc' => '25 years after (today)'],
    ['year' => 2050, 'month' => 6, 'day' => 15, 'hour' => 12, 'desc' => '50 years after'],
    ['year' => 2100, 'month' => 1, 'day' => 1, 'hour' => 12, 'desc' => '100 years after'],
    ['year' => 1950, 'month' => 6, 'day' => 15, 'hour' => 12, 'desc' => '50 years before'],
    ['year' => 1900, 'month' => 1, 'day' => 1, 'hour' => 12, 'desc' => '100 years before'],
];

function getSwetestLon(string $swetest, string $ephePath, string $date, string $time, int $planetCode): ?float {
    $cmd = sprintf(
        'cmd /c ""%s" -b%s -ut%s -p%d -fPl -head -eswe -edir%s"',
        $swetest, $date, $time, $planetCode, $ephePath
    );

    exec($cmd, $output, $returnCode);

    foreach ($output as $line) {
        if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
            return (float)$matches[1];
        }
    }
    return null;
}

echo "=== Precision Analysis by Distance from J2000 ===\n\n";
echo "Testing Sun, Moon and Mercury\n\n";

foreach ($testCases as $tc) {
    $jd = swe_julday($tc['year'], $tc['month'], $tc['day'], $tc['hour'], 1);
    $dateStr = sprintf("%d.%d.%d", $tc['day'], $tc['month'], $tc['year']);
    $timeStr = sprintf("%02d:00:00", $tc['hour']);

    echo sprintf("=== %s: %s (JD %.2f) ===\n", $tc['desc'], $dateStr, $jd);

    $planets = [
        Constants::SE_SUN => 'Sun',
        Constants::SE_MOON => 'Moon',
        Constants::SE_MERCURY => 'Mercury',
    ];

    foreach ($planets as $ipl => $name) {
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $ipl, Constants::SEFLG_SPEED, $xx, $serr);

        if ($ret < 0) {
            echo "  $name: PHP Error - $serr\n";
            continue;
        }

        $phpLon = $xx[0];
        $refLon = getSwetestLon($swetest, $absEphePath, $dateStr, $timeStr, $ipl);

        if ($refLon === null) {
            echo "  $name: Could not get reference\n";
            continue;
        }

        $diff = $phpLon - $refLon;
        while ($diff > 180) $diff -= 360;
        while ($diff < -180) $diff += 360;
        $diffArcsec = abs($diff) * 3600;

        $status = $diffArcsec < 1.0 ? "OK" : ($diffArcsec < 5.0 ? "WARN" : "FAIL");
        echo sprintf("  %s: PHP=%.7f, Ref=%.7f, Diff=%.3f\" [%s]\n",
            $name, $phpLon, $refLon, $diffArcsec, $status);
    }
    echo "\n";
}

// Check what ephemeris source is used
echo "=== Ephemeris Source Check ===\n";
$xx = [];
$serr = '';
$jd2000 = 2451545.0;
$ret = swe_calc($jd2000, Constants::SE_SUN, Constants::SEFLG_SPEED, $xx, $serr);
echo "Sun at J2000: lon={$xx[0]}, lat={$xx[1]}, dist={$xx[2]}\n";
echo "serr: " . ($serr ?: "(none)") . "\n";
echo "return: $ret\n";

// Get current file data
$fileData = swe_get_current_file_data(0); // planet file
if ($fileData) {
    echo "Planet file: {$fileData['path']}\n";
    echo "JD range: {$fileData['tfstart']} - {$fileData['tfend']}\n";
    echo "DE number: {$fileData['denum']}\n";
} else {
    echo "No planet file loaded (using Moshier?)\n";
}
