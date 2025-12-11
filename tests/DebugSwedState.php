<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

$jd_ut = 2460677.0;
$jd_et = $jd_ut + swe_deltat_ex($jd_ut, Constants::SEFLG_SWIEPH, $serr);

echo "=== Debug SwedState after Moon calculation ===\n\n";

// Calculate Moon
$xc_moon = [];
swe_calc($jd_et, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $xc_moon, $serr);
printf("Moon result: [%.6f, %.6f, %.9f]\n\n", $xc_moon[0], $xc_moon[1], $xc_moon[2]);

// Inspect SwedState
$swed = SwedState::getInstance();

printf("SwedState planet slots:\n");
for ($i = 0; $i <= 10; $i++) {
    $pdp = &$swed->pldat[$i];
    printf("  Slot %2d: teval=%.2f, iephe=%d, x=[%.6f, %.6f, %.9f]\n",
        $i, $pdp->teval ?? 0, $pdp->iephe ?? 0,
        $pdp->x[0] ?? 0, $pdp->x[1] ?? 0, $pdp->x[2] ?? 0);
}

printf("\nKey slots:\n");
printf("  SEI_SUN=%d, SEI_MOON=%d, SEI_EARTH=%d, SEI_EMB=%d, SEI_SUNBARY=%d\n",
    SwephConstants::SEI_SUN, SwephConstants::SEI_MOON,
    SwephConstants::SEI_EARTH, SwephConstants::SEI_EMB,
    SwephConstants::SEI_SUNBARY);

printf("\nExpected:\n");
printf("  Slot 0 (EMB/EARTH/SUN): Should have Earth/EMB data\n");
printf("  Slot 1 (MOON): Should have Moon data (~0.00257 AU)\n");
printf("  Slot 10 (SUNBARY): Should have Sun barycenter data (~0.00597 AU from Earth)\n");
