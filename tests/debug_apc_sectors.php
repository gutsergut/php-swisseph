<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// Berlin 2025-01-01 12:00 UT
$jd = swe_julday(2025, 1, 1, 12.0, \Swisseph\Constants::SE_GREG_CAL);
$lon = 13.41;
$lat = 52.52;

echo "=== APC Sector Debug ===\n";
echo "Location: Berlin (13.41°E, 52.52°N)\n";
echo "Date: 2025-01-01 12:00 UT\n\n";

// Get houses through swe_houses to see what cusps should be
$cusps = [];
$ascmc = [];
swe_houses($jd, $lat, $lon, 'Y', $cusps, $ascmc);

echo "Current APC cusps from swe_houses:\n";
for ($i = 1; $i <= 12; $i++) {
    printf("  House %2d: %.6f°\n", $i, $cusps[$i]);
}

echo "\nExpected reference values:\n";
$ref = [
    1 => 53.174602,
    2 => 79.910605,
    3 => 97.174127,
    4 => 112.976294,
    5 => 135.857779,
    6 => 182.568172,
    7 => 233.174602,
    8 => 266.963800,
    9 => 281.277876,
    10 => 292.976294,
    11 => 308.236161,
    12 => 341.460366,
];

for ($i = 1; $i <= 12; $i++) {
    $diff = abs($cusps[$i] - $ref[$i]);
    $status = ($diff < 0.01) ? '✓' : '✗';
    printf("  House %2d: %.6f° (ref: %.6f°, diff: %.6f°) %s\n",
        $i, $cusps[$i], $ref[$i], $diff, $status);
}
