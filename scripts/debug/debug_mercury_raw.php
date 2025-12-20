<?php
/**
 * Debug: compare raw Mercury data from ephemeris
 */

require_once __DIR__ . '/../vendor/autoload.php';
use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwephPlanCalculator;

\swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2460000.0;  // 25 Feb 2023 00:00 UT

echo "=== Using SwephPlanCalculator (proper entry point) ===\n\n";

// Read Mercury through SwephPlanCalculator
$xpret = [];
$xperet = [];
$xpsret = [];
$xpmret = [];
$serr = '';
$ipli = SwephConstants::SEI_MERCURY;  // Internal Mercury = 2

$retc = SwephPlanCalculator::calculate(
    $tjd,
    $ipli,
    Constants::SE_MERCURY,  // external planet number
    SwephConstants::SEI_FILE_PLANET,
    Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED,
    true,  // doSave
    $xpret,
    $xperet,
    $xpsret,
    $xpmret,
    $serr
);

printf("Return code: %d, error: %s\n", $retc, $serr ?: 'none');
printf("\nMercury XYZ from SwephPlanCalculator:\n");
printf("  x = %.15f\n", $xpret[0] ?? 0);
printf("  y = %.15f\n", $xpret[1] ?? 0);
printf("  z = %.15f\n", $xpret[2] ?? 0);
$dist = sqrt(($xpret[0]??0)**2 + ($xpret[1]??0)**2 + ($xpret[2]??0)**2);
printf("  dist = %.9f AU\n", $dist);

// Check pdp flags
$swed = SwedState::getInstance();
$pdp = $swed->pldat[$ipli] ?? null;
if ($pdp) {
    printf("\npdp->iflg = 0x%X (HELIO=%d, EMBHEL=%d)\n",
        $pdp->iflg,
        ($pdp->iflg & SwephConstants::SEI_FLG_HELIO) ? 1 : 0,
        ($pdp->iflg & SwephConstants::SEI_FLG_EMBHEL) ? 1 : 0);
}

// Check Sun Barycenter
echo "\n=== Sun Barycenter ===\n";
printf("xpsret (returned by SwephPlanCalculator):\n");
printf("  x = %.15f\n", $xpsret[0] ?? 0);
printf("  y = %.15f\n", $xpsret[1] ?? 0);
printf("  z = %.15f\n", $xpsret[2] ?? 0);

$psdp = $swed->pldat[SwephConstants::SEI_SUNBARY] ?? null;
if ($psdp) {
    printf("\npsdp->x (cache):\n");
    printf("  x = %.15f\n", $psdp->x[0] ?? 0);
    printf("  y = %.15f\n", $psdp->x[1] ?? 0);
    printf("  z = %.15f\n", $psdp->x[2] ?? 0);
}

echo "\n=== Reference from swetest64.exe ===\n";
echo "swetest -p2 -fX -head -eswe -true -j2000 -icrs -hel:\n";
echo "  x = 0.101780886\n";
echo "  y = -0.441188298\n";
echo "  z = -0.045389862\n";
echo "\nswetest -p0 -fX -head -eswe -true -j2000 -icrs -bary (Sun bary):\n";
echo "  x = -0.008984226\n";
echo "  y = -0.000438873\n";
echo "  z = 0.000210217\n";
