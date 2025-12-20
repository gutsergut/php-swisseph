<?php
/**
 * Compare JplEphemeris results with swetest for multiple dates
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$ephDir = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl';

// Test with DE200 (little-endian)
JplEphemeris::resetInstance();  // Reset any previous state
$jpl = JplEphemeris::getInstance();
$serr = '';
$ss = [];

// Open the ephemeris file first (DE406e - big endian)
$ret = $jpl->open($ss, 'de406e.eph', $ephDir, $serr);
if ($ret < 0) {
    die("Failed to open ephemeris: $serr\n");
}
echo "DE406e opened, range: [{$ss[0]}, {$ss[1]}], segment: {$ss[2]} days\n\n";

$testDates = [
    2451545.0,    // J2000
    2451600.0,    // J2000 + 55 days
    2460000.0,    // 2023
    2440000.0,    // 1968
    -254863.5,    // Early DE406e
];

echo "=== Testing JplEphemeris vs swetest ===\n\n";

foreach ($testDates as $jd) {
    $pv = [];
    $ret = $jpl->pleph(
        $jd,
        JplConstants::J_MERCURY,
        JplConstants::J_SBARY,
        $pv,
        $serr
    );

    if ($ret < 0) {
        echo "JD $jd: Error - $serr\n";
        continue;
    }

    echo "JD $jd Mercury barycentric (AU):\n";
    printf("  PHP:  X = %15.9f, Y = %15.9f, Z = %15.9f\n", $pv[0], $pv[1], $pv[2]);
}
