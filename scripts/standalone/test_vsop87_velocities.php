<?php
/**
 * Validate VSOP87 velocity calculations
 * Compare computed velocities with numerical derivatives
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2451545.0;
$dt = 0.01; // days

$planets = [
    'Mercury' => Constants::SE_MERCURY,
    'Venus' => Constants::SE_VENUS,
    'Saturn' => Constants::SE_SATURN,
];

echo "=== VSOP87 Velocity Validation ===\n";
echo "Comparing computed velocities with numerical derivatives\n\n";

foreach ($planets as $name => $ipl) {
    echo "--- $name ---\n";

    // Get position and velocity at t
    $xx_t = array_fill(0, 6, 0.0);
    $serr = '';
    swe_calc($jd, $ipl, Constants::SEFLG_VSOP87, $xx_t, $serr);

    // Get positions at t-dt and t+dt for numerical derivative
    $xx_minus = array_fill(0, 6, 0.0);
    $xx_plus = array_fill(0, 6, 0.0);
    swe_calc($jd - $dt, $ipl, Constants::SEFLG_VSOP87, $xx_minus, $serr);
    swe_calc($jd + $dt, $ipl, Constants::SEFLG_VSOP87, $xx_plus, $serr);

    // Numerical derivatives (central difference)
    $v_lon_num = ($xx_plus[0] - $xx_minus[0]) / (2 * $dt);
    $v_lat_num = ($xx_plus[1] - $xx_minus[1]) / (2 * $dt);
    $v_dist_num = ($xx_plus[2] - $xx_minus[2]) / (2 * $dt);

    // Handle longitude wrap-around
    if (abs($v_lon_num) > 180) {
        if ($v_lon_num > 0) {
            $v_lon_num -= 360 / (2 * $dt);
        } else {
            $v_lon_num += 360 / (2 * $dt);
        }
    }

    // Computed velocities
    $v_lon_comp = $xx_t[3];
    $v_lat_comp = $xx_t[4];
    $v_dist_comp = $xx_t[5];

    // Errors
    $err_lon = abs($v_lon_comp - $v_lon_num);
    $err_lat = abs($v_lat_comp - $v_lat_num);
    $err_dist = abs($v_dist_comp - $v_dist_num);

    printf("Longitude velocity:\n");
    printf("  Computed:  %+.9f °/day\n", $v_lon_comp);
    printf("  Numerical: %+.9f °/day\n", $v_lon_num);
    printf("  Error:     %.9f °/day (%.3f arcsec/day)\n", $err_lon, $err_lon * 3600);

    printf("Latitude velocity:\n");
    printf("  Computed:  %+.9f °/day\n", $v_lat_comp);
    printf("  Numerical: %+.9f °/day\n", $v_lat_num);
    printf("  Error:     %.9f °/day (%.3f arcsec/day)\n", $err_lat, $err_lat * 3600);

    printf("Distance velocity:\n");
    printf("  Computed:  %+.9f AU/day\n", $v_dist_comp);
    printf("  Numerical: %+.9f AU/day\n", $v_dist_num);
    printf("  Error:     %.9f AU/day (%.0f km/day)\n\n", $err_dist, abs($err_dist * 1.496e8));

    // Check if errors are acceptable
    $ok_lon = $err_lon < 0.001; // <3.6 arcsec/day
    $ok_lat = $err_lat < 0.001;
    $ok_dist = $err_dist < 0.0001; // <15000 km/day

    $status = ($ok_lon && $ok_lat && $ok_dist) ? '✓ PASS' : '✗ FAIL';
    echo "Status: $status\n\n";
}
