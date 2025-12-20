<?php
/**
 * Test SEFLG_BARYCTR for planets
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\Functions\PlanetsFunctions;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2460000.5;  // 25.2.2023 12:00 TT
$serr = '';

echo "=== Test SEFLG_BARYCTR ===\n";
echo "JD = {$tjd}\n\n";

// Test Mercury barycentric TRUEPOS (no light-time)
echo "=== Mercury BARYCTR + TRUEPOS ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS;
$xx = array_fill(0, 6, 0.0);

$ret = PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag, $xx, $serr);

echo "  PHP: x = " . sprintf("%.9f", $xx[0]) . "\n";
echo "  PHP: y = " . sprintf("%.9f", $xx[1]) . "\n";
echo "  PHP: z = " . sprintf("%.9f", $xx[2]) . "\n";
echo "\n";
echo "  swetest ref (SWIEPH, TRUEPOS):\n";
echo "  C:   x = 0.095251399\n";
echo "  C:   y = -0.441060814\n";
echo "  C:   z = -0.045199093\n";
echo "\n";

// Test Mercury barycentric (with light-time)
echo "=== Mercury BARYCTR (apparent) ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_XYZ;
$xx = array_fill(0, 6, 0.0);

$ret = PlanetsFunctions::calc($tjd, Constants::SE_MERCURY, $iflag, $xx, $serr);

echo "  PHP: x = " . sprintf("%.9f", $xx[0]) . "\n";
echo "  PHP: y = " . sprintf("%.9f", $xx[1]) . "\n";
echo "  PHP: z = " . sprintf("%.9f", $xx[2]) . "\n";
echo "\n";
echo "  swetest ref (SWIEPH):\n";
echo "  C:   x = 0.095194482\n";
echo "  C:   y = -0.441081431\n";
echo "  C:   z = -0.045195524\n";
echo "\n";

// Test Sun barycentric
echo "=== Sun SEFLG_BARYCTR ===\n";
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_XYZ;
$xx_sun = array_fill(0, 6, 0.0);
$ret = PlanetsFunctions::calc($tjd, Constants::SE_SUN, $iflag, $xx_sun, $serr);
echo "  PHP: x = " . sprintf("%.9f", $xx_sun[0]) . "\n";
echo "  PHP: y = " . sprintf("%.9f", $xx_sun[1]) . "\n";
echo "  PHP: z = " . sprintf("%.9f", $xx_sun[2]) . "\n";
echo "\n";
echo "  swetest ref (SWIEPH):\n";
echo "  C:   x = -0.008981057\n";
echo "  C:   y = -0.000445411\n";
echo "  C:   z = 0.000212362\n";
if ($serr) {
    echo "Error: $serr\n";
}
