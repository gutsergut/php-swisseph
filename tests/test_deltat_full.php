<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Time\DeltaTFull;

// Test full DeltaTFull implementation against C swetest64 reference
$tjd = 2460409.2630702;
$Y = 2000.0 + ($tjd - 2451544.5) / 365.25;

echo "Testing DeltaTFull::deltaTAA() for JD=$tjd (Year $Y)\n";
echo "Expected from C: 0.0007994712 days = 69.074 seconds\n\n";

// Test with default tidal acceleration
$dt_days = DeltaTFull::deltaTAA($tjd, -1);
$dt_seconds = $dt_days * 86400.0;

echo "PHP DeltaTFull result:\n";
echo "  DeltaT = $dt_days days\n";
echo "  DeltaT = $dt_seconds seconds\n\n";

$expected_days = 0.0007994712;
$expected_seconds = 69.074;
$error_days = abs($dt_days - $expected_days);
$error_seconds = abs($dt_seconds - $expected_seconds);

echo "Error vs C:\n";
echo "  Δ(days) = $error_days\n";
echo "  Δ(seconds) = $error_seconds\n\n";

if ($error_seconds < 0.001) {
    echo "✅ PASS: Error < 0.001 seconds (< 1 millisecond)\n";
} elseif ($error_seconds < 0.01) {
    echo "⚠️  WARN: Error < 0.01 seconds (< 10 milliseconds)\n";
} else {
    echo "❌ FAIL: Error > 0.01 seconds\n";
}
