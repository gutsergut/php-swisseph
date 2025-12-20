<?php
/**
 * Check coordinate system in pldat
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Calculate Mercury (this will also calculate Earth)
$xx = [];
$serr = '';
swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED, $xx, $serr);

$swed = SwedState::getInstance();
$pedp = $swed->pldat[SwephConstants::SEI_EARTH];
$psdp = $swed->pldat[SwephConstants::SEI_SUNBARY];

echo "=== SWIEPH Earth position in pldat[SEI_EARTH].x ===\n";
printf("x[0..5] = [%.15f, %.15f, %.15f, %.15f, %.15f, %.15f]\n",
    $pedp->x[0], $pedp->x[1], $pedp->x[2], $pedp->x[3], $pedp->x[4], $pedp->x[5]);

// Check if this is ecliptic or equatorial
// For ecliptic J2000: z should be near ecliptic plane (small)
// For equatorial J2000: y should be different pattern

// Earth position at J2000.0:
// Equatorial: x ≈ -0.18, y ≈ 0.88, z ≈ 0.38 (significant z due to Earth's tilt)
// Ecliptic: x ≈ -0.18, y ≈ 0.97, z ≈ 0.00 (z near zero in ecliptic plane)

echo "\nCheck: if |z| << |y|, it's ecliptic; if z ~ 0.38, it's equatorial\n";
printf("Ratio |z/y| = %.4f\n", abs($pedp->x[2] / $pedp->x[1]));

echo "\n=== Expected values for Earth at J2000.0 ===\n";
echo "Ecliptic J2000: x=-0.18, y=0.97, z≈0.00\n";
echo "Equatorial J2000: x=-0.18, y=0.88, z=0.38\n";

// Also check Sun barycentric
echo "\n=== SWIEPH SunBary position in pldat[SEI_SUNBARY].x ===\n";
printf("x[0..5] = [%.15f, %.15f, %.15f, %.15f, %.15f, %.15f]\n",
    $psdp->x[0], $psdp->x[1], $psdp->x[2], $psdp->x[3], $psdp->x[4], $psdp->x[5]);

// Sun barycentric at J2000.0:
// Should be very small (Sun is near barycenter)
// Equatorial: similar pattern with z ≠ 0
// Ecliptic: z ≈ 0
