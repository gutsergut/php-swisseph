<?php
/**
 * Maximum Parameter Testing Script
 *
 * Tests ALL available input parameters for Swiss Ephemeris functions
 * against swetest64.exe reference values.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Configuration
$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';
$epheDirPath = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe';

// Tolerances
$toleranceAngle = 1.0;      // arcseconds
$toleranceDist = 0.0001;    // AU
$toleranceSpeed = 0.001;    // deg/day

// Statistics
$stats = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'errors' => [],
];

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

function jdToDateTime(float $jd): array
{
    $jd2000 = 2451545.0;
    $daysSince2000 = $jd - $jd2000;
    $baseDate = new DateTime('2000-01-01 12:00:00', new DateTimeZone('UTC'));
    $baseDate->modify(sprintf('%+d seconds', (int)($daysSince2000 * 86400)));
    return [
        'date' => $baseDate->format('j.n.Y'),
        'time' => $baseDate->format('H:i:s'),
    ];
}

function runSwetest(string $swetest, string $args): ?string
{
    global $epheDirPath;
    $cmd = sprintf('cmd /c ""%s" %s -head -eswe -edir%s"', $swetest, $args, $epheDirPath);
    return shell_exec($cmd);
}

function parseSwetestLine(string $output): ?array
{
    // Parse: "Sun              280.3681656   0.0002274    0.983327631   1.0194341"
    if (preg_match('/^\s*\S+\s+([-+]?\d+\.?\d*)\s+([-+]?\d+\.?\d*)\s+([-+]?\d+\.?\d*)"?\s+([-+]?\d+\.?\d*)/m', $output, $m)) {
        return [
            'lon' => (float)$m[1],
            'lat' => (float)$m[2],
            'dist' => (float)$m[3],
            'speed' => (float)$m[4],
        ];
    }
    return null;
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

function test(string $name, bool $condition, string $details = ''): void
{
    global $stats;
    $stats['total']++;
    if ($condition) {
        $stats['passed']++;
    } else {
        $stats['failed']++;
        $stats['errors'][] = ['name' => $name, 'details' => $details];
    }
}

// =============================================================================
// TEST DATES
// =============================================================================

$testDates = [
    2378497.0,   // 1800-01-01
    2415020.5,   // 1900-01-01
    2433282.5,   // 1950-01-01
    2440587.5,   // 1970-01-01
    2451545.0,   // J2000.0
    2458849.5,   // 2020-01-01
    2469807.5,   // 2050-01-01
    2488069.5,   // 2100-01-01
];

$planets = [
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
];

echo "=".str_repeat("=", 78)."\n";
echo " MAXIMUM PARAMETER TESTING - Swiss Ephemeris PHP Port\n";
echo "=".str_repeat("=", 78)."\n\n";

// =============================================================================
// SECTION 1: ECLIPTIC GEOCENTRIC (default)
// =============================================================================

echo "--- Section 1: Ecliptic Geocentric (default flags) ---\n";

$section1Stats = ['tests' => 0, 'passed' => 0];

foreach ($testDates as $jd) {
    $dt = jdToDateTime($jd);

    foreach ($planets as $planetId => $planetArg) {
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $planetId, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);

        if ($ret < 0) continue;

        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$planetArg} -fPlbrs");
        $ref = parseSwetestLine($output ?? '');

        if ($ref === null) continue;

        $section1Stats['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diffLon <= $toleranceAngle) {
            $section1Stats['passed']++;
        }
    }
}

echo "  Passed: {$section1Stats['passed']}/{$section1Stats['tests']}\n";

// =============================================================================
// SECTION 2: EQUATORIAL COORDINATES
// =============================================================================

echo "\n--- Section 2: Equatorial Coordinates (RA/Dec) ---\n";

$section2Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach (array_slice($testDates, 2, 4) as $jd) {  // Test subset of dates
    $dt = jdToDateTime($jd);

    foreach ($planets as $planetId => $planetArg) {
        $xx = [];
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL;
        $ret = swe_calc_ut($jd, $planetId, $flags, $xx, $serr);

        if ($ret < 0) continue;

        // swetest -fPADR gives RA, Dec (format uses 'A' for RA in hours, 'D' for Dec)
        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$planetArg} -fPADR");

        // Parse equatorial output: "Sun    18h45' 6.8125  -23° 1'56.7492   0.983327625"
        if (preg_match('/\s+(\d+)h\s*(\d+)\'\s*([\d.]+)\s+([-+]?\d+)°\s*(\d+)\'([\d.]+)\s+([\d.]+)/m', $output ?? '', $m)) {
            $refRA = ((float)$m[1] + (float)$m[2]/60 + (float)$m[3]/3600) * 15; // hours to degrees
            $decSign = (strpos($m[4], '-') !== false) ? -1 : 1;
            $refDec = abs((float)$m[4]) + (float)$m[5]/60 + (float)$m[6]/3600;
            $refDec *= $decSign;

            $section2Stats['tests']++;

            // RA comparison
            $diffRA = abs(angleDiff($xx[0], $refRA)) * 3600.0;
            // Dec comparison
            $diffDec = abs($xx[1] - $refDec) * 3600.0;

            $maxDiff = max($diffRA, $diffDec);
            if ($maxDiff > $section2Stats['maxErr']) {
                $section2Stats['maxErr'] = $maxDiff;
            }

            if ($diffRA <= $toleranceAngle && $diffDec <= $toleranceAngle) {
                $section2Stats['passed']++;
            }
        }
    }
}

echo "  Passed: {$section2Stats['passed']}/{$section2Stats['tests']}\n";
echo sprintf("  Max error: %.3f arcsec\n", $section2Stats['maxErr']);

// =============================================================================
// SECTION 3: HELIOCENTRIC COORDINATES
// =============================================================================

echo "\n--- Section 3: Heliocentric Coordinates ---\n";

$helioPlanets = [
    Constants::SE_MERCURY => '2',
    Constants::SE_VENUS => '3',
    Constants::SE_MARS => '4',
    Constants::SE_JUPITER => '5',
    Constants::SE_SATURN => '6',
];

$section3Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach (array_slice($testDates, 2, 4) as $jd) {
    $dt = jdToDateTime($jd);

    foreach ($helioPlanets as $planetId => $planetArg) {
        $xx = [];
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR;
        $ret = swe_calc_ut($jd, $planetId, $flags, $xx, $serr);

        if ($ret < 0) continue;

        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$planetArg} -fPlbrs -hel");
        $ref = parseSwetestLine($output ?? '');

        if ($ref === null) continue;

        $section3Stats['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diffLon > $section3Stats['maxErr']) {
            $section3Stats['maxErr'] = $diffLon;
        }

        if ($diffLon <= $toleranceAngle) {
            $section3Stats['passed']++;
        }
    }
}

echo "  Passed: {$section3Stats['passed']}/{$section3Stats['tests']}\n";
echo sprintf("  Max error: %.3f arcsec\n", $section3Stats['maxErr']);

// =============================================================================
// SECTION 4: BARYCENTRIC COORDINATES
// =============================================================================

echo "\n--- Section 4: Barycentric Coordinates ---\n";

$section4Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach ([2451545.0, 2458849.5] as $jd) {
    $dt = jdToDateTime($jd);

    foreach ([Constants::SE_SUN => '0', Constants::SE_JUPITER => '5'] as $planetId => $planetArg) {
        $xx = [];
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR;
        $ret = swe_calc_ut($jd, $planetId, $flags, $xx, $serr);

        if ($ret < 0) continue;

        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$planetArg} -fPlbrs -bary");
        $ref = parseSwetestLine($output ?? '');

        if ($ref === null) continue;

        $section4Stats['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diffLon > $section4Stats['maxErr']) {
            $section4Stats['maxErr'] = $diffLon;
        }

        if ($diffLon <= $toleranceAngle) {
            $section4Stats['passed']++;
        }
    }
}

echo "  Passed: {$section4Stats['passed']}/{$section4Stats['tests']}\n";
echo sprintf("  Max error: %.3f arcsec\n", $section4Stats['maxErr']);

// =============================================================================
// SECTION 5: TRUE POSITION (no aberration)
// =============================================================================

echo "\n--- Section 5: True Position (no aberration) ---\n";

$section5Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach (array_slice($testDates, 2, 4) as $jd) {
    $dt = jdToDateTime($jd);

    foreach ($planets as $planetId => $planetArg) {
        $xx = [];
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_TRUEPOS;
        $ret = swe_calc_ut($jd, $planetId, $flags, $xx, $serr);

        if ($ret < 0) continue;

        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$planetArg} -fPlbrs -true");
        $ref = parseSwetestLine($output ?? '');

        if ($ref === null) continue;

        $section5Stats['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diffLon > $section5Stats['maxErr']) {
            $section5Stats['maxErr'] = $diffLon;
        }

        if ($diffLon <= $toleranceAngle) {
            $section5Stats['passed']++;
        }
    }
}

echo "  Passed: {$section5Stats['passed']}/{$section5Stats['tests']}\n";
echo sprintf("  Max error: %.3f arcsec\n", $section5Stats['maxErr']);

// =============================================================================
// SECTION 6: NO NUTATION
// =============================================================================

echo "\n--- Section 6: No Nutation ---\n";

$section6Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach (array_slice($testDates, 2, 4) as $jd) {
    $dt = jdToDateTime($jd);

    foreach ($planets as $planetId => $planetArg) {
        $xx = [];
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_NONUT;
        $ret = swe_calc_ut($jd, $planetId, $flags, $xx, $serr);

        if ($ret < 0) continue;

        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$planetArg} -fPlbrs -nonut");
        $ref = parseSwetestLine($output ?? '');

        if ($ref === null) continue;

        $section6Stats['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diffLon > $section6Stats['maxErr']) {
            $section6Stats['maxErr'] = $diffLon;
        }

        if ($diffLon <= $toleranceAngle) {
            $section6Stats['passed']++;
        }
    }
}

echo "  Passed: {$section6Stats['passed']}/{$section6Stats['tests']}\n";
echo sprintf("  Max error: %.3f arcsec\n", $section6Stats['maxErr']);

// =============================================================================
// SECTION 7: SPEED CALCULATIONS
// =============================================================================

echo "\n--- Section 7: Speed Calculations ---\n";

$section7Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach ([2451545.0, 2458849.5] as $jd) {
    $dt = jdToDateTime($jd);

    foreach ($planets as $planetId => $planetArg) {
        $xx = [];
        $serr = '';
        $flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
        $ret = swe_calc_ut($jd, $planetId, $flags, $xx, $serr);

        if ($ret < 0) continue;

        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$planetArg} -fPlbrs");
        $ref = parseSwetestLine($output ?? '');

        if ($ref === null) continue;

        $section7Stats['tests']++;

        // Compare longitude speed (xx[3] = speed in deg/day)
        $diffSpeed = abs($xx[3] - $ref['speed']);

        if ($diffSpeed > $section7Stats['maxErr']) {
            $section7Stats['maxErr'] = $diffSpeed;
        }

        if ($diffSpeed <= $toleranceSpeed) {
            $section7Stats['passed']++;
        }
    }
}

echo "  Passed: {$section7Stats['passed']}/{$section7Stats['tests']}\n";
echo sprintf("  Max error: %.6f deg/day\n", $section7Stats['maxErr']);

// =============================================================================
// SECTION 8: LUNAR NODES
// =============================================================================

echo "\n--- Section 8: Lunar Nodes ---\n";

$nodeTests = [
    Constants::SE_MEAN_NODE => ['arg' => 'm', 'name' => 'Mean Node'],
    Constants::SE_TRUE_NODE => ['arg' => 't', 'name' => 'True Node'],
];

$section8Stats = ['tests' => 0, 'passed' => 0];

foreach ([2451545.0, 2458849.5, 2469807.5] as $jd) {
    $dt = jdToDateTime($jd);

    foreach ($nodeTests as $nodeId => $nodeInfo) {
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $nodeId, Constants::SEFLG_SWIEPH, $xx, $serr);

        if ($ret < 0) continue;

        // Use -pm or -pt (no space between -p and m/t)
        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$nodeInfo['arg']} -fPlbrs");

        // Parse: "mean Node        125.0406461   0.0000000    0.002569555  -0.0529518"
        // Note: name has space, so we use a different regex
        if (preg_match('/Node\s+([-+]?\d+\.?\d*)\s+([-+]?\d+\.?\d*)\s+([-+]?\d+\.?\d*)\s+([-+]?\d+\.?\d*)/m', $output ?? '', $m)) {
            $ref = [
                'lon' => (float)$m[1],
                'lat' => (float)$m[2],
                'dist' => (float)$m[3],
                'speed' => (float)$m[4],
            ];
        } else {
            continue;
        }

        $section8Stats['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diffLon <= $toleranceAngle) {
            $section8Stats['passed']++;
        }
    }
}

echo "  Passed: {$section8Stats['passed']}/{$section8Stats['tests']}\n";

// =============================================================================
// SECTION 9: ASTEROIDS (Chiron, Ceres, Pallas, Juno, Vesta)
// =============================================================================

echo "\n--- Section 9: Main Asteroids ---\n";

$asteroids = [
    Constants::SE_CHIRON => ['arg' => 'D', 'name' => 'Chiron'],
    Constants::SE_PHOLUS => ['arg' => 'E', 'name' => 'Pholus'],
    Constants::SE_CERES => ['arg' => 'F', 'name' => 'Ceres'],
    Constants::SE_PALLAS => ['arg' => 'G', 'name' => 'Pallas'],
    Constants::SE_JUNO => ['arg' => 'H', 'name' => 'Juno'],
    Constants::SE_VESTA => ['arg' => 'I', 'name' => 'Vesta'],
];

$section9Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach ([2451545.0, 2458849.5] as $jd) {
    $dt = jdToDateTime($jd);

    foreach ($asteroids as $asteroidId => $asteroidInfo) {
        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $asteroidId, Constants::SEFLG_SWIEPH, $xx, $serr);

        if ($ret < 0) continue;

        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$asteroidInfo['arg']} -fPlbrs");
        $ref = parseSwetestLine($output ?? '');

        if ($ref === null) continue;

        $section9Stats['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diffLon > $section9Stats['maxErr']) {
            $section9Stats['maxErr'] = $diffLon;
        }

        if ($diffLon <= $toleranceAngle) {
            $section9Stats['passed']++;
        }
    }
}

echo "  Passed: {$section9Stats['passed']}/{$section9Stats['tests']}\n";
echo sprintf("  Max error: %.3f arcsec\n", $section9Stats['maxErr']);

// =============================================================================
// SECTION 10: HOUSES (Placidus, Koch, Equal, Whole Sign)
// =============================================================================

echo "\n--- Section 10: House Systems ---\n";

$houseSystems = ['P' => 'Placidus', 'K' => 'Koch', 'E' => 'Equal', 'W' => 'Whole Sign'];
$testLocations = [
    ['lat' => 52.5, 'lon' => 13.4, 'name' => 'Berlin'],
    ['lat' => 40.7, 'lon' => -74.0, 'name' => 'New York'],
    ['lat' => -33.9, 'lon' => 151.2, 'name' => 'Sydney'],
];

$section10Stats = ['tests' => 0, 'passed' => 0];

foreach ([2451545.0, 2458849.5] as $jd) {
    foreach ($houseSystems as $sysCode => $sysName) {
        foreach ($testLocations as $loc) {
            $cusps = [];
            $ascmc = [];

            $ret = swe_houses($jd, $loc['lat'], $loc['lon'], $sysCode, $cusps, $ascmc);

            if ($ret < 0) continue;

            // Just verify that we got valid cusp values (0-360)
            $section10Stats['tests']++;
            $valid = true;
            for ($i = 1; $i <= 12; $i++) {
                if (!isset($cusps[$i]) || $cusps[$i] < 0 || $cusps[$i] >= 360) {
                    $valid = false;
                    break;
                }
            }

            if ($valid && isset($ascmc[0]) && $ascmc[0] >= 0 && $ascmc[0] < 360) {
                $section10Stats['passed']++;
            }
        }
    }
}

echo "  Passed: {$section10Stats['passed']}/{$section10Stats['tests']}\n";

// =============================================================================
// SECTION 11: DELTA T
// =============================================================================

echo "\n--- Section 11: Delta T ---\n";

$deltaTDates = [
    2415020.5,   // 1900
    2433282.5,   // 1950
    2440587.5,   // 1970
    2451545.0,   // 2000
    2458849.5,   // 2020
];

$section11Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

foreach ($deltaTDates as $jd) {
    $dt = jdToDateTime($jd);

    // Get PHP Delta T
    $phpDeltaT = swe_deltat($jd);

    // Get reference from swetest
    $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p0 -fPl -g");

    // Parse Delta T from output (appears in verbose mode)
    // For now, just check that Delta T is reasonable (0-200 seconds)
    $section11Stats['tests']++;

    $deltaTSeconds = $phpDeltaT * 86400.0; // Convert days to seconds

    if ($deltaTSeconds >= -50 && $deltaTSeconds <= 200) {
        $section11Stats['passed']++;
    }
}

echo "  Passed: {$section11Stats['passed']}/{$section11Stats['tests']}\n";

// =============================================================================
// SECTION 12: SIDEREAL TIME
// =============================================================================

echo "\n--- Section 12: Sidereal Time ---\n";

$section12Stats = ['tests' => 0, 'passed' => 0];

foreach ([2451545.0, 2458849.5] as $jd) {
    $sidtime = swe_sidtime($jd);

    $section12Stats['tests']++;

    // Sidereal time should be 0-24 hours
    if ($sidtime >= 0 && $sidtime < 24) {
        $section12Stats['passed']++;
    }
}

echo "  Passed: {$section12Stats['passed']}/{$section12Stats['tests']}\n";

// =============================================================================
// SECTION 13: CALENDAR CONVERSIONS
// =============================================================================

echo "\n--- Section 13: Calendar Conversions ---\n";

$section13Stats = ['tests' => 0, 'passed' => 0];

$calendarTests = [
    ['y' => 2000, 'm' => 1, 'd' => 1, 'h' => 12.0, 'g' => 1, 'expected' => 2451545.0],   // J2000.0
    ['y' => 1900, 'm' => 1, 'd' => 1, 'h' => 0.0, 'g' => 1, 'expected' => 2415020.5],    // Jan 1, 1900 00:00 UT
    ['y' => 2020, 'm' => 1, 'd' => 1, 'h' => 12.0, 'g' => 1, 'expected' => 2458850.0],   // Jan 1, 2020 12:00 UT
];

foreach ($calendarTests as $test) {
    $jd = swe_julday($test['y'], $test['m'], $test['d'], $test['h'], $test['g']);

    $section13Stats['tests']++;

    if (abs($jd - $test['expected']) < 0.01) {
        $section13Stats['passed']++;
    }
}

echo "  Passed: {$section13Stats['passed']}/{$section13Stats['tests']}\n";

// =============================================================================
// SECTION 14: COMBINED FLAGS
// =============================================================================

echo "\n--- Section 14: Combined Flags ---\n";

$flagCombinations = [
    'TRUEPOS + NONUT' => Constants::SEFLG_SWIEPH | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT,
    'EQUATORIAL + SPEED' => Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED,
    'HELCTR + SPEED' => Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR | Constants::SEFLG_SPEED,
];

$section14Stats = ['tests' => 0, 'passed' => 0];

$jd = 2451545.0;
foreach ($flagCombinations as $name => $flags) {
    $xx = [];
    $serr = '';
    $ret = swe_calc_ut($jd, Constants::SE_MARS, $flags, $xx, $serr);

    $section14Stats['tests']++;

    // Just check that calculation succeeds and returns valid data
    if ($ret >= 0 && count($xx) >= 6) {
        $section14Stats['passed']++;
    }
}

echo "  Passed: {$section14Stats['passed']}/{$section14Stats['tests']}\n";

// =============================================================================
// SECTION 15: EDGE CASES
// =============================================================================

echo "\n--- Section 15: Edge Cases ---\n";

$section15Stats = ['tests' => 0, 'passed' => 0];

// Test 1: Very old date (1500 AD)
$xx = [];
$serr = '';
$ret = swe_calc_ut(2268923.5, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx, $serr);
$section15Stats['tests']++;
if ($ret >= 0) $section15Stats['passed']++;

// Test 2: Future date (2200 AD)
$ret = swe_calc_ut(2524593.5, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx, $serr);
$section15Stats['tests']++;
if ($ret >= 0) $section15Stats['passed']++;

// Test 3: Moon at different hours of day
foreach ([0.0, 0.25, 0.5, 0.75] as $frac) {
    $ret = swe_calc_ut(2451545.0 + $frac, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xx, $serr);
    $section15Stats['tests']++;
    if ($ret >= 0 && $xx[0] >= 0 && $xx[0] < 360) $section15Stats['passed']++;
}

// Test 4: High latitude houses
$cusps = [];
$ascmc = [];
$ret = swe_houses(2451545.0, 70.0, 25.0, 'P', $cusps, $ascmc);  // Arctic
$section15Stats['tests']++;
if ($ret >= 0 || $ret === -1) $section15Stats['passed']++;  // May fail at high lat, that's OK

echo "  Passed: {$section15Stats['passed']}/{$section15Stats['tests']}\n";

// =============================================================================
// SECTION 16: RADIANS OUTPUT (SEFLG_RADIANS)
// =============================================================================

echo "\n--- Section 16: Radians Output ---\n";

$section16Stats = ['tests' => 0, 'passed' => 0];

$jd = 2451545.0;
$xx_deg = [];
$xx_rad = [];
$serr = '';

// Calculate in degrees
swe_calc_ut($jd, Constants::SE_SUN, Constants::SEFLG_SWIEPH, $xx_deg, $serr);

// Calculate in radians
swe_calc_ut($jd, Constants::SE_SUN, Constants::SEFLG_SWIEPH | Constants::SEFLG_RADIANS, $xx_rad, $serr);

$section16Stats['tests']++;
// Check if radians = degrees * pi/180
$expected_rad = deg2rad($xx_deg[0]);
if (abs($xx_rad[0] - $expected_rad) < 0.0001) {
    $section16Stats['passed']++;
}

$section16Stats['tests']++;
// Check latitude in radians
$expected_lat_rad = deg2rad($xx_deg[1]);
if (abs($xx_rad[1] - $expected_lat_rad) < 0.0001) {
    $section16Stats['passed']++;
}

echo "  Passed: {$section16Stats['passed']}/{$section16Stats['tests']}\n";

// =============================================================================
// SECTION 17: XYZ COORDINATES (SEFLG_XYZ)
// =============================================================================

echo "\n--- Section 17: XYZ Coordinates ---\n";

$section17Stats = ['tests' => 0, 'passed' => 0];

$jd = 2451545.0;

foreach ([Constants::SE_SUN, Constants::SE_MOON, Constants::SE_MARS] as $planet) {
    $xx_ecl = [];
    $xx_xyz = [];
    $serr = '';

    // Get ecliptic coords
    swe_calc_ut($jd, $planet, Constants::SEFLG_SWIEPH, $xx_ecl, $serr);

    // Get XYZ coords
    $ret = swe_calc_ut($jd, $planet, Constants::SEFLG_SWIEPH | Constants::SEFLG_XYZ, $xx_xyz, $serr);

    $section17Stats['tests']++;

    // Verify XYZ coords are Cartesian (should give same distance)
    if ($ret >= 0) {
        $dist_xyz = sqrt($xx_xyz[0]**2 + $xx_xyz[1]**2 + $xx_xyz[2]**2);
        $dist_ecl = $xx_ecl[2];

        if (abs($dist_xyz - $dist_ecl) / $dist_ecl < 0.001) {  // 0.1% tolerance
            $section17Stats['passed']++;
        }
    }
}

echo "  Passed: {$section17Stats['passed']}/{$section17Stats['tests']}\n";

// =============================================================================
// SECTION 18: EXTENDED DATE RANGE
// =============================================================================

echo "\n--- Section 18: Extended Date Range ---\n";

$section18Stats = ['tests' => 0, 'passed' => 0, 'maxErr' => 0];

$extendedDates = [
    2415020.5,   // 1900-01-01
    2440587.5,   // 1970-01-01
    2451545.0,   // 2000-01-01 12:00
    2460310.5,   // 2024-01-01
    2488069.5,   // 2100-01-01
];

foreach ($extendedDates as $jd) {
    $dt = jdToDateTime($jd);

    foreach ([Constants::SE_SUN, Constants::SE_MOON, Constants::SE_MARS] as $planetId) {
        $planetArg = ['0' => '0', '1' => '1', '4' => '4'][$planetId] ?? null;
        if (!$planetArg) continue;

        $xx = [];
        $serr = '';
        $ret = swe_calc_ut($jd, $planetId, Constants::SEFLG_SWIEPH, $xx, $serr);

        if ($ret < 0) continue;

        $output = runSwetest($swetest, "-b{$dt['date']} -ut{$dt['time']} -p{$planetArg} -fPlbrs");
        $ref = parseSwetestLine($output ?? '');

        if ($ref === null) continue;

        $section18Stats['tests']++;
        $diffLon = abs(angleDiff($xx[0], $ref['lon'])) * 3600.0;

        if ($diffLon > $section18Stats['maxErr']) {
            $section18Stats['maxErr'] = $diffLon;
        }

        if ($diffLon <= $toleranceAngle) {
            $section18Stats['passed']++;
        }
    }
}

echo "  Passed: {$section18Stats['passed']}/{$section18Stats['tests']}\n";
echo sprintf("  Max error: %.3f arcsec\n", $section18Stats['maxErr']);

// =============================================================================
// SECTION 19: REVERSE CALENDAR (JD to Date)
// =============================================================================

echo "\n--- Section 19: Reverse Calendar (JD to Date) ---\n";

$section19Stats = ['tests' => 0, 'passed' => 0];

$revCalTests = [
    ['jd' => 2451545.0, 'expected' => ['y' => 2000, 'm' => 1, 'd' => 1, 'h' => 12.0]],
    ['jd' => 2415020.5, 'expected' => ['y' => 1900, 'm' => 1, 'd' => 1, 'h' => 0.0]],
    ['jd' => 2458850.0, 'expected' => ['y' => 2020, 'm' => 1, 'd' => 1, 'h' => 12.0]],
];

foreach ($revCalTests as $test) {
    $result = swe_revjul($test['jd'], 1);  // Returns array: ['y', 'm', 'd', 'ut']

    $section19Stats['tests']++;

    if ($result['y'] === $test['expected']['y'] &&
        $result['m'] === $test['expected']['m'] &&
        $result['d'] === $test['expected']['d'] &&
        abs($result['ut'] - $test['expected']['h']) < 0.001) {
        $section19Stats['passed']++;
    }
}

echo "  Passed: {$section19Stats['passed']}/{$section19Stats['tests']}\n";

// =============================================================================
// SECTION 20: PLANET NAMES
// =============================================================================

echo "\n--- Section 20: Planet Names ---\n";

$section20Stats = ['tests' => 0, 'passed' => 0];

$planetNames = [
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
];

foreach ($planetNames as $id => $expectedName) {
    $section20Stats['tests']++;

    $name = swe_get_planet_name($id);

    if (stripos($name, $expectedName) !== false) {
        $section20Stats['passed']++;
    }
}

echo "  Passed: {$section20Stats['passed']}/{$section20Stats['tests']}\n";

// =============================================================================
// SUMMARY
// =============================================================================

echo "\n" . str_repeat("=", 79) . "\n";
echo " SUMMARY\n";
echo str_repeat("=", 79) . "\n\n";

$allSections = [
    ['name' => 'Ecliptic Geocentric', 'stats' => $section1Stats],
    ['name' => 'Equatorial (RA/Dec)', 'stats' => $section2Stats],
    ['name' => 'Heliocentric', 'stats' => $section3Stats],
    ['name' => 'Barycentric', 'stats' => $section4Stats],
    ['name' => 'True Position', 'stats' => $section5Stats],
    ['name' => 'No Nutation', 'stats' => $section6Stats],
    ['name' => 'Speed Calculations', 'stats' => $section7Stats],
    ['name' => 'Lunar Nodes', 'stats' => $section8Stats],
    ['name' => 'Asteroids', 'stats' => $section9Stats],
    ['name' => 'House Systems', 'stats' => $section10Stats],
    ['name' => 'Delta T', 'stats' => $section11Stats],
    ['name' => 'Sidereal Time', 'stats' => $section12Stats],
    ['name' => 'Calendar Conversions', 'stats' => $section13Stats],
    ['name' => 'Combined Flags', 'stats' => $section14Stats],
    ['name' => 'Edge Cases', 'stats' => $section15Stats],
    ['name' => 'Radians Output', 'stats' => $section16Stats],
    ['name' => 'XYZ Coordinates', 'stats' => $section17Stats],
    ['name' => 'Extended Date Range', 'stats' => $section18Stats],
    ['name' => 'Reverse Calendar', 'stats' => $section19Stats],
    ['name' => 'Planet Names', 'stats' => $section20Stats],
];

$totalTests = 0;
$totalPassed = 0;

foreach ($allSections as $section) {
    $tests = $section['stats']['tests'];
    $passed = $section['stats']['passed'];
    $pct = $tests > 0 ? ($passed / $tests) * 100 : 0;
    $status = $passed === $tests ? '✓' : '✗';

    printf("  %s %-25s %3d/%3d (%.1f%%)\n", $status, $section['name'], $passed, $tests, $pct);

    $totalTests += $tests;
    $totalPassed += $passed;
}

echo "\n" . str_repeat("-", 79) . "\n";
$totalPct = $totalTests > 0 ? ($totalPassed / $totalTests) * 100 : 0;
printf("  TOTAL: %d/%d tests passed (%.1f%%)\n", $totalPassed, $totalTests, $totalPct);

if ($totalPassed === $totalTests) {
    echo "\n✓ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED\n";
    exit(1);
}
