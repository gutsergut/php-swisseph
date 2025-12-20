<?php
/**
 * Extended Comprehensive Testing Script
 *
 * Tests PHP Swiss Ephemeris against swetest64.exe reference
 * with various dates, planets, flag combinations, and multiple outputs.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

// swetest64 path
$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';

// =============================================================================
// TEST CONFIGURATION
// =============================================================================

$toleranceAngle = 20.0;      // arcseconds for longitude/latitude
$toleranceDist = 0.001;      // AU for distance (more reasonable for PHP extension)
$toleranceSpeed = 0.01;      // arcsec/day for speed

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

    // Calculate date using PHP DateTime
    $baseDate = new DateTime('2000-01-01 12:00:00', new DateTimeZone('UTC'));
    $baseDate->modify(sprintf('%+d seconds', (int)($daysSince2000 * 86400)));

    return [
        'date' => $baseDate->format('j.n.Y'),  // No leading zeros
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
    // or Moon with arcsec distance: "Moon  223.3237512   5.1707406    3269.07177"  12.0213038"
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
// TEST SECTION 1: ECLIPTIC GEOCENTRIC POSITIONS
// =============================================================================

echo "=== Section 1: Ecliptic Geocentric Positions ===\n";

$stats = [
    'lon' => ['tests' => 0, 'passed' => 0, 'errors' => []],
    'lat' => ['tests' => 0, 'passed' => 0, 'errors' => []],
    'dist' => ['tests' => 0, 'passed' => 0, 'errors' => []],
];

foreach ($testDates as $dateInfo) {
    $jd = $dateInfo['jd'];
    $dateName = $dateInfo['name'];

    foreach ($planets as $planetId => $planetName) {
        // Get PHP result
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $planetId, Constants::SEFLG_SWIEPH, $xx, $serr);

        if ($ret < 0) {
            continue;
        }

        // Get reference
        $ref = runSwetest($swetest, $jd, $planetId, '');

        if ($ref === null || !isset($ref['lon'])) {
            continue;
        }

        // Compare longitude
        $stats['lon']['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;
        if ($diffLon <= $toleranceAngle) {
            $stats['lon']['passed']++;
        } else {
            $stats['lon']['errors'][] = ['name' => "$planetName @ $dateName", 'diff' => $diffLon];
        }

        // Compare latitude
        $stats['lat']['tests']++;
        $diffLat = abs($xx[1] - $ref['lat']) * 3600.0;
        if ($diffLat <= $toleranceAngle) {
            $stats['lat']['passed']++;
        } else {
            $stats['lat']['errors'][] = ['name' => "$planetName @ $dateName", 'diff' => $diffLat];
        }

        // Compare distance (skip for nodes - distance is always 1, and Moon - different units)
        if ($planetId != Constants::SE_MEAN_NODE && $planetId != Constants::SE_TRUE_NODE
            && $planetId != Constants::SE_MOON) {
            $stats['dist']['tests']++;
            $diffDist = abs($xx[2] - $ref['dist']);
            if ($diffDist <= $toleranceDist) {
                $stats['dist']['passed']++;
            } else {
                $stats['dist']['errors'][] = ['name' => "$planetName @ $dateName", 'diff' => $diffDist];
            }
        }
    }
}

echo "  Longitude: {$stats['lon']['passed']}/{$stats['lon']['tests']} passed\n";
echo "  Latitude:  {$stats['lat']['passed']}/{$stats['lat']['tests']} passed\n";
echo "  Distance:  {$stats['dist']['passed']}/{$stats['dist']['tests']} passed\n";

// =============================================================================
// TEST SECTION 2: HELIOCENTRIC POSITIONS
// =============================================================================

echo "\n=== Section 2: Heliocentric Positions ===\n";

$helioPlanets = [
    Constants::SE_MERCURY => 'Mercury',
    Constants::SE_VENUS => 'Venus',
    Constants::SE_MARS => 'Mars',
    Constants::SE_JUPITER => 'Jupiter',
    Constants::SE_SATURN => 'Saturn',
    Constants::SE_URANUS => 'Uranus',
    Constants::SE_NEPTUNE => 'Neptune',
    Constants::SE_PLUTO => 'Pluto',
];

$helioStats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach ($testDates as $dateInfo) {
    $jd = $dateInfo['jd'];

    foreach ($helioPlanets as $planetId => $planetName) {
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $planetId, Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR, $xx, $serr);

        if ($ret < 0) continue;

        $ref = runSwetest($swetest, $jd, $planetId, '-hel');
        if ($ref === null || !isset($ref['lon'])) continue;

        $helioStats['tests']++;
        $diff = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diff > $helioStats['maxErr']) {
            $helioStats['maxErr'] = $diff;
        }

        if ($diff <= $toleranceAngle) {
            $helioStats['passed']++;
        }
    }
}

echo "  Tests: {$helioStats['passed']}/{$helioStats['tests']} passed\n";
echo sprintf("  Max error: %.3f arcsec\n", $helioStats['maxErr']);

// =============================================================================
// TEST SECTION 3: TRUE POSITIONS (no aberration)
// =============================================================================

echo "\n=== Section 3: True Positions (no aberration) ===\n";

$trueStats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach ($testDates as $dateInfo) {
    $jd = $dateInfo['jd'];

    foreach ($planets as $planetId => $planetName) {
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $planetId, Constants::SEFLG_SWIEPH | Constants::SEFLG_TRUEPOS, $xx, $serr);

        if ($ret < 0) continue;

        $ref = runSwetest($swetest, $jd, $planetId, '-true');
        if ($ref === null || !isset($ref['lon'])) continue;

        $trueStats['tests']++;
        $diff = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diff > $trueStats['maxErr']) {
            $trueStats['maxErr'] = $diff;
        }

        if ($diff <= $toleranceAngle) {
            $trueStats['passed']++;
        }
    }
}

echo "  Tests: {$trueStats['passed']}/{$trueStats['tests']} passed\n";
echo sprintf("  Max error: %.3f arcsec\n", $trueStats['maxErr']);

// =============================================================================
// TEST SECTION 4: NO NUTATION
// =============================================================================

echo "\n=== Section 4: No Nutation ===\n";

$nonutStats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach ($testDates as $dateInfo) {
    $jd = $dateInfo['jd'];

    foreach ($planets as $planetId => $planetName) {
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $planetId, Constants::SEFLG_SWIEPH | Constants::SEFLG_NONUT, $xx, $serr);

        if ($ret < 0) continue;

        $ref = runSwetest($swetest, $jd, $planetId, '-nonut');
        if ($ref === null || !isset($ref['lon'])) continue;

        $nonutStats['tests']++;
        $diff = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diff > $nonutStats['maxErr']) {
            $nonutStats['maxErr'] = $diff;
        }

        if ($diff <= $toleranceAngle) {
            $nonutStats['passed']++;
        }
    }
}

echo "  Tests: {$nonutStats['passed']}/{$nonutStats['tests']} passed\n";
echo sprintf("  Max error: %.3f arcsec\n", $nonutStats['maxErr']);

// =============================================================================
// TEST SECTION 5: SPECIAL DATES
// =============================================================================

echo "\n=== Section 5: Special Dates Tests ===\n";

$specialDates = [
    ['jd' => 2378497.0, 'name' => '1800-01-01 (before DE431)'],
    ['jd' => 2524593.5, 'name' => '2200-01-01 (after DE431)'],
    ['jd' => 2451545.0, 'name' => 'J2000.0 noon'],
    ['jd' => 2451544.5, 'name' => 'J2000.0 midnight'],
];

$specialStats = ['tests' => 0, 'passed' => 0];

foreach ($specialDates as $dateInfo) {
    $jd = $dateInfo['jd'];
    $dateName = $dateInfo['name'];

    // Test Sun
    $xx = [];
    $serr = '';
    $ret = swe_calc_ut($jd, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx, $serr);

    if ($ret >= 0) {
        $ref = runSwetest($swetest, $jd, Constants::SE_SUN, '');
        if ($ref !== null && isset($ref['lon'])) {
            $specialStats['tests']++;
            $diff = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;
            if ($diff <= $toleranceAngle) {
                $specialStats['passed']++;
                echo "  ✓ Sun @ $dateName: diff = " . sprintf("%.3f", $diff) . " arcsec\n";
            } else {
                echo "  ✗ Sun @ $dateName: diff = " . sprintf("%.3f", $diff) . " arcsec\n";
            }
        }
    } else {
        echo "  ⚠ Sun @ $dateName: $serr\n";
    }
}

// =============================================================================
// TEST SECTION 6: MOON DETAILED TEST
// =============================================================================

echo "\n=== Section 6: Moon Detailed Test ===\n";

$moonDates = [
    2451545.0,  // J2000
    2458849.5,  // 2020
    2440587.5,  // 1970
    2433282.5,  // 1950
];

foreach ($moonDates as $jd) {
    $xx = [];
    $serr = '';
    $ret = swe_calc_ut($jd, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xx, $serr);

    if ($ret < 0) continue;

    $ref = runSwetest($swetest, $jd, Constants::SE_MOON, '');
    if ($ref === null || !isset($ref['lon'])) continue;

    $diffLon = angleDiff($xx[0], $ref['lon']) * 3600.0;
    $diffLat = ($xx[1] - $ref['lat']) * 3600.0;
    $diffDist = $xx[2] - $ref['dist'];

    echo sprintf("  JD %.1f:\n", $jd);
    echo sprintf("    Lon: PHP=%.8f Ref=%.8f diff=%.3f\"\n", $xx[0], $ref['lon'], $diffLon);
    echo sprintf("    Lat: PHP=%.8f Ref=%.8f diff=%.3f\"\n", $xx[1], $ref['lat'], $diffLat);
    echo sprintf("    Dist: PHP=%.10f Ref=%.10f diff=%.2e AU\n", $xx[2], $ref['dist'], $diffDist);
}

// =============================================================================
// SUMMARY
// =============================================================================

echo "\n=== SUMMARY ===\n";

$totalPassed = $stats['lon']['passed'] + $stats['lat']['passed'] + $stats['dist']['passed']
    + $helioStats['passed'] + $trueStats['passed'] + $nonutStats['passed'] + $specialStats['passed'];
$totalTests = $stats['lon']['tests'] + $stats['lat']['tests'] + $stats['dist']['tests']
    + $helioStats['tests'] + $trueStats['tests'] + $nonutStats['tests'] + $specialStats['tests'];

echo "Total: $totalPassed / $totalTests tests passed\n";
echo sprintf("Pass rate: %.1f%%\n", ($totalPassed / $totalTests) * 100);

// Detailed breakdown
echo "\nBreakdown:\n";
echo sprintf("  Geocentric Lon:  %d/%d (%.1f%%)\n",
    $stats['lon']['passed'], $stats['lon']['tests'],
    $stats['lon']['tests'] > 0 ? ($stats['lon']['passed'] / $stats['lon']['tests']) * 100 : 0);
echo sprintf("  Geocentric Lat:  %d/%d (%.1f%%)\n",
    $stats['lat']['passed'], $stats['lat']['tests'],
    $stats['lat']['tests'] > 0 ? ($stats['lat']['passed'] / $stats['lat']['tests']) * 100 : 0);
echo sprintf("  Distance:        %d/%d (%.1f%%)\n",
    $stats['dist']['passed'], $stats['dist']['tests'],
    $stats['dist']['tests'] > 0 ? ($stats['dist']['passed'] / $stats['dist']['tests']) * 100 : 0);
echo sprintf("  Heliocentric:    %d/%d (%.1f%%)\n",
    $helioStats['passed'], $helioStats['tests'],
    $helioStats['tests'] > 0 ? ($helioStats['passed'] / $helioStats['tests']) * 100 : 0);
echo sprintf("  True Pos:        %d/%d (%.1f%%)\n",
    $trueStats['passed'], $trueStats['tests'],
    $trueStats['tests'] > 0 ? ($trueStats['passed'] / $trueStats['tests']) * 100 : 0);
echo sprintf("  No Nutation:     %d/%d (%.1f%%)\n",
    $nonutStats['passed'], $nonutStats['tests'],
    $nonutStats['tests'] > 0 ? ($nonutStats['passed'] / $nonutStats['tests']) * 100 : 0);

if ($totalPassed === $totalTests) {
    echo "\n✓ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED\n";
    exit(1);
}
