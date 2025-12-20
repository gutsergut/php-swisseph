#!/usr/bin/env php
<?php
/**
 * Test coordinate transformation hypothesis
 */

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Coordinates;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0;
$ipl = Constants::SE_JUPITER;
// Use EQUATORIAL flag to get equatorial XYZ (not ecliptic)
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ |
         Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL;echo "Testing coordinate transformation hypothesis\n";
echo "=============================================\n\n";

// Get Jupiter barycentric (equatorial)
$xx_jupiter_bary = [];
$serr = null;
swe_calc($jd, $ipl, $iflag | Constants::SEFLG_BARYCTR, $xx_jupiter_bary, $serr);

// Get Earth barycentric (equatorial) - use separate array!
$swed = SwedState::getInstance();
$xx_earth_temp = [];
swe_calc($jd, Constants::SE_EARTH, $iflag | Constants::SEFLG_BARYCTR, $xx_earth_temp, $serr);
$pedp = $swed->pldat[SwephConstants::SEI_EARTH];

echo "1. Jupiter barycentric (EQUATORIAL J2000 XYZ):\n";
printf("   X=%.10f, Y=%.10f, Z=%.10f\n\n",
    $xx_jupiter_bary[0], $xx_jupiter_bary[1], $xx_jupiter_bary[2]);

echo "2. Earth barycentric (EQUATORIAL J2000 XYZ from SwedState):\n";
printf("   X=%.10f, Y=%.10f, Z=%.10f\n\n",
    $pedp->x[0], $pedp->x[1], $pedp->x[2]);

// Manual geocentric (still equatorial)
$xx_geo_equatorial = [
    $xx_jupiter_bary[0] - $pedp->x[0],
    $xx_jupiter_bary[1] - $pedp->x[1],
    $xx_jupiter_bary[2] - $pedp->x[2]
];

echo "3. Geocentric (EQUATORIAL J2000 XYZ) = Jupiter - Earth:\n";
printf("   X=%.10f, Y=%.10f, Z=%.10f\n\n",
    $xx_geo_equatorial[0], $xx_geo_equatorial[1], $xx_geo_equatorial[2]);

// Transform equatorial → ecliptic
$eps = 0.40909280422232897; // J2000 obliquity
$seps = sin($eps);
$ceps = cos($eps);

$xx_geo_ecliptic = [];
Coordinates::coortrf2($xx_geo_equatorial, $xx_geo_ecliptic, $seps, $ceps);

echo "4. Geocentric (ECLIPTIC J2000 XYZ) = coortrf2(equatorial):\n";
printf("   X=%.10f, Y=%.10f, Z=%.10f\n\n",
    $xx_geo_ecliptic[0], $xx_geo_ecliptic[1], $xx_geo_ecliptic[2]);

// Get from swe_calc WITHOUT EQUATORIAL flag (to get ECLIPTIC XYZ)
$xx_geo_swe = [];
$iflag_ecliptic = $iflag & ~Constants::SEFLG_EQUATORIAL; // Remove EQUATORIAL flag
swe_calc($jd, $ipl, $iflag_ecliptic, $xx_geo_swe, $serr);

echo "5. Geocentric from swe_calc (ECLIPTIC J2000 XYZ):\n";
printf("   X=%.10f, Y=%.10f, Z=%.10f\n\n",
    $xx_geo_swe[0], $xx_geo_swe[1], $xx_geo_swe[2]);

// Compare
echo "6. DIFFERENCE (swe_calc - manual+transform):\n";
printf("   ΔX = %.15e AU (%.3f meters)\n",
    $xx_geo_swe[0] - $xx_geo_ecliptic[0],
    ($xx_geo_swe[0] - $xx_geo_ecliptic[0]) * 149597870700);
printf("   ΔY = %.15e AU (%.3f meters)\n",
    $xx_geo_swe[1] - $xx_geo_ecliptic[1],
    ($xx_geo_swe[1] - $xx_geo_ecliptic[1]) * 149597870700);
printf("   ΔZ = %.15e AU (%.3f meters)\n",
    $xx_geo_swe[2] - $xx_geo_ecliptic[2],
    ($xx_geo_swe[2] - $xx_geo_ecliptic[2]) * 149597870700);

$dist = sqrt(
    pow($xx_geo_swe[0] - $xx_geo_ecliptic[0], 2) +
    pow($xx_geo_swe[1] - $xx_geo_ecliptic[1], 2) +
    pow($xx_geo_swe[2] - $xx_geo_ecliptic[2], 2)
);
printf("\n   Total 3D distance: %.15e AU (%.3f meters)\n",
    $dist, $dist * 149597870700);
