<?php

// Get reference values from C swetest64 for comparison
// JD 2460409.2630702 (2024-04-08 06:18 UT)

$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';
$ephe = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe';

// Observer location: Dallas, TX
$lon = -96.8;
$lat = 32.8;
$alt = 0.0;

// Date
$jd = 2460409.2630702;

echo "Getting C reference for Moon topocentric equatorial coordinates\n";
echo str_repeat("=", 80) . "\n\n";
echo "Date: JD $jd (~2024-04-08 06:18 UT)\n";
printf("Observer: lon=%.1f°, lat=%.1f°, alt=%.1f m\n\n", $lon, $lat, $alt);

// Build swetest64 command
// -j = Julian day (TT by default)
// -p1 = Moon (planet 1)
// -fTAD = columns: calendar date (T), right ascension (A), declination (D)
// -topo = topocentric observer coordinates (lon,lat,alt)
// -eswe = use Swiss Ephemeris files
// -head = suppress header

$args = [
    $swetest,
    '-j' . $jd,
    '-p1',
    '-fTAD',
    '-topo' . $lon . ',' . $lat . ',' . $alt,
    '-eswe',
    '-head',
];

$escaped = array_map('escapeshellarg', $args);
$cmd = implode(' ', $escaped);

echo "Command:\n$cmd\n\n";
echo "Output:\n";
echo str_repeat("-", 80) . "\n";

// Execute and capture output (quoted args required on PowerShell to keep decimals)
$output = shell_exec($cmd . ' 2>&1');
echo $output;
echo str_repeat("-", 80) . "\n";
