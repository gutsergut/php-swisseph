<?php

declare(strict_types=1);

/**
 * Test Moon with HELCTR flag to understand the calculation
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2460677.0;
$iflag = Constants::SEFLG_SWIEPH;
$iflagp = $iflag | Constants::SEFLG_HELCTR;

echo "Testing Moon coordinates with different flags\n";
echo str_repeat('=', 80) . "\n\n";

// Geocentric
$xx_geo = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $iflag | Constants::SEFLG_XYZ, $xx_geo, $serr);
echo "Geocentric (Earth-centered) Moon XYZ:\n";
printf("  X = %.12f\n", $xx_geo[0]);
printf("  Y = %.12f\n", $xx_geo[1]);
printf("  Z = %.12f\n", $xx_geo[2]);
$r_geo = sqrt($xx_geo[0]**2 + $xx_geo[1]**2 + $xx_geo[2]**2);
printf("  Distance = %.12f AU\n\n", $r_geo);

// Heliocentric
$xx_helio = [];
swe_calc($jd, Constants::SE_MOON, $iflagp | Constants::SEFLG_XYZ, $xx_helio, $serr);
echo "Heliocentric (Sun-centered) Moon XYZ:\n";
printf("  X = %.12f\n", $xx_helio[0]);
printf("  Y = %.12f\n", $xx_helio[1]);
printf("  Z = %.12f\n", $xx_helio[2]);
$r_helio = sqrt($xx_helio[0]**2 + $xx_helio[1]**2 + $xx_helio[2]**2);
printf("  Distance = %.12f AU\n\n", $r_helio);

// Light time
$dt = $r_geo * Constants::AUNIT / Constants::CLIGHT / 86400.0;
printf("Light time: %.12f days (%.6f seconds)\n\n", $dt, $dt * 86400);

// Heliocentric at JD - dt
$xx_helio_dt = [];
swe_calc($jd - $dt, Constants::SE_MOON, $iflagp | Constants::SEFLG_XYZ, $xx_helio_dt, $serr);
echo "Heliocentric Moon XYZ at (JD - light_time):\n";
printf("  X = %.12f\n", $xx_helio_dt[0]);
printf("  Y = %.12f\n", $xx_helio_dt[1]);
printf("  Z = %.12f\n", $xx_helio_dt[2]);
$r_helio_dt = sqrt($xx_helio_dt[0]**2 + $xx_helio_dt[1]**2 + $xx_helio_dt[2]**2);
printf("  Distance = %.12f AU\n\n", $r_helio_dt);

// Phase angle calculation
$dot = $xx_geo[0]*$xx_helio_dt[0] + $xx_geo[1]*$xx_helio_dt[1] + $xx_geo[2]*$xx_helio_dt[2];
$cos_angle = $dot / ($r_geo * $r_helio_dt);
$phase_angle = rad2deg(acos($cos_angle));

echo "Phase angle calculation (geocentric · heliocentric_at_t-dt):\n";
printf("  dot product = %.12f\n", $dot);
printf("  cos(angle) = %.12f\n", $cos_angle);
printf("  phase angle = %.12f°\n\n", $phase_angle);

echo "Expected from swetest: 160.1574°\n";
printf("Difference: %.6f° (%.3f arcseconds)\n", abs($phase_angle - 160.1574), abs($phase_angle - 160.1574) * 3600);
