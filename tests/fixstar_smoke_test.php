<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\FixstarFunctions;
use Swisseph\Constants;

// Set ephemeris path using swe_set_ephe_path from functions.php
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Fixed Star Smoke Test ===\n\n";

// Test date: 2025-01-01 00:00 UT
$tjd_ut = 2460676.5;

// Test star: Spica
$star = 'Spica';
$xx = [];
$serr = null;

echo "Testing swe_fixstar_ut() for Spica at JD " . $tjd_ut . " (2025-01-01):\n";

$retc = FixstarFunctions::fixstarUt($star, $tjd_ut, Constants::SEFLG_SWIEPH, $xx, $serr);

if ($retc === Constants::SE_ERR) {
    echo "ERROR: " . ($serr ?? 'Unknown error') . "\n";
    exit(1);
}

echo "Star name: " . $star . "\n";
echo "Longitude: " . sprintf("%.6f", $xx[0]) . "°\n";
echo "Latitude:  " . sprintf("%.6f", $xx[1]) . "°\n";
echo "Distance:  " . sprintf("%.6f", $xx[2]) . " AU\n";
echo "Speed Lon: " . sprintf("%.9f", $xx[3]) . "°/day\n";
echo "Speed Lat: " . sprintf("%.9f", $xx[4]) . "°/day\n";
echo "Speed Dis: " . sprintf("%.9f", $xx[5]) . " AU/day\n";

echo "\n✓ Test passed!\n";
