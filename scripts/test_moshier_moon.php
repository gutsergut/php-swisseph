<?php

/**
 * Test script for Moshier Moon implementation
 * Compares PHP results with swetest64.exe reference
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SwissEph\Domain\Moshier\MoshierMoon;
use SwissEph\Domain\Moshier\MoshierMoonData;

echo "=== Moshier Moon Test ===\n\n";

// Test date: J2000 epoch
$J2000 = 2451545.0;

$moon = new MoshierMoon();

// Test 1: Basic moshmoon2() - geometric coordinates
echo "Test 1: moshmoon2() at J2000\n";
$pol = [0.0, 0.0, 0.0];
$result = $moon->moshmoon2($J2000, $pol);

echo "Result code: $result\n";
echo sprintf("Longitude: %.10f rad = %.6f°\n", $pol[0], rad2deg($pol[0]));
echo sprintf("Latitude:  %.10f rad = %.6f°\n", $pol[1], rad2deg($pol[1]));
echo sprintf("Distance:  %.10f AU = %.2f km\n", $pol[2], $pol[2] * 149597870.7);
echo "\n";

// Test 2: Another date (2025-01-01 12:00 UT)
$jd2025 = 2460676.5 + 0.5;  // 2025-01-01 12:00 UT
echo "Test 2: moshmoon2() at 2025-01-01 12:00 UT (JD = $jd2025)\n";
$pol = [0.0, 0.0, 0.0];
$result = $moon->moshmoon2($jd2025, $pol);

echo "Result code: $result\n";
echo sprintf("Longitude: %.10f rad = %.6f°\n", $pol[0], rad2deg($pol[0]));
echo sprintf("Latitude:  %.10f rad = %.6f°\n", $pol[1], rad2deg($pol[1]));
echo sprintf("Distance:  %.10f AU = %.2f km\n", $pol[2], $pol[2] * 149597870.7);
echo "\n";

// Test 3: moshmoon() with speed
echo "Test 3: moshmoon() at 2025-01-01 12:00 UT (equatorial J2000 + speed)\n";
$xpm = [];
$serr = '';
$result = $moon->moshmoon($jd2025, false, $xpm, $serr);

echo "Result code: $result\n";
if ($serr) {
    echo "Error: $serr\n";
}
if ($result === 0) {
    echo sprintf("X: %.10f AU\n", $xpm[0]);
    echo sprintf("Y: %.10f AU\n", $xpm[1]);
    echo sprintf("Z: %.10f AU\n", $xpm[2]);
    echo sprintf("VX: %.10f AU/day\n", $xpm[3]);
    echo sprintf("VY: %.10f AU/day\n", $xpm[4]);
    echo sprintf("VZ: %.10f AU/day\n", $xpm[5]);

    // Calculate distance
    $dist = sqrt($xpm[0]*$xpm[0] + $xpm[1]*$xpm[1] + $xpm[2]*$xpm[2]);
    echo sprintf("\nDistance: %.10f AU = %.2f km\n", $dist, $dist * 149597870.7);
}
echo "\n";

// Test 4: Edge cases - start and end of Moshier range
echo "Test 4: Edge cases\n";

$testDates = [
    ['desc' => 'Near start (-3000)', 'jd' => MoshierMoon::MOSHLUEPH_START + 1],
    ['desc' => 'Near end (+3000)', 'jd' => MoshierMoon::MOSHLUEPH_END - 1],
    ['desc' => 'Out of range (before)', 'jd' => MoshierMoon::MOSHLUEPH_START - 100],
    ['desc' => 'Out of range (after)', 'jd' => MoshierMoon::MOSHLUEPH_END + 100],
];

foreach ($testDates as $test) {
    echo sprintf("  %s (JD=%.1f): ", $test['desc'], $test['jd']);
    $xpm = [];
    $serr = '';
    $result = $moon->moshmoon($test['jd'], false, $xpm, $serr);
    if ($result === 0) {
        $dist = sqrt($xpm[0]*$xpm[0] + $xpm[1]*$xpm[1] + $xpm[2]*$xpm[2]);
        echo sprintf("OK, dist=%.6f AU\n", $dist);
    } else {
        echo "ERR: $serr\n";
    }
}
echo "\n";

// Test 5: Verify tables are loaded correctly
echo "Test 5: Table verification\n";
echo sprintf("Z array length: %d (expected 25)\n", count(MoshierMoonData::Z));
echo sprintf("LR array length: %d (expected %d = 8*%d)\n", count(MoshierMoonData::LR), 8 * MoshierMoonData::NLR, MoshierMoonData::NLR);
echo sprintf("MB array length: %d (expected %d = 6*%d)\n", count(MoshierMoonData::MB), 6 * MoshierMoonData::NMB, MoshierMoonData::NMB);
echo sprintf("LRT array length: %d (expected %d = 8*%d)\n", count(MoshierMoonData::LRT), 8 * MoshierMoonData::NLRT, MoshierMoonData::NLRT);
echo sprintf("BT array length: %d (expected %d = 5*%d)\n", count(MoshierMoonData::BT), 5 * MoshierMoonData::NBT, MoshierMoonData::NBT);
echo sprintf("LRT2 array length: %d (expected %d = 6*%d)\n", count(MoshierMoonData::LRT2), 6 * MoshierMoonData::NLRT2, MoshierMoonData::NLRT2);
echo sprintf("BT2 array length: %d (expected %d = 5*%d)\n", count(MoshierMoonData::BT2), 5 * MoshierMoonData::NBT2, MoshierMoonData::NBT2);
echo "\n";

// Test 6: Sample values from LR table
echo "Test 6: Sample LR table values\n";
echo sprintf("LR[0-7] (first line): D=%d l'=%d l=%d F=%d Long=%d.%04d\" Rad=%d.%04dkm\n",
    MoshierMoonData::LR[0], MoshierMoonData::LR[1], MoshierMoonData::LR[2], MoshierMoonData::LR[3],
    MoshierMoonData::LR[4], abs(MoshierMoonData::LR[5]),
    MoshierMoonData::LR[6], abs(MoshierMoonData::LR[7]));
echo "Expected: D=0 l'=0 l=1 F=0 Long=22639.5858\" Rad=-20905.-3550km\n";
echo "\n";

echo "=== All basic tests completed ===\n";
