<?php
/**
 * Random Date Testing Script
 *
 * Tests PHP Swiss Ephemeris against swetest64.exe reference
 * with random dates from 1900 to 2100.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

// swetest64 path
$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';

// Configuration
$numTests = 20;  // Number of random dates to test
$tolerance = 1.0; // arcseconds tolerance

// Planets to test
$planets = [
    Constants::SE_SUN => 'Sun',
    Constants::SE_MOON => 'Moon',
    Constants::SE_MERCURY => 'Mercury',
    Constants::SE_VENUS => 'Venus',
    Constants::SE_MARS => 'Mars',
    Constants::SE_JUPITER => 'Jupiter',
    Constants::SE_SATURN => 'Saturn',
];

// Generate random dates
function randomDate(int $startYear, int $endYear): array {
    $year = rand($startYear, $endYear);
    $month = rand(1, 12);
    $maxDay = match($month) {
        2 => ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0)) ? 29 : 28,
        4, 6, 9, 11 => 30,
        default => 31
    };
    $day = rand(1, $maxDay);
    $hour = rand(0, 23);
    $minute = rand(0, 59);

    return [
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'hour' => $hour,
        'minute' => $minute,
        'dateStr' => sprintf("%d.%d.%d", $day, $month, $year),
        'timeStr' => sprintf("%02d:%02d", $hour, $minute),
    ];
}

// Get reference from swetest64 using cmd /c wrapper
function getSwetestReference(string $swetest, string $ephePath, string $date, string $time, int $planetCode): ?float {
    $planetChar = match($planetCode) {
        0 => '0',  // Sun
        1 => '1',  // Moon
        2 => '2',  // Mercury
        3 => '3',  // Venus
        4 => '4',  // Mars
        5 => '5',  // Jupiter
        6 => '6',  // Saturn
        default => (string)$planetCode
    };

    // Use cmd /c wrapper as recommended in SWETEST64-POWERSHELL.md
    // Format: cmd /c ""path\swetest64.exe" -bD.M.Y -utHH:MM:SS -pX -fPl -head -eswe -edir<path>"
    $cmd = sprintf(
        'cmd /c ""%s" -b%s -ut%s:00 -p%s -fPl -head -eswe -edir%s"',
        $swetest,
        $date,
        $time,
        $planetChar,
        $ephePath
    );

    $output = [];
    exec($cmd, $output, $returnCode);

    if (empty($output)) {
        return null;
    }

    // Parse longitude from output - format: "Sun   longitude_value"
    foreach ($output as $line) {
        // Match planet name followed by longitude value
        if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
            return (float)$matches[1];
        }
    }

    return null;
}

// Run tests
echo "=== Random Date Testing (1900-2100) ===\n";
echo "Testing $numTests random dates with tolerance $tolerance arcsec\n\n";

$errors = [];
$maxError = 0;
$maxErrorInfo = null;
$testedCount = 0;
$passedCount = 0;

// Seed for reproducibility (comment out for true random)
srand(12345);

for ($i = 0; $i < $numTests; $i++) {
    $date = randomDate(1900, 2100);
    $jd = swe_julday($date['year'], $date['month'], $date['day'],
                     $date['hour'] + $date['minute']/60.0, 1);

    echo sprintf("Test %d: %s %s (JD %.2f)\n", $i + 1, $date['dateStr'], $date['timeStr'], $jd);

    foreach ($planets as $ipl => $name) {
        $testedCount++;

        // Get PHP result
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $ipl, Constants::SEFLG_SPEED, $xx, $serr);

        if ($ret < 0) {
            echo "  $name: PHP Error - $serr\n";
            continue;
        }

        $phpLon = $xx[0];

        // Get swetest reference (use absolute path for -edir)
        $absEphePath = realpath($ephePath);
        $refLon = getSwetestReference($swetest, $absEphePath, $date['dateStr'], $date['timeStr'], $ipl);

        if ($refLon === null) {
            echo "  $name: Could not get swetest reference\n";
            continue;
        }

        // Calculate difference
        $diff = $phpLon - $refLon;
        // Normalize to [-180, 180]
        while ($diff > 180) $diff -= 360;
        while ($diff < -180) $diff += 360;

        $diffArcsec = abs($diff) * 3600;

        if ($diffArcsec > $tolerance) {
            $errors[] = [
                'date' => $date,
                'jd' => $jd,
                'planet' => $name,
                'php' => $phpLon,
                'ref' => $refLon,
                'diff' => $diffArcsec,
            ];
            echo sprintf("  %s: FAIL lon=%.7f (ref=%.7f, diff=%.3f\")\n",
                        $name, $phpLon, $refLon, $diffArcsec);
        } else {
            $passedCount++;
            echo sprintf("  %s: OK   lon=%.7f (diff=%.3f\")\n", $name, $phpLon, $diffArcsec);
        }

        if ($diffArcsec > $maxError) {
            $maxError = $diffArcsec;
            $maxErrorInfo = [
                'date' => $date,
                'jd' => $jd,
                'planet' => $name,
                'php' => $phpLon,
                'ref' => $refLon,
            ];
        }
    }

    echo "\n";
}

// Summary
echo "\n=== Summary ===\n";
echo "Tests: $testedCount, Passed: $passedCount, Failed: " . count($errors) . "\n";
echo "Max error: " . sprintf("%.3f", $maxError) . " arcsec\n";

if ($maxErrorInfo) {
    echo "Worst case: {$maxErrorInfo['planet']} on {$maxErrorInfo['date']['dateStr']}\n";
    echo "  PHP: {$maxErrorInfo['php']}, Reference: {$maxErrorInfo['ref']}\n";
}

if (count($errors) > 0) {
    echo "\n=== Errors Detail ===\n";
    foreach ($errors as $err) {
        echo sprintf("%s on %s %s: PHP=%.7f, Ref=%.7f, Diff=%.3f\"\n",
            $err['planet'], $err['date']['dateStr'], $err['date']['timeStr'],
            $err['php'], $err['ref'], $err['diff']);
    }
}

echo "\n" . ($passedCount === $testedCount ? "✓ ALL TESTS PASSED!" : "✗ SOME TESTS FAILED") . "\n";
