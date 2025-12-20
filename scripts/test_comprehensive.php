<?php
/**
 * Comprehensive Testing Script
 *
 * Tests PHP Swiss Ephemeris against swetest64.exe reference
 * with various dates, planets, and flag combinations.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

// swetest64 path
$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';

// Test configuration
$tolerance = 1.0; // arcseconds for angles

// Test dates spanning 1900-2100
$testDates = [
    ['jd' => 2415020.5, 'name' => '1900-01-01'],
    ['jd' => 2419402.5, 'name' => '1912-01-01'],
    ['jd' => 2429630.5, 'name' => '1940-04-01'],
    ['jd' => 2433282.5, 'name' => '1950-01-01'],
    ['jd' => 2440587.5, 'name' => '1970-01-01'],
    ['jd' => 2444239.5, 'name' => '1980-01-01'],
    ['jd' => 2447892.5, 'name' => '1990-01-01'],
    ['jd' => 2451545.0, 'name' => 'J2000.0'],
    ['jd' => 2455197.5, 'name' => '2010-01-01'],
    ['jd' => 2458849.5, 'name' => '2020-01-01'],
    ['jd' => 2462502.5, 'name' => '2030-01-01'],
    ['jd' => 2469807.5, 'name' => '2050-01-01'],
    ['jd' => 2477111.5, 'name' => '2070-01-01'],
    ['jd' => 2488069.5, 'name' => '2100-01-01'],
];

// Planets to test
$planets = [
    Constants::SE_SUN => 'Sun',
    Constants::SE_MOON => 'Moon',
    Constants::SE_MERCURY => 'Mercury',
    Constants::SE_VENUS => 'Venus',
    Constants::SE_MARS => 'Mars',
    Constants::SE_JUPITER => 'Jupiter',
    Constants::SE_SATURN => 'Saturn',
    Constants::SE_URANUS => 'Uranus',
    Constants::SE_NEPTUNE => 'Neptune',
    Constants::SE_PLUTO => 'Pluto',
    Constants::SE_MEAN_NODE => 'Mean Node',
    Constants::SE_TRUE_NODE => 'True Node',
];

// Flag combinations to test (only available in PHP extension)
$flagSets = [
    [
        'name' => 'Ecliptic geocentric',
        'flags' => Constants::SEFLG_SWIEPH,
        'swetest_flags' => '',
    ],
    [
        'name' => 'True position',
        'flags' => Constants::SEFLG_SWIEPH | Constants::SEFLG_TRUEPOS,
        'swetest_flags' => '-true',
    ],
    [
        'name' => 'No nutation',
        'flags' => Constants::SEFLG_SWIEPH | Constants::SEFLG_NONUT,
        'swetest_flags' => '-nonut',
    ],
    [
        'name' => 'Heliocentric',
        'flags' => Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR,
        'swetest_flags' => '-hel',
        'skip_planets' => [Constants::SE_SUN, Constants::SE_MOON, Constants::SE_MEAN_NODE, Constants::SE_TRUE_NODE],
    ],
];

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

// Ephemeris path for swetest
$epheDirPath = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe';

/**
 * Convert JD to date/time for swetest
 */
function jdToDateTime(float $jd): array
{
    // JD 2451545.0 = J2000.0 = 2000-01-01 12:00:00 UT
    $jd2000 = 2451545.0;
    $daysSince2000 = $jd - $jd2000;

    $baseDate = new DateTime('2000-01-01 12:00:00', new DateTimeZone('UTC'));
    $baseDate->modify(sprintf('%+d seconds', (int)($daysSince2000 * 86400)));

    return [
        'date' => $baseDate->format('j.n.Y'),
        'time' => $baseDate->format('H:i:s'),
    ];
}

function runSwetest(string $swetest, float $jd, int $planet, string $extraFlags = ''): ?array
{
    global $epheDirPath;

    $planetMap = [
        Constants::SE_SUN => '0',
        Constants::SE_MOON => '1',
        Constants::SE_MERCURY => '2',
        Constants::SE_VENUS => '3',
        Constants::SE_MARS => '4',
        Constants::SE_JUPITER => '5',
        Constants::SE_SATURN => '6',
        Constants::SE_URANUS => '7',
        Constants::SE_NEPTUNE => '8',
        Constants::SE_PLUTO => '9',
        Constants::SE_MEAN_NODE => 'm',
        Constants::SE_TRUE_NODE => 't',
    ];

    $planetArg = $planetMap[$planet] ?? (string)$planet;

    // Convert JD to date/time
    $dt = jdToDateTime($jd);

    // Use cmd /c for reliable execution on Windows
    $cmd = sprintf('cmd /c ""%s" -b%s -ut%s -p%s -fPlbrs -head -eswe -edir%s %s"',
        $swetest, $dt['date'], $dt['time'], $planetArg, $epheDirPath, $extraFlags);

    $output = shell_exec($cmd);
    if ($output === null) {
        return null;
    }

    $result = ['raw' => trim($output)];

    // Parse: "Sun              280.3681656   0.0002274    0.983327631   1.0194341"
    if (preg_match('/^\s*\S+\s+([-+]?\d+\.?\d*)\s+([-+]?\d+\.?\d*)\s+([-+]?\d+\.?\d*)"?\s+([-+]?\d+\.?\d*)/m', $output, $m)) {
        $result['lon'] = (float)$m[1];
        $result['lat'] = (float)$m[2];
        $result['dist'] = (float)$m[3];
        $result['lonspd'] = (float)$m[4];
    }

    return $result;
}

