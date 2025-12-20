<?php
/**
 * Test swe_calc() with SEFLG_MOSEPH
 *
 * Compares PHP swe_calc(SEFLG_MOSEPH) output with swetest -emos
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe;
use Swisseph\Constants;

// JD for 2024-06-15 12:00 UT
$jdUT = 2460477.0;

echo "=== Testing swe_calc() with SEFLG_MOSEPH ===\n\n";
echo sprintf("JD (UT): %.6f\n\n", $jdUT);

// Reference data from swetest -emos:
// cmd /c "swetest64.exe -b15.6.2024 -ut12:00 -emos -p012345678 -fPl -head"
$reference = [
    'Sun'     => 84.6547116,   // Geocentric ecliptic longitude
    'Moon'    => 264.7282602,
    'Mercury' => 89.2584683,
    'Venus'   => 92.6668766,
    'Mars'    => 0.9741753,
    'Jupiter' => 61.2074619,
    'Saturn'  => 343.6873715,
    'Uranus'  => 53.9120608,
    'Neptune' => 358.3024054,
    'Pluto'   => 301.8147422,
];

$planets = [
    'Sun'     => Constants::SE_SUN,
    'Moon'    => Constants::SE_MOON,
    'Mercury' => Constants::SE_MERCURY,
    'Venus'   => Constants::SE_VENUS,
    'Mars'    => Constants::SE_MARS,
    'Jupiter' => Constants::SE_JUPITER,
    'Saturn'  => Constants::SE_SATURN,
    'Uranus'  => Constants::SE_URANUS,
    'Neptune' => Constants::SE_NEPTUNE,
    'Pluto'   => Constants::SE_PLUTO,
];

echo "--- Geocentric Ecliptic Longitude Comparison ---\n\n";
echo sprintf("%-10s %15s %15s %12s\n", 'Planet', 'PHP (°)', 'Ref (°)', 'Δ (")');
echo str_repeat('-', 55) . "\n";

$iflag = Constants::SEFLG_MOSEPH;

foreach ($planets as $name => $ipl) {
    $xx = [];
    $serr = null;

    // Skip Moon for now - Moshier Moon is not implemented yet
    if ($ipl === Constants::SE_MOON) {
        echo sprintf("%-10s %15s %15.7f %12s\n",
            $name, 'N/A', $reference[$name], '(Moon not impl)');
        continue;
    }

    $ret = Swe::swe_calc_ut($jdUT, $ipl, $iflag, $xx, $serr);

    if ($ret < 0) {
        echo "$name: ERROR - $serr\n";
        continue;
    }

    $ref = $reference[$name];
    $php_lon = $xx[0];
    $diff = ($php_lon - $ref) * 3600;

    // Handle wraparound
    if ($diff > 648000) $diff -= 1296000;
    if ($diff < -648000) $diff += 1296000;

    $status = abs($diff) < 100 ? '✓' : '✗';

    echo sprintf("%-10s %15.7f %15.7f %+12.2f %s\n",
        $name, $php_lon, $ref, $diff, $status);
}

echo "\n=== Test completed ===\n";
echo "Note: Δ should be < 100\" for Moshier accuracy (~50\" inner, ~10\" outer)\n";
