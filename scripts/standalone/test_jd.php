<?php
$jd = 2460676.5;

echo "JD: $jd\n";
echo "Gregorian: " . \Swisseph\Jd::jdToGregorian($jd) . "\n";
echo "Unix timestamp: " . (($jd - 2440587.5) * 86400) . "\n";

// Also check what swetest says
echo "\n2460676.5 should be 2025-01-01 00:00 UT\n";
echo "2460675.5 should be 2024-12-31 00:00 UT\n";
