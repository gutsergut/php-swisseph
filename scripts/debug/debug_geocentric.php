#!/usr/bin/env php
<?php
/**
 * Detailed debug of VSOP87 → geocentric conversion for Mercury
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Domain\Vsop87\VsopSegmentedLoader;
use Swisseph\Domain\Vsop87\Vsop87Calculator;
use Swisseph\Math;

// Setup ephemeris path
$ephe_path = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
swe_set_ephe_path($ephe_path);

$jd_tt = 2451545.0; // J2000.0

echo "=== STEP 1: VSOP87 Heliocentric Calculation ===\n";
$mercuryDir = __DIR__ . '/../data/vsop87/mercury';
$loader = new VsopSegmentedLoader();
$model = $loader->loadPlanet($mercuryDir);
$calc = new Vsop87Calculator();
[$Ldeg, $Bdeg, $Rau] = $calc->compute($model, $jd_tt);

printf("Mercury heliocentric (VSOP87):\n");
printf("  L = %.8f ° (%.8f rad)\n", $Ldeg, Math::degToRad($Ldeg));
printf("  B = %.8f ° (%.8f rad)\n", $Bdeg, Math::degToRad($Bdeg));
printf("  R = %.10f AU\n", $Rau);

// Convert to cartesian heliocentric
$lon = Math::degToRad($Ldeg);
$lat = Math::degToRad($Bdeg);
$xh = $Rau * cos($lat) * cos($lon);
$yh = $Rau * cos($lat) * sin($lon);
$zh = $Rau * sin($lat);
printf("\nHeliocentric cartesian:\n");
printf("  x = %.10f AU\n", $xh);
printf("  y = %.10f AU\n", $yh);
printf("  z = %.10f AU\n", $zh);

echo "\n=== STEP 2: Get Earth Barycentric Position ===\n";
// Force calculation of Earth
$xx_earth = array_fill(0, 6, 0.0);
$serr = '';
$ret = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
    $jd_tt,
    Constants::SE_EARTH,
    Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
    $xx_earth,
    $serr
);

$swed = \Swisseph\SwephFile\SwedState::getInstance();
$earth_pd = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_EARTH];
$sunb_pd = $swed->pldat[\Swisseph\SwephFile\SwephConstants::SEI_SUNBARY];

printf("Earth barycentric:\n");
printf("  x = %.10f AU\n", $earth_pd->x[0]);
printf("  y = %.10f AU\n", $earth_pd->x[1]);
printf("  z = %.10f AU\n", $earth_pd->x[2]);

printf("\nSun barycentric:\n");
printf("  x = %.10f AU\n", $sunb_pd->x[0]);
printf("  y = %.10f AU\n", $sunb_pd->x[1]);
printf("  z = %.10f AU\n", $sunb_pd->x[2]);

echo "\n=== STEP 3: Convert Heliocentric → Barycentric ===\n";
$xb_mercury = [
    $xh + $sunb_pd->x[0],
    $yh + $sunb_pd->x[1],
    $zh + $sunb_pd->x[2],
];
printf("Mercury barycentric:\n");
printf("  x = %.10f AU\n", $xb_mercury[0]);
printf("  y = %.10f AU\n", $xb_mercury[1]);
printf("  z = %.10f AU\n", $xb_mercury[2]);

echo "\n=== STEP 4: Convert Barycentric → Geocentric ===\n";
$xg_mercury = [
    $xb_mercury[0] - $earth_pd->x[0],
    $xb_mercury[1] - $earth_pd->x[1],
    $xb_mercury[2] - $earth_pd->x[2],
];
printf("Mercury geocentric:\n");
printf("  x = %.10f AU\n", $xg_mercury[0]);
printf("  y = %.10f AU\n", $xg_mercury[1]);
printf("  z = %.10f AU\n", $xg_mercury[2]);

// Convert back to spherical
$r_geo = sqrt($xg_mercury[0]**2 + $xg_mercury[1]**2 + $xg_mercury[2]**2);
$lon_geo_rad = atan2($xg_mercury[1], $xg_mercury[0]);
$lat_geo_rad = asin($xg_mercury[2] / $r_geo);
$lon_geo = Math::normAngleDeg(Math::radToDeg($lon_geo_rad));
$lat_geo = Math::radToDeg($lat_geo_rad);

printf("\nMercury geocentric spherical:\n");
printf("  lon = %.8f °\n", $lon_geo);
printf("  lat = %.8f °\n", $lat_geo);
printf("  dist = %.10f AU\n", $r_geo);

echo "\n=== STEP 5: Compare with SWIEPH (Expected) ===\n";
$xx_swieph = array_fill(0, 6, 0.0);
$serr = '';
\Swisseph\Swe\Functions\PlanetsFunctions::calc(
    $jd_tt,
    Constants::SE_MERCURY,
    Constants::SEFLG_SWIEPH,
    $xx_swieph,
    $serr
);
printf("SWIEPH geocentric:\n");
printf("  lon = %.8f °\n", $xx_swieph[0]);
printf("  lat = %.8f °\n", $xx_swieph[1]);
printf("  dist = %.10f AU\n", $xx_swieph[2]);

$diff_lon = abs($xx_swieph[0] - $lon_geo);
$diff_lat = abs($xx_swieph[1] - $lat_geo);
$diff_dist = abs($xx_swieph[2] - $r_geo);

printf("\nDifferences (basic conversion, no corrections):\n");
printf("  Δlon = %.4f ° (%.1f\")\n", $diff_lon, $diff_lon * 3600);
printf("  Δlat = %.4f ° (%.1f\")\n", $diff_lat, $diff_lat * 3600);
printf("  Δdist = %.6f AU (%.0f km)\n", $diff_dist, $diff_dist * 149597870.7);

echo "\n=== STEP 6: Test with VSOP87Strategy (full pipeline) ===\n";
$xx_vsop = array_fill(0, 6, 0.0);
$serr = '';
\Swisseph\Swe\Functions\PlanetsFunctions::calc(
    $jd_tt,
    Constants::SE_MERCURY,
    Constants::SEFLG_VSOP87,
    $xx_vsop,
    $serr
);
printf("VSOP87Strategy output:\n");
printf("  lon = %.8f °\n", $xx_vsop[0]);
printf("  lat = %.8f °\n", $xx_vsop[1]);
printf("  dist = %.10f AU\n", $xx_vsop[2]);

$diff_lon_vs = abs($xx_swieph[0] - $xx_vsop[0]);
$diff_lat_vs = abs($xx_swieph[1] - $xx_vsop[1]);
$diff_dist_vs = abs($xx_swieph[2] - $xx_vsop[2]);

printf("\nVSOP87 vs SWIEPH:\n");
printf("  Δlon = %.4f ° (%.1f\")\n", $diff_lon_vs, $diff_lon_vs * 3600);
printf("  Δlat = %.4f ° (%.1f\")\n", $diff_lat_vs, $diff_lat_vs * 3600);
printf("  Δdist = %.6f AU (%.0f km)\n", $diff_dist_vs, $diff_dist_vs * 149597870.7);
