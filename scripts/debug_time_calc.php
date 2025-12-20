<?php
/**
 * Debug time/record calculation for different dates
 */

declare(strict_types=1);

$ss = [2305424.5, 2513392.5, 32.0];  // DE200

$testDates = [
    2451545.0,    // J2000
    2451600.0,    // J2000 + 55 days
    2305500.0,    // Early DE200
];

echo "SS: [{$ss[0]}, {$ss[1]}, {$ss[2]}]\n\n";

foreach ($testDates as $jd) {
    echo "=== JD $jd ===\n";

    // Standard calculation (from state)
    $s = $jd - 0.5;
    $etMn = floor($s);
    $etFr = $s - $etMn;
    $etMn += 0.5;

    echo "  s = $s\n";
    echo "  etMn = $etMn (midnight before)\n";
    echo "  etFr = $etFr (fraction)\n";

    $nr = (int)(($etMn - $ss[0]) / $ss[2]) + 2;
    if ($etMn === $ss[1]) $nr--;

    echo "  nr = $nr (record number)\n";

    // Segment this record represents
    $segStart = ($nr - 2) * $ss[2] + $ss[0];
    $segEnd = $segStart + $ss[2];
    echo "  Segment: [$segStart, $segEnd]\n";

    $t = ($etMn - $segStart + $etFr) / $ss[2];
    echo "  t = $t (normalized, 0..1)\n";

    // Check if JD is within segment
    if ($jd < $segStart || $jd > $segEnd) {
        echo "  WARNING: JD $jd not in segment!\n";
    }

    // Also check buf[0], buf[1] should match segment
    echo "\n";
}
