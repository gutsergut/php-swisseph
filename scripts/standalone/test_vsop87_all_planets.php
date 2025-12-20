<?php
/**
 * Test VSOP87 geocentric coordinates for all outer planets
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// JD = 2451545.0 (J2000.0)
$jd = 2451545.0;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$planets = [
    'Venus' => Constants::SE_VENUS,
    'Mars' => Constants::SE_MARS,
    'Jupiter' => Constants::SE_JUPITER,
    'Saturn' => Constants::SE_SATURN,
    'Uranus' => Constants::SE_URANUS,
    'Neptune' => Constants::SE_NEPTUNE,
];

$results = [];

foreach ($planets as $name => $planet) {
    $xx_vsop = array_fill(0, 6, 0.0);
    $xx_swieph = array_fill(0, 6, 0.0);
    $serr = '';

    $ret_vsop = swe_calc($jd, $planet, Constants::SEFLG_VSOP87, $xx_vsop, $serr);
    $ret_swieph = swe_calc($jd, $planet, Constants::SEFLG_SWIEPH, $xx_swieph, $serr);

    if ($ret_vsop < 0 || $ret_swieph < 0) {
        echo "ERROR for $name: $serr\n";
        continue;
    }

    $err_lon = abs($xx_vsop[0] - $xx_swieph[0]) * 3600; // arcsec
    $err_lat = abs($xx_vsop[1] - $xx_swieph[1]) * 3600; // arcsec
    $err_dist = abs($xx_vsop[2] - $xx_swieph[2]) * 1.496e8; // km

    $results[$name] = [
        'lon_arcsec' => $err_lon,
        'lat_arcsec' => $err_lat,
        'dist_km' => $err_dist,
    ];
}

echo "=== VSOP87 Geocentric Accuracy (vs SWIEPH) ===\n";
echo "Planet    │ ΔLon (arcsec) │ ΔLat (arcsec) │ ΔDist (km)\n";
echo "──────────┼───────────────┼───────────────┼─────────────\n";

foreach ($results as $name => $errors) {
    printf(
        "%-9s │ %13.1f │ %13.1f │ %11.0f\n",
        $name,
        $errors['lon_arcsec'],
        $errors['lat_arcsec'],
        $errors['dist_km']
    );
}

// Check if all planets pass position accuracy test (< 36 arcsec)
$max_arcsec = 36.0;
$all_pass = true;

foreach ($results as $name => $errors) {
    if ($errors['lon_arcsec'] > $max_arcsec || $errors['lat_arcsec'] > $max_arcsec) {
        echo "\n✗ $name FAILED position accuracy (> 36 arcsec)\n";
        $all_pass = false;
    }
}

if ($all_pass) {
    echo "\n✓ All planets PASSED position accuracy test (< 36 arcsec)\n";
    exit(0);
} else {
    exit(1);
}
