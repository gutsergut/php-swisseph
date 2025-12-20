<?php
/**
 * Multi-planet comparison test for moshplan2
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Moshier\MoshierPlanetCalculator;

$jdTT = 2460477.0008007409;  // 2024-06-15 12:00 TT

echo "=== Multi-planet moshplan2 comparison ===\n\n";
echo sprintf("JD (TT): %.10f\n\n", $jdTT);

// Reference values from swetest -t0 -j2460477.0008007409 -emos -hel -j2000 -fPlbr
// Mercury: 88.9178875  4.5724582  0.308572307
// Venus:   183.5999893  1.5648621  0.719627447
// Earth:   265.5247161 -0.0004405  1.016135988
// Mars:    359.4472133  0.9149588  1.391354379
// Jupiter: 36.3066096 -1.3128315  5.022746447
// Saturn:  349.2625879 -2.0959124  9.690232989
// Uranus:  54.9642844  0.3556605  19.586586738
// Neptune: 359.8540810 -0.9217820  29.899565078
// (Pluto not directly comparable as swetest returns geocentric for -p9)

$refs = [
    ['name' => 'Mercury', 'mosh' => 0, 'lon' => 88.9178875, 'lat' => 4.5724582, 'rad' => 0.308572307],
    ['name' => 'Venus', 'mosh' => 1, 'lon' => 91.6834734, 'lat' => 0.8836133, 'rad' => 0.719590039],
    ['name' => 'EMB', 'mosh' => 2, 'lon' => 264.5353408, 'lat' => 0.0032079, 'rad' => 1.015792265],
    ['name' => 'Mars', 'mosh' => 3, 'lon' => 0.2106963, 'lat' => -1.4006790, 'rad' => 1.391790150],
    ['name' => 'Jupiter', 'mosh' => 4, 'lon' => 60.4449327, 'lat' => -0.8392576, 'rad' => 5.022997100],
    ['name' => 'Saturn', 'mosh' => 5, 'lon' => 342.9254253, 'lat' => -1.8865983, 'rad' => 9.689890447],
    ['name' => 'Uranus', 'mosh' => 6, 'lon' => 53.1436008, 'lat' => -0.2753024, 'rad' => 19.586502677],
    ['name' => 'Neptune', 'mosh' => 7, 'lon' => 357.5738981, 'lat' => -1.2689680, 'rad' => 29.899543754],
];

echo sprintf("%-10s %12s %12s %12s %12s %12s\n",
    "Planet", "PHP Lon", "Ref Lon", "ΔLon (\")", "ΔLat (\")", "ΔRad (AU)");
echo str_repeat('-', 72) . "\n";

foreach ($refs as $ref) {
    $pobj = [0.0, 0.0, 0.0];
    MoshierPlanetCalculator::moshplan2($jdTT, $ref['mosh'], $pobj);

    $phpLon = rad2deg($pobj[0]);
    // Normalize to 0-360
    while ($phpLon < 0) $phpLon += 360;
    while ($phpLon >= 360) $phpLon -= 360;

    $phpLat = rad2deg($pobj[1]);
    $phpRad = $pobj[2];

    $dLon = ($phpLon - $ref['lon']) * 3600;
    // Handle wraparound near 0/360
    if ($dLon > 648000) $dLon -= 1296000;
    if ($dLon < -648000) $dLon += 1296000;

    $dLat = ($phpLat - $ref['lat']) * 3600;
    $dRad = $phpRad - $ref['rad'];

    echo sprintf("%-10s %12.6f %12.6f %+12.2f %+12.2f %+12.6f\n",
        $ref['name'], $phpLon, $ref['lon'], $dLon, $dLat, $dRad);
}

echo "\n=== Done ===\n";
