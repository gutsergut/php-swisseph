<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\FixstarFunctions;
use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../../eph/ephe');

echo "=== Fixed Star Magnitude Test ===\n\n";

// Test star magnitude only (doesn't require ephemeris files)
$star = 'Spica';
$mag = 0.0;
$serr = null;

echo "Testing swe_fixstar_mag() for Spica:\n";

$retc = FixstarFunctions::fixstarMag($star, $mag, $serr);

if ($retc === Constants::SE_ERR) {
    echo "ERROR: " . ($serr ?? 'Unknown error') . "\n";
    exit(1);
}

echo "Star name: " . $star . "\n";
echo "Magnitude: " . sprintf("%.2f", $mag) . "\n";
echo "\n✓ Test passed!\n";
