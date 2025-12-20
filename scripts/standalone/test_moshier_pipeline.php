<?php

/**
 * Test Moshier pipeline with full LightTime transformations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\SwephFile\SwedState;
use Swisseph\Moshier\MoshierPlanetCalculator;
use Swisseph\Moshier\MoshierConstants;
use Swisseph\Constants;
use Swisseph\Swe\Planets\MoshierApparentPipeline;

// J2000.0
$jd = 2451545.0;

// Initialize SwedState
$swed = SwedState::getInstance();
$swed->ephePath = __DIR__ . '/data/ephe';

// Ensure oec2000 is calculated
$swed->oec2000->calculate(2451545.0);

// Test Mercury
echo "=== Testing moshplan() for Mercury at J2000.0 ===\n";

$xpret = null;
$xeret = null;
$serr = null;

$ret = MoshierPlanetCalculator::moshplan($jd, MoshierConstants::SEI_MERCURY, true, $xpret, $xeret, $serr);

if ($ret < 0) {
    echo "ERROR in moshplan: $serr\n";
    exit(1);
}

echo "Earth heliocentric equatorial J2000 (AU):\n";
$pedp = $swed->pldat[MoshierConstants::SEI_EARTH];
printf("  x=%.10f  y=%.10f  z=%.10f\n", $pedp->x[0], $pedp->x[1], $pedp->x[2]);
printf("  vx=%.10f vy=%.10f vz=%.10f AU/day\n", $pedp->x[3], $pedp->x[4], $pedp->x[5]);

echo "\nMercury heliocentric equatorial J2000 (AU):\n";
$pdp = $swed->pldat[MoshierConstants::SEI_MERCURY];
printf("  x=%.10f  y=%.10f  z=%.10f\n", $pdp->x[0], $pdp->x[1], $pdp->x[2]);
printf("  vx=%.10f vy=%.10f vz=%.10f AU/day\n", $pdp->x[3], $pdp->x[4], $pdp->x[5]);

// Test the full pipeline
echo "\n=== Testing MoshierApparentPipeline ===\n";

$iflag = Constants::SEFLG_MOSEPH | Constants::SEFLG_SPEED;
$ret = MoshierApparentPipeline::appPosEtcPlan(MoshierConstants::SEI_MERCURY, Constants::SE_MERCURY, $iflag, $serr);

if ($ret < 0) {
    echo "ERROR in pipeline: $serr\n";
    exit(1);
}

echo "Mercury apparent geocentric ecliptic:\n";
printf("  lon=%.6f°  lat=%.6f°  dist=%.10f AU\n",
    $pdp->xreturn[0], $pdp->xreturn[1], $pdp->xreturn[2]);
printf("  lon_speed=%.6f °/day\n", $pdp->xreturn[3]);

echo "\nMercury apparent equatorial:\n";
printf("  RA=%.6f°  Dec=%.6f°\n", $pdp->xreturn[12], $pdp->xreturn[13]);
printf("  RA_speed=%.6f °/day\n", $pdp->xreturn[15]);

// Compare with swetest64.exe reference
echo "\n=== Reference values from swetest64.exe (Moshier) ===\n";
echo "(Run: swetest64.exe -b1.1.2000 -p2 -fPZls -head -ephm)\n";
echo "Expected for Mercury at JD 2451545.0:\n";
echo "  lon ≈ 271.° (approximate, will get exact later)\n";

echo "\nDone!\n";
