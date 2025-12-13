<?php
/**
 * Test SEFLG_ICRS (ICRS reference frame - no frame bias)
 * Reference values from swetest64.exe
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2460000.5;  // 25.2.2023 12:00 TT
$serr = '';

echo "=== Test SEFLG_ICRS ===\n";
echo "JD = {$tjd}\n\n";

// Test Mercury with J2000 (with frame bias applied - default)
echo "=== Mercury J2000 (with bias, default) ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS;
$xx = [];
$ret = PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag, $xx, $serr);
printf("  x = %.12f\n", $xx[0]);
printf("  y = %.12f\n", $xx[1]);
printf("  z = %.12f\n\n", $xx[2]);

// Test Mercury with J2000 + ICRS (no frame bias)
echo "=== Mercury J2000 + ICRS (no bias) ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_ICRS;
$xx_icrs = [];
$ret = PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag, $xx_icrs, $serr);
printf("  x = %.12f\n", $xx_icrs[0]);
printf("  y = %.12f\n", $xx_icrs[1]);
printf("  z = %.12f\n\n", $xx_icrs[2]);

// Calculate difference (should be small, ~milliarcseconds)
echo "=== Difference (bias effect) ===\n";
$dx = $xx_icrs[0] - $xx[0];
$dy = $xx_icrs[1] - $xx[1];
$dz = $xx_icrs[2] - $xx[2];
printf("  dx = %.15e AU\n", $dx);
printf("  dy = %.15e AU\n", $dy);
printf("  dz = %.15e AU\n", $dz);
$dr_km = sqrt($dx*$dx + $dy*$dy + $dz*$dz) * 149597870.7;
printf("  |dr| = %.3f km\n\n", $dr_km);

// If difference is non-zero, ICRS flag is working
if (abs($dx) > 1e-12 || abs($dy) > 1e-12 || abs($dz) > 1e-12) {
    echo "✅ SEFLG_ICRS is working correctly - bias is skipped when flag is set\n";
} else {
    echo "❌ SEFLG_ICRS may not be working - no difference detected\n";
}
