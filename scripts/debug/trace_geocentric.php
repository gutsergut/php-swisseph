#!/usr/bin/env php
<?php
/**
 * Detailed trace of geocentric coordinate calculation
 * Outputs ALL intermediate values for comparison with C
 */

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$jd = 2451545.0; // J2000.0
$ipl = Constants::SE_JUPITER;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ |
         Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS;

echo "=============================================================\n";
echo "GEOCENTRIC COORDINATE TRACE for Jupiter at J2000.0\n";
echo "=============================================================\n\n";

echo "Input parameters:\n";
echo "  JD = $jd\n";
echo "  Planet = Jupiter (ipl=$ipl)\n";
printf("  iflag = 0x%X\n", $iflag);
echo "  Flags: SWIEPH | J2000 | XYZ | SPEED | TRUEPOS\n\n";

// Step 1: Calculate Jupiter barycentric
echo "STEP 1: Calculate Jupiter (barycentric)\n";
echo "========================================\n";
$xx_jupiter_bary = [];
$serr = null;
$rc = swe_calc($jd, $ipl, $iflag | Constants::SEFLG_BARYCTR, $xx_jupiter_bary, $serr);

if ($rc < 0) {
    die("ERROR: $serr\n");
}

printf("Jupiter barycentric (from swe_calc with BARYCTR):\n");
printf("  X = %.15f AU\n", $xx_jupiter_bary[0]);
printf("  Y = %.15f AU\n", $xx_jupiter_bary[1]);
printf("  Z = %.15f AU\n", $xx_jupiter_bary[2]);
printf("  VX = %.15f AU/day\n", $xx_jupiter_bary[3]);
printf("  VY = %.15f AU/day\n", $xx_jupiter_bary[4]);
printf("  VZ = %.15f AU/day\n\n", $xx_jupiter_bary[5]);

// Step 2: Get internal state for Jupiter
echo "STEP 2: Access internal SwedState for Jupiter\n";
echo "==============================================\n";
$swed = SwedState::getInstance();
$ipli_jupiter = SwephConstants::PNOEXT2INT[$ipl];
$pdp_jupiter = $swed->pldat[$ipli_jupiter];

printf("Jupiter internal data (pdp):\n");
printf("  teval = %.10f\n", $pdp_jupiter->teval);
printf("  iephe = 0x%X\n", $pdp_jupiter->iephe);
printf("  iflg = 0x%X\n", $pdp_jupiter->iflg);
printf("  ncoe = %d\n", $pdp_jupiter->ncoe);
printf("  neval = %d\n\n", $pdp_jupiter->neval);

// Step 3: Get Earth barycentric (from internal state)
echo "STEP 3: Get Earth barycentric coordinates\n";
echo "==========================================\n";

// Force calculation of Earth
$xx_earth_dummy = [];
$rc_earth = swe_calc($jd, Constants::SE_EARTH, $iflag | Constants::SEFLG_BARYCTR, $xx_earth_dummy, $serr);

$pedp = $swed->pldat[SwephConstants::SEI_EARTH];
printf("Earth barycentric (from SwedState after swe_calc):\n");
printf("  X = %.15f AU\n", $pedp->x[0]);
printf("  Y = %.15f AU\n", $pedp->x[1]);
printf("  Z = %.15f AU\n", $pedp->x[2]);
printf("  VX = %.15f AU/day\n", $pedp->x[3]);
printf("  VY = %.15f AU/day\n", $pedp->x[4]);
printf("  VZ = %.15f AU/day\n", $pedp->x[5]);
printf("  teval = %.10f\n\n", $pedp->teval);

// Step 4: Manual geocentric calculation
echo "STEP 4: Manual geocentric calculation (Jupiter - Earth)\n";
echo "========================================================\n";
$xx_geo_manual = [];
for ($i = 0; $i <= 5; $i++) {
    $xx_geo_manual[$i] = $xx_jupiter_bary[$i] - $pedp->x[$i];
}

printf("Geocentric (manual calculation):\n");
printf("  X = %.15f AU\n", $xx_geo_manual[0]);
printf("  Y = %.15f AU\n", $xx_geo_manual[1]);
printf("  Z = %.15f AU\n", $xx_geo_manual[2]);
printf("  VX = %.15f AU/day\n", $xx_geo_manual[3]);
printf("  VY = %.15f AU/day\n", $xx_geo_manual[4]);
printf("  VZ = %.15f AU/day\n\n", $xx_geo_manual[5]);

// Step 5: Get geocentric from swe_calc (without BARYCTR/HELCTR)
echo "STEP 5: Get geocentric from swe_calc (default)\n";
echo "================================================\n";
$xx_geo_swe = [];
$rc_geo = swe_calc($jd, $ipl, $iflag, $xx_geo_swe, $serr);

