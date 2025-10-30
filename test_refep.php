#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$tjd = 2451545.0; // J2000.0
$xx = [];
$serr = '';

// Calculate Jupiter to load segment
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_SPEED;
PlanetsFunctions::calc($tjd, Constants::SE_JUPITER, $iflag, $xx, $serr);

// Get swed state
$swed = SwedState::getInstance();
$pdp = $swed->pldat[SwephConstants::SEI_JUPITER];

echo "DEBUG: Checking all pldat entries:\n";
for ($i = 0; $i < 18; $i++) {
    if ($swed->pldat[$i]->iflg != 0 || $swed->pldat[$i]->ncoe != 0) {
        printf("  pldat[%2d]: iflg=0x%X, ncoe=%d, ibdy=%d\n",
            $i, $swed->pldat[$i]->iflg, $swed->pldat[$i]->ncoe, $swed->pldat[$i]->ibdy);
    }
}
echo "\n";

echo "Jupiter Reference Ellipse Check:\n";
echo "=================================\n\n";

printf("iflg = 0x%X\n", $pdp->iflg);
printf("SEI_FLG_HELIO  = 0x%X (bit 0)\n", SwephConstants::SEI_FLG_HELIO);
printf("SEI_FLG_ROTATE = 0x%X (bit 1)\n", SwephConstants::SEI_FLG_ROTATE);
printf("SEI_FLG_ELLIPSE = 0x%X (bit 2)\n", SwephConstants::SEI_FLG_ELLIPSE);
printf("Is heliocentric? %s\n", ($pdp->iflg & SwephConstants::SEI_FLG_HELIO) ? "YES" : "NO");
printf("Needs rotation? %s\n", ($pdp->iflg & SwephConstants::SEI_FLG_ROTATE) ? "YES" : "NO");
printf("Uses reference ellipse? %s\n", ($pdp->iflg & SwephConstants::SEI_FLG_ELLIPSE) ? "YES" : "NO");
printf("\n");

printf("ncoe = %d\n", $pdp->ncoe);
printf("peri = %.15f\n", $pdp->peri);
printf("dperi = %.15f\n", $pdp->dperi);
printf("\n");

if ($pdp->refep !== null) {
    printf("refep array size: %d (expected: %d)\n", count($pdp->refep), 2 * $pdp->ncoe);
    printf("\n");
    printf("First 5 refep X coefficients:\n");
    for ($i = 0; $i < 5 && $i < $pdp->ncoe; $i++) {
        printf("  refep[%2d] = %.15f\n", $i, $pdp->refep[$i]);
    }
    printf("\n");
    printf("First 5 refep Y coefficients:\n");
    for ($i = 0; $i < 5 && $i < $pdp->ncoe; $i++) {
        printf("  refep[%2d] = %.15f\n", $i + $pdp->ncoe, $pdp->refep[$i + $pdp->ncoe]);
    }
} else {
    echo "ERROR: refep is NULL!\n";
}

printf("\n");
printf("First 5 final X coefficients (after rot_back):\n");
for ($i = 0; $i < 5 && $i < $pdp->ncoe; $i++) {
    printf("  segp[%2d] = %.15f\n", $i, $pdp->segp[$i]);
}
