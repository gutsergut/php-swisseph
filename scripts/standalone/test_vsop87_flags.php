<?php
/**
 * Test VSOP87 coordinate transformations with different flags
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2451545.0; // J2000.0
$planet = Constants::SE_SATURN;

echo "=== VSOP87 Saturn Coordinate Systems ===\n\n";

// 1. Geocentric ecliptic (default)
$xx_geo = array_fill(0, 6, 0.0);
$serr = '';
swe_calc($jd, $planet, Constants::SEFLG_VSOP87, $xx_geo, $serr);
printf("Geocentric Ecliptic:\n");
printf("  Lon: %10.6f°  Lat: %9.6f°  Dist: %11.9f AU\n", $xx_geo[0], $xx_geo[1], $xx_geo[2]);
printf("  Vel: %10.6f°/d %9.6f°/d %11.9f AU/d\n\n", $xx_geo[3], $xx_geo[4], $xx_geo[5]);

// 2. Geocentric equatorial
$xx_eq = array_fill(0, 6, 0.0);
swe_calc($jd, $planet, Constants::SEFLG_VSOP87 | Constants::SEFLG_EQUATORIAL, $xx_eq, $serr);
printf("Geocentric Equatorial:\n");
printf("  RA:  %10.6f°  Dec: %9.6f°  Dist: %11.9f AU\n", $xx_eq[0], $xx_eq[1], $xx_eq[2]);
printf("  Vel: %10.6f°/d %9.6f°/d %11.9f AU/d\n\n", $xx_eq[3], $xx_eq[4], $xx_eq[5]);

// 3. Heliocentric ecliptic
$xx_helio = array_fill(0, 6, 0.0);
swe_calc($jd, $planet, Constants::SEFLG_VSOP87 | Constants::SEFLG_HELCTR, $xx_helio, $serr);
printf("Heliocentric Ecliptic:\n");
printf("  Lon: %10.6f°  Lat: %9.6f°  Dist: %11.9f AU\n", $xx_helio[0], $xx_helio[1], $xx_helio[2]);
printf("  Vel: %10.6f°/d %9.6f°/d %11.9f AU/d\n\n", $xx_helio[3], $xx_helio[4], $xx_helio[5]);

// 4. Heliocentric equatorial
$xx_helio_eq = array_fill(0, 6, 0.0);
swe_calc($jd, $planet, Constants::SEFLG_VSOP87 | Constants::SEFLG_HELCTR | Constants::SEFLG_EQUATORIAL, $xx_helio_eq, $serr);
printf("Heliocentric Equatorial:\n");
printf("  RA:  %10.6f°  Dec: %9.6f°  Dist: %11.9f AU\n", $xx_helio_eq[0], $xx_helio_eq[1], $xx_helio_eq[2]);
printf("  Vel: %10.6f°/d %9.6f°/d %11.9f AU/d\n\n", $xx_helio_eq[3], $xx_helio_eq[4], $xx_helio_eq[5]);

// 5. Barycentric equatorial
$xx_bary = array_fill(0, 6, 0.0);
swe_calc($jd, $planet, Constants::SEFLG_VSOP87 | Constants::SEFLG_BARYCTR, $xx_bary, $serr);
printf("Barycentric Equatorial:\n");
printf("  RA:  %10.6f°  Dec: %9.6f°  Dist: %11.9f AU\n", $xx_bary[0], $xx_bary[1], $xx_bary[2]);
printf("  Vel: %10.6f°/d %9.6f°/d %11.9f AU/d\n\n", $xx_bary[3], $xx_bary[4], $xx_bary[5]);

// Compare with SWIEPH
echo "=== Comparison with SWIEPH ===\n\n";

$tests = [
    ['name' => 'Geocentric', 'flags' => 0, 'vsop' => $xx_geo],
    ['name' => 'Equatorial', 'flags' => Constants::SEFLG_EQUATORIAL, 'vsop' => $xx_eq],
    ['name' => 'Heliocentric', 'flags' => Constants::SEFLG_HELCTR, 'vsop' => $xx_helio],
];

foreach ($tests as $test) {
    $xx_ref = array_fill(0, 6, 0.0);
    swe_calc($jd, $planet, Constants::SEFLG_SWIEPH | $test['flags'], $xx_ref, $serr);

    $err_0 = abs($test['vsop'][0] - $xx_ref[0]) * 3600;
    $err_1 = abs($test['vsop'][1] - $xx_ref[1]) * 3600;

    printf("%s: Δ0=%.1f″  Δ1=%.1f″\n", $test['name'], $err_0, $err_1);
}
