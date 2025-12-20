<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Sidereal;
use Swisseph\Constants;

// EXACT same JD as C test
$tjd_ut = 2460409.262213060166687;

// Use same eps/nut as C test
$eps = 23.438715260821553;
$nut = -0.001481963118772;

putenv('DEBUG_OBSERVER=1');

echo "Input parameters:\n";
echo sprintf("  tjd_ut = %.15f\n", $tjd_ut);
echo sprintf("  eps = %.15f degrees\n", $eps);
echo sprintf("  nut = %.15f degrees\n", $nut);
echo "\n";

$sidt_hours = Sidereal::sidtime0($tjd_ut, $eps, $nut);
$sidt_deg = $sidt_hours * 15.0;

echo "PHP RESULT:\n";
echo sprintf("  sidt = %.15f hours\n", $sidt_hours);
echo sprintf("  sidt = %.15f degrees\n", $sidt_deg);
echo "\n";

echo "C REFERENCE:\n";
echo "  sidt = 7.459511544629957 hours\n";
echo "  sidt = 111.892673169449353 degrees\n";
echo "\n";

$diff_hours = $sidt_hours - 7.459511544629957;
$diff_deg = $sidt_deg - 111.892673169449353;
$diff_arcsec = $diff_deg * 3600.0;

echo "DIFFERENCE:\n";
echo sprintf("  Δsidt = %.15f hours\n", $diff_hours);
echo sprintf("  Δsidt = %.15f degrees = %.6f arcsec\n", $diff_deg, $diff_arcsec);
