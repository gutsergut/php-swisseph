<?php
/**
 * Debug: Check record number calculation for different JDs
 */

// Ephemeris parameters from de406e.eph
$ss0 = -254895.5;  // Start epoch
$ss1 = 3696976.5;  // End epoch
$ss2 = 64.0;       // Step (days per segment)

function calcRecordAndTime($jd, $ss0, $ss1, $ss2) {
    $s = $jd - 0.5;
    $etMn = floor($s);
    $etFr = $s - $etMn;
    $etMn += 0.5;

    // Record number (как в C коде)
    $nr = (int)(($etMn - $ss0) / $ss2) + 2;

    // Normalized time t
    $t = ($etMn - (($nr - 2) * $ss2 + $ss0) + $etFr) / $ss2;

    return [
        'jd' => $jd,
        'etMn' => $etMn,
        'etFr' => $etFr,
        'nr' => $nr,
        't' => $t,
        'segment_start' => ($nr - 2) * $ss2 + $ss0,
        'segment_end' => ($nr - 2) * $ss2 + $ss0 + $ss2
    ];
}

echo "=== Record calculation debug ===\n";
echo "ss0 (start) = $ss0\n";
echo "ss1 (end) = $ss1\n";
echo "ss2 (step) = $ss2 days\n\n";

// Test dates
$testDates = [
    -254800.0,    // Near start
    2451545.0,    // J2000
    2455000.0,    // Middle
    2460016.5,    // March 2023
];

foreach ($testDates as $jd) {
    $result = calcRecordAndTime($jd, $ss0, $ss1, $ss2);
    printf("JD %.1f:\n", $jd);
    printf("  etMn = %.1f, etFr = %.10f\n", $result['etMn'], $result['etFr']);
    printf("  Record nr = %d\n", $result['nr']);
    printf("  Segment: [%.1f .. %.1f]\n", $result['segment_start'], $result['segment_end']);
    printf("  Normalized t = %.15f (should be 0..1)\n", $result['t']);
    printf("  JD in segment: %s\n", ($jd >= $result['segment_start'] && $jd < $result['segment_end']) ? 'YES' : 'NO');
    echo "\n";
}

// Check for -254800 specifically
echo "=== Special check for JD -254800 ===\n";
$jd = -254800.0;
$s = $jd - 0.5;
$etMn = floor($s);
$etFr = $s - $etMn;
$etMn += 0.5;

echo "Step by step:\n";
echo "  jd = $jd\n";
echo "  s = jd - 0.5 = $s\n";
echo "  etMn = floor(s) = $etMn\n";
echo "  etFr = s - etMn = $etFr\n";
echo "  etMn += 0.5 => $etMn\n";

$nr = (int)(($etMn - $ss0) / $ss2) + 2;
echo "  nr = (int)(($etMn - $ss0) / $ss2) + 2\n";
echo "     = (int)(({$etMn} - {$ss0}) / $ss2) + 2\n";
$diff = $etMn - $ss0;
echo "     = (int)($diff / $ss2) + 2\n";
$div = $diff / $ss2;
echo "     = (int)($div) + 2\n";
$intDiv = (int)$div;
echo "     = $intDiv + 2 = $nr\n";
