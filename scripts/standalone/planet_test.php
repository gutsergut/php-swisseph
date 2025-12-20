<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "=== Planet Calculation Test ===\n\n";

// Test date: 2025-01-01 00:00 ET
$tjd_et = 2460676.5 + 0.000775; // UT + deltat

$xx = [];
$serr = null;

echo "Testing swe_calc() for Earth at JD " . $tjd_et . ":\n";

$retc = PlanetsFunctions::calc(
    $tjd_et,
    Constants::SE_EARTH,
    Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR,
    $xx,
    $serr
);

if ($retc < 0) {
    echo "ERROR: " . ($serr ?? 'Unknown error') . "\n";
    exit(1);
}

echo "Position: [" . sprintf("%.9f", $xx[0]) . ", " . sprintf("%.9f", $xx[1]) . ", " . sprintf("%.9f", $xx[2]) . "] AU\n";
echo "\n✓ Test passed!\n";