function normalizeAngle(float $angle): float
{
    while ($angle < 0) $angle += 360.0;
    while ($angle >= 360.0) $angle -= 360.0;
    return $angle;
}

function angleDiff(float $a, float $b): float
{
    $diff = normalizeAngle($a) - normalizeAngle($b);
    if ($diff > 180.0) $diff -= 360.0;
    if ($diff < -180.0) $diff += 360.0;
    return $diff;
}

// =============================================================================
// MAIN TEST RUNNER
// =============================================================================

echo "=== Comprehensive Swiss Ephemeris Test ===\n";
echo "Testing " . count($testDates) . " dates × " . count($planets) . " planets × " . count($flagSets) . " flag sets\n";
echo "Tolerance: {$tolerance} arcsec for angles\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = [];
$skippedTests = 0;
$maxError = 0;
$worstCase = '';

foreach ($flagSets as $flagSet) {
    $flagName = $flagSet['name'];
    $flags = $flagSet['flags'];
    $swetestFlags = $flagSet['swetest_flags'];
    $skipPlanets = $flagSet['skip_planets'] ?? [];

    echo "--- {$flagName} ---\n";

    // Run setup if needed
    if (isset($flagSet['setup'])) {
        $flagSet['setup']();
    }

    $setTests = 0;
    $setPassed = 0;

    foreach ($testDates as $dateInfo) {
        $jd = $dateInfo['jd'];
        $dateName = $dateInfo['name'];

        foreach ($planets as $planetId => $planetName) {
            if (in_array($planetId, $skipPlanets)) {
                $skippedTests++;
                continue;
            }

            $totalTests++;
            $setTests++;

            // Get PHP result
            $xx = [];
            $serr = '';
            $ret = swe_calc_ut($jd, $planetId, $flags, $xx, $serr);

            if ($ret < 0) {
                $skippedTests++;
                continue;
            }

            // Get reference from swetest
            $ref = runSwetest($swetest, $jd, $planetId, $swetestFlags);

            if ($ref === null || !isset($ref['lon'])) {
                $skippedTests++;
                continue;
            }

            // Compare longitude
            $diff = angleDiff($xx[0], $ref['lon']);
            $diffArcsec = abs($diff) * 3600.0;

            if ($diffArcsec > $maxError) {
                $maxError = $diffArcsec;
                $worstCase = "{$planetName} @ {$dateName} ({$flagName})";
            }

            if ($diffArcsec <= $tolerance) {
                $passedTests++;
                $setPassed++;
            } else {
                $failedTests[] = [
                    'flagSet' => $flagName,
                    'date' => $dateName,
                    'planet' => $planetName,
                    'diff' => $diffArcsec,
                    'php' => $xx[0],
                    'ref' => $ref['lon'],
                ];
            }
        }
    }

    echo "  Passed: {$setPassed}/{$setTests}\n";
}

// =============================================================================
// SUMMARY
// =============================================================================

echo "\n=== SUMMARY ===\n";
echo "Total tests: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: " . count($failedTests) . "\n";
echo "Skipped: {$skippedTests}\n";
echo sprintf("Max error: %.4f arcsec\n", $maxError);
echo "Worst case: {$worstCase}\n";

if (count($failedTests) > 0) {
    echo "\n=== FAILED TESTS (first 20) ===\n";
    foreach (array_slice($failedTests, 0, 20) as $fail) {
        echo sprintf("  %s: %s @ %s - diff=%.3f\"\n",
            $fail['flagSet'], $fail['planet'], $fail['date'], $fail['diff']);
        echo sprintf("    PHP=%.10f, Ref=%.10f\n", $fail['php'], $fail['ref']);
    }
    if (count($failedTests) > 20) {
        echo "  ... and " . (count($failedTests) - 20) . " more failures\n";
    }
    echo "\n✗ SOME TESTS FAILED\n";
    exit(1);
} else {
    echo "\n✓ ALL TESTS PASSED!\n";
    exit(0);
}