printf("Geocentric (from swe_calc without flags):\n");
printf("  X = %.15f AU\n", $xx_geo_swe[0]);
printf("  Y = %.15f AU\n", $xx_geo_swe[1]);
printf("  Z = %.15f AU\n", $xx_geo_swe[2]);
printf("  VX = %.15f AU/day\n", $xx_geo_swe[3]);
printf("  VY = %.15f AU/day\n", $xx_geo_swe[4]);
printf("  VZ = %.15f AU/day\n\n", $xx_geo_swe[5]);

// Step 6: Compare results
echo "STEP 6: Comparison\n";
echo "==================\n";
printf("Difference (swe_calc - manual):\n");
printf("  ΔX = %.15e AU (%.3f meters)\n",
    $xx_geo_swe[0] - $xx_geo_manual[0],
    ($xx_geo_swe[0] - $xx_geo_manual[0]) * 149597870700);
printf("  ΔY = %.15e AU (%.3f meters)\n",
    $xx_geo_swe[1] - $xx_geo_manual[1],
    ($xx_geo_swe[1] - $xx_geo_manual[1]) * 149597870700);
printf("  ΔZ = %.15e AU (%.3f meters)\n",
    $xx_geo_swe[2] - $xx_geo_manual[2],
    ($xx_geo_swe[2] - $xx_geo_manual[2]) * 149597870700);

$distance_diff = sqrt(
    pow($xx_geo_swe[0] - $xx_geo_manual[0], 2) +
    pow($xx_geo_swe[1] - $xx_geo_manual[1], 2) +
    pow($xx_geo_swe[2] - $xx_geo_manual[2], 2)
);
printf("\nTotal 3D distance difference: %.15e AU (%.3f meters)\n\n",
    $distance_diff, $distance_diff * 149597870700);

// Step 7: Investigate Earth calculation in detail
echo "STEP 7: Earth calculation details\n";
echo "==================================\n";

// Get EMB
$xx_emb = [];
$rc_emb = swe_calc($jd, Constants::SE_EARTH,
    Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR,
    $xx_emb, $serr);

$pebdp = $swed->pldat[SwephConstants::SEI_EMB];
printf("EMB barycentric (SEI_EMB from SwedState):\n");
printf("  X = %.15f AU\n", $pebdp->x[0]);
printf("  Y = %.15f AU\n", $pebdp->x[1]);
printf("  Z = %.15f AU\n\n", $pebdp->x[2]);

// Get Moon
$pmdp = $swed->pldat[SwephConstants::SEI_MOON];
printf("Moon barycentric (SEI_MOON from SwedState):\n");
printf("  X = %.15f AU\n", $pmdp->x[0]);
printf("  Y = %.15f AU\n", $pmdp->x[1]);
printf("  Z = %.15f AU\n\n", $pmdp->x[2]);

// Manual embofs calculation
$mass_ratio = 1.0 / (1.0 + 81.30056);
printf("Mass ratio (Moon/(Earth+Moon)) = %.15f\n", $mass_ratio);

$earth_manual = [
    $pebdp->x[0] - $pmdp->x[0] * $mass_ratio,
    $pebdp->x[1] - $pmdp->x[1] * $mass_ratio,
    $pebdp->x[2] - $pmdp->x[2] * $mass_ratio
];

printf("\nEarth from manual embofs(EMB, Moon):\n");
printf("  X = %.15f AU\n", $earth_manual[0]);
printf("  Y = %.15f AU\n", $earth_manual[1]);
printf("  Z = %.15f AU\n", $earth_manual[2]);

printf("\nEarth from SwedState (pedp->x):\n");
printf("  X = %.15f AU\n", $pedp->x[0]);
printf("  Y = %.15f AU\n", $pedp->x[1]);
printf("  Z = %.15f AU\n", $pedp->x[2]);

printf("\nDifference (SwedState - manual embofs):\n");
printf("  ΔX = %.15e AU (%.3f mm)\n",
    $pedp->x[0] - $earth_manual[0],
    ($pedp->x[0] - $earth_manual[0]) * 149597870700000);
printf("  ΔY = %.15e AU (%.3f mm)\n",
    $pedp->x[1] - $earth_manual[1],
    ($pedp->x[1] - $earth_manual[1]) * 149597870700000);
printf("  ΔZ = %.15e AU (%.3f mm)\n",
    $pedp->x[2] - $earth_manual[2],
    ($pedp->x[2] - $earth_manual[2]) * 149597870700000);

echo "\n=============================================================\n";
echo "TRACE COMPLETE\n";
echo "=============================================================\n";
