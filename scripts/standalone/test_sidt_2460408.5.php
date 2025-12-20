<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Sidereal;
use Swisseph\Constants;

$tjd_ut = 2460408.5;
$eps = 23.438723450473816;
$nut = -0.001454220565147;

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
echo "  sidt = 13.116314911067278 hours\n";
echo "  sidt = 196.744723666009179 degrees\n";
echo "\n";

$diff_hours = $sidt_hours - 13.116314911067278;
$diff_deg = $sidt_deg - 196.744723666009179;
$diff_arcsec = $diff_deg * 3600.0;

echo "DIFFERENCE:\n";
echo sprintf("  Δsidt = %.15f hours\n", $diff_hours);
echo sprintf("  Δsidt = %.15f degrees = %.6f arcsec\n", $diff_deg, $diff_arcsec);
