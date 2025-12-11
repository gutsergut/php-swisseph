<?php

declare(strict_types=1);

/**
 * Debug test for swe_pheno() - compare intermediate values
 *
 * This test helps identify where differences arise between PHP and C
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2460677.0;  // 2025-01-01 12:00 TT
$iflag = Constants::SEFLG_SWIEPH;

echo "Debugging swe_pheno() calculations\n";
echo "Date: JD $jd (2025-01-01 12:00 TT)\n";
echo str_repeat('=', 100) . "\n\n";

// Test Moon in detail
$ipl = Constants::SE_MOON;
echo "MOON DETAILED ANALYSIS:\n";
echo str_repeat('-', 100) . "\n";

// Get geocentric coordinates
$xx_geo = [];
$serr = '';
swe_calc($jd, $ipl, $iflag | Constants::SEFLG_XYZ, $xx_geo, $serr);
echo "Geocentric XYZ:\n";
printf("  X = %.12f\n", $xx_geo[0]);
printf("  Y = %.12f\n", $xx_geo[1]);
printf("  Z = %.12f\n", $xx_geo[2]);

$lbr_geo = [];
swe_calc($jd, $ipl, $iflag, $lbr_geo, $serr);
echo "\nGeocentric spherical:\n";
printf("  Longitude = %.12f°\n", $lbr_geo[0]);
printf("  Latitude  = %.12f°\n", $lbr_geo[1]);
printf("  Distance  = %.12f AU\n", $lbr_geo[2]);

// Calculate light time
$dt = $lbr_geo[2] * Constants::AUNIT / Constants::CLIGHT / 86400.0;
echo "\nLight time:\n";
printf("  dt = %.12f days (%.6f seconds)\n", $dt, $dt * 86400);

// Get heliocentric coordinates at tjd - dt
$xx_helio = [];
swe_calc($jd - $dt, $ipl, ($iflag | Constants::SEFLG_HELCTR | Constants::SEFLG_XYZ), $xx_helio, $serr);
echo "\nHeliocentric XYZ (at JD - dt):\n";
printf("  X = %.12f\n", $xx_helio[0]);
printf("  Y = %.12f\n", $xx_helio[1]);
printf("  Z = %.12f\n", $xx_helio[2]);

$lbr_helio = [];
swe_calc($jd - $dt, $ipl, ($iflag | Constants::SEFLG_HELCTR), $lbr_helio, $serr);
echo "\nHeliocentric spherical (at JD - dt):\n";
printf("  Longitude = %.12f°\n", $lbr_helio[0]);
printf("  Latitude  = %.12f°\n", $lbr_helio[1]);
printf("  Distance  = %.12f AU\n", $lbr_helio[2]);

// Calculate dot product manually
$dot = $xx_geo[0] * $xx_helio[0] + $xx_geo[1] * $xx_helio[1] + $xx_geo[2] * $xx_helio[2];
$len_geo = sqrt($xx_geo[0]**2 + $xx_geo[1]**2 + $xx_geo[2]**2);
$len_helio = sqrt($xx_helio[0]**2 + $xx_helio[1]**2 + $xx_helio[2]**2);
$cos_angle = $dot / ($len_geo * $len_helio);

echo "\nPhase angle calculation:\n";
printf("  dot product = %.12f\n", $dot);
printf("  |geo| = %.12f\n", $len_geo);
printf("  |helio| = %.12f\n", $len_helio);
printf("  cos(angle) = %.12f\n", $cos_angle);
printf("  phase angle = %.12f° (via acos)\n", rad2deg(acos($cos_angle)));

// Now call swe_pheno and compare
$attr = [];
swe_pheno($jd, $ipl, $iflag, $attr, $serr);

echo "\nswe_pheno() result:\n";
printf("  Phase angle = %.12f°\n", $attr[0]);
printf("  Phase = %.12f\n", $attr[1]);
printf("  Elongation = %.12f°\n", $attr[2]);
printf("  Diameter = %.12f°\n", $attr[3]);
printf("  Magnitude = %.6f\n", $attr[4]);

echo "\n" . str_repeat('=', 100) . "\n";
echo "\nNow let's get the same data from swetest64...\n";
echo "Run: swetest64.exe -j2460677 -p1 -fLbR -head\n";
echo "Expected geocentric: ~300.65°, -4.35°, 0.002540815 AU\n\n";

// Let's also check if using TRUEPOS flag makes difference
echo "Testing with SEFLG_TRUEPOS (astrometric positions):\n";
$iflag_true = $iflag | Constants::SEFLG_TRUEPOS;
$attr_true = [];
swe_pheno($jd, $ipl, $iflag_true, $attr_true, $serr);
printf("  Phase angle (TRUEPOS) = %.12f°\n", $attr_true[0]);
printf("  Difference = %.12f°\n", abs($attr[0] - $attr_true[0]));
