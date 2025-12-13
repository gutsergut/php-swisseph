<?php
/**
 * Debug script to check nutation values
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Nutation;
use Swisseph\Obliquity;
use Swisseph\Constants;

// JD for 2020-01-01 12:00:00 UT
$deltaT = 69.184 / 86400.0;
$jd_ut = 2458850.0;
$jd_tt = $jd_ut + $deltaT;

echo "JD_TT = $jd_tt\n\n";

// Calculate nutation
$nutModel = Nutation::selectModelFromFlags(0);
[$dpsi, $deps] = Nutation::calc($jd_tt, $nutModel, false);

echo "Nutation:\n";
echo "  dpsi = " . rad2deg($dpsi) . "° = " . ($dpsi * 180 / M_PI * 3600) . " arcsec\n";
echo "  deps = " . rad2deg($deps) . "° = " . ($deps * 180 / M_PI * 3600) . " arcsec\n";

// Calculate obliquity
$eps = Obliquity::calc($jd_tt, 0, 0, null);
echo "\nObliquity:\n";
echo "  eps = " . rad2deg($eps) . "°\n";
echo "  sin(eps) = " . sin($eps) . "\n";
echo "  cos(eps) = " . cos($eps) . "\n";

echo "\nFor comparison with C:\n";
echo "  snut = sin(deps) = " . sin($deps) . "\n";
echo "  cnut = cos(deps) = " . cos($deps) . "\n";

// Expected True Node longitude
$true_node_ref = 98.3863094;
$true_node_php = 98.1391064;
$diff = $true_node_ref - $true_node_php;

echo "\nTrue Node analysis:\n";
echo "  Reference: $true_node_ref°\n";
echo "  PHP:       $true_node_php°\n";
echo "  Diff:      $diff°\n";
echo "  dpsi:      " . rad2deg($dpsi) . "°\n";
echo "  Ratio (diff/dpsi): " . ($diff / rad2deg($dpsi)) . "\n";
