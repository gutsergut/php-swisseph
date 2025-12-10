<?php
/**
 * Validate VSOP87 velocity calculations in Cartesian coordinates
 * This is the correct way to validate - spherical velocities have nonlinear transformations
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2451545.0;
$dt = 0.001; // small dt for better numerical accuracy

$planets = [
    'Mercury' => Constants::SE_MERCURY,
    'Venus' => Constants::SE_VENUS,
    'Saturn' => Constants::SE_SATURN,
    'Uranus' => Constants::SE_URANUS,
];

echo "=== VSOP87 Cartesian Velocity Validation ===\n";
echo "Comparing computed velocities with numerical derivatives\n";
echo "Using XYZ rectangular coordinates (correct method)\n\n";

foreach ($planets as $name => $ipl) {
    echo "--- $name ---\n";

    // Get XYZ position and velocity at t
    $xx_t = array_fill(0, 6, 0.0);
    $serr = '';
    swe_calc($jd, $ipl, Constants::SEFLG_VSOP87 | Constants::SEFLG_XYZ, $xx_t, $serr);

    // Get XYZ positions at t-dt and t+dt for numerical derivative
    $xx_minus = array_fill(0, 6, 0.0);
    $xx_plus = array_fill(0, 6, 0.0);
    swe_calc($jd - $dt, $ipl, Constants::SEFLG_VSOP87 | Constants::SEFLG_XYZ, $xx_minus, $serr);
    swe_calc($jd + $dt, $ipl, Constants::SEFLG_VSOP87 | Constants::SEFLG_XYZ, $xx_plus, $serr);

    // Numerical derivatives (central difference)
    $vx_num = ($xx_plus[0] - $xx_minus[0]) / (2 * $dt);
    $vy_num = ($xx_plus[1] - $xx_minus[1]) / (2 * $dt);
    $vz_num = ($xx_plus[2] - $xx_minus[2]) / (2 * $dt);

    // Computed velocities
    $vx_comp = $xx_t[3];
    $vy_comp = $xx_t[4];
    $vz_comp = $xx_t[5];

    // Errors
    $err_x = abs($vx_comp - $vx_num);
    $err_y = abs($vy_comp - $vy_num);
    $err_z = abs($vz_comp - $vz_num);

    $err_total = sqrt($err_x*$err_x + $err_y*$err_y + $err_z*$err_z);

    printf("X velocity:\n");
    printf("  Computed:  %+.12f AU/day\n", $vx_comp);
    printf("  Numerical: %+.12f AU/day\n", $vx_num);
    printf("  Error:     %.12f AU/day (%.0f km/day)\n", $err_x, abs($err_x * 1.496e8));

    printf("Y velocity:\n");
    printf("  Computed:  %+.12f AU/day\n", $vy_comp);
    printf("  Numerical: %+.12f AU/day\n", $vy_num);
    printf("  Error:     %.12f AU/day (%.0f km/day)\n", $err_y, abs($err_y * 1.496e8));

    printf("Z velocity:\n");
    printf("  Computed:  %+.12f AU/day\n", $vz_comp);
    printf("  Numerical: %+.12f AU/day\n", $vz_num);
    printf("  Error:     %.12f AU/day (%.0f km/day)\n", $err_z, abs($err_z * 1.496e8));

    printf("Total vector error: %.12f AU/day (%.0f km/day)\n", $err_total, $err_total * 1.496e8);

    // Check if errors are acceptable (<1000 km/day per component)
    $ok_x = $err_x < 0.00001; // <1500 km/day
    $ok_y = $err_y < 0.00001;
    $ok_z = $err_z < 0.00001;

    $status = ($ok_x && $ok_y && $ok_z) ? '✓ PASS' : '⚠ MARGINAL';
    if ($err_total > 0.0001) $status = '✗ FAIL';

    echo "Status: $status\n\n";
}
