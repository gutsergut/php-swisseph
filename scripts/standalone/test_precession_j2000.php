<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Precession;
use Swisseph\Constants;

$jd = 2451545.0; // J2000

// Test position in equatorial coordinates
$pos = [1.0, 2.0, 3.0, 0.0, 0.0, 0.0];

echo "=== Testing Precession J2000→J2000 ===\n\n";
echo "Initial position: [" . implode(', ', $pos) . "]\n\n";

// Precess to J2000
$pos_j2000 = $pos;
Precession::precess($pos_j2000, $jd, 0, Constants::J_TO_J2000);
echo "After J_TO_J2000: [" . implode(', ', $pos_j2000) . "]\n";

// Precess back to date
$pos_date = $pos_j2000;
Precession::precess($pos_date, $jd, 0, Constants::J2000_TO_J);
echo "After J2000_TO_J: [" . implode(', ', $pos_date) . "]\n\n";

// Calculate difference
$diff = [];
for ($i = 0; $i < 6; $i++) {
    $diff[$i] = abs($pos_date[$i] - $pos[$i]);
}

echo "Difference from initial: [" . implode(', ', $diff) . "]\n";
echo "Total error: " . array_sum($diff) . "\n";

if (array_sum($diff) < 1e-10) {
    echo "\n✓ Precession is identity for J2000 (within floating point precision)\n";
} else {
    echo "\n✗ Precession introduces error!\n";
}
