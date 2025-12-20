<?php
/**
 * Compare VSOP87 with swetest64.exe reference for all planets
 * Uses J2000.0 epoch for consistency
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// swetest64.exe path
$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';
$ephe = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\ephe';

if (!file_exists($swetest)) {
    die("ERROR: swetest64.exe not found at $swetest\n");
}

// Set ephemeris path for PHP
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2451545.0; // J2000.0

$planets = [
    ['name' => 'Mercury', 'ipl' => Constants::SE_MERCURY, 'code' => '0'],
    ['name' => 'Venus', 'ipl' => Constants::SE_VENUS, 'code' => '1'],
    ['name' => 'Mars', 'ipl' => Constants::SE_MARS, 'code' => '4'],
    ['name' => 'Jupiter', 'ipl' => Constants::SE_JUPITER, 'code' => '5'],
    ['name' => 'Saturn', 'ipl' => Constants::SE_SATURN, 'code' => '6'],
    ['name' => 'Uranus', 'ipl' => Constants::SE_URANUS, 'code' => '7'],
    ['name' => 'Neptune', 'ipl' => Constants::SE_NEPTUNE, 'code' => '8'],
];

echo "=== VSOP87 vs SWIEPH Reference (J2000.0) ===\n";
echo "Planet    │ ΔLon (arcsec) │ ΔLat (arcsec) │ ΔDist (km)   │ Status\n";
echo "──────────┼───────────────┼───────────────┼──────────────┼─────────\n";

foreach ($planets as $planet) {
    // Get VSOP87 coordinates
    $xx_vsop = array_fill(0, 6, 0.0);
    $serr = '';
    $ret_vsop = swe_calc($jd, $planet['ipl'], Constants::SEFLG_VSOP87, $xx_vsop, $serr);

    if ($ret_vsop < 0) {
        echo sprintf("%-9s │ ERROR: %s\n", $planet['name'], $serr);
        continue;
    }

    // Get SWIEPH reference (use swetest64 -b for accuracy)
    $cmd = sprintf(
        '"%s" -b%f -p%s -fPl -edir"%s" 2>&1',
        $swetest,
        $jd,
        $planet['code'],
        $ephe
    );
    $output = shell_exec($cmd);

    if (!$output || !preg_match('/(\d+\.\d+)\s+(-?\d+\.\d+)\s+(\d+\.\d+)/', $output, $matches)) {
        echo sprintf("%-9s │ ERROR: Failed to parse swetest output\n", $planet['name']);
        continue;
    }

    $ref_lon = (float)$matches[1];
    $ref_lat = (float)$matches[2];
    $ref_dist = (float)$matches[3];

    // Calculate errors
    $err_lon = abs($xx_vsop[0] - $ref_lon) * 3600; // arcsec
    $err_lat = abs($xx_vsop[1] - $ref_lat) * 3600; // arcsec
    $err_dist = abs($xx_vsop[2] - $ref_dist) * 1.496e8; // km

    // Determine status
    $status = '✓';
    if ($err_lon > 36 || $err_lat > 36) {
        $status = '⚠';
    }
    if ($err_lon > 180 || $err_lat > 180) {
        $status = '✗';
    }

    printf(
        "%-9s │ %13.1f │ %13.1f │ %12.0f │ %s\n",
        $planet['name'],
        $err_lon,
        $err_lat,
        $err_dist,
        $status
    );
}

echo "\nLegend: ✓ Good (<36″)  ⚠ Fair (36-180″)  ✗ Poor (>180″)\n";
