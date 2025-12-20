<?php
/**
 * Compare PHP osculating nodes/apsides with C reference from swetest64
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path('C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe');

$jd = 2451545.0; // J2000.0 = 2000-01-01 12:00 UT
$planet = Constants::SE_JUPITER;

// C Reference from swetest64.exe -b1.1.2000 -ut12:00 -p5 -fN/-fF:
// N: 100.5194687  280.4645626
// F: 4.1628266  205.5688232  254.2113958 (distance to peri, aph_lon, focal_lon?)
$cRef = [
    'asc_node' => 100.5194687,
    'desc_node' => 280.4645626,
    // Need to re-check perihelion/aphelion format
];

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = '';
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

$ret = swe_nod_aps_ut($jd, $planet, $iflag, Constants::SE_NODBIT_OSCU, $xnasc, $xndsc, $xperi, $xaphe, $serr);

if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "=== JUPITER OSCULATING NODES/APSIDES COMPARISON ===\n";
echo "Date: 2000-01-01 12:00 UT (JD 2451545.0)\n\n";

echo "ASCENDING NODE:\n";
echo sprintf("  C Reference: %.7f°\n", $cRef['asc_node']);
echo sprintf("  PHP Result:  %.7f°\n", $xnasc[0]);
$diff = abs($xnasc[0] - $cRef['asc_node']) * 3600;
echo sprintf("  Difference:  %.2f\" (%s)\n\n", $diff, $diff < 1 ? "✓" : "✗");

echo "DESCENDING NODE:\n";
echo sprintf("  C Reference: %.7f°\n", $cRef['desc_node']);
echo sprintf("  PHP Result:  %.7f°\n", $xndsc[0]);
$diff = abs($xndsc[0] - $cRef['desc_node']) * 3600;
echo sprintf("  Difference:  %.2f\" (%s)\n\n", $diff, $diff < 1 ? "✓" : "✗");

echo "PERIHELION:\n";
echo sprintf("  PHP Longitude: %.7f°\n", $xperi[0]);
echo sprintf("  PHP Distance:  %.7f AU\n", $xperi[2]);

echo "\nAPHELION:\n";
echo sprintf("  PHP Longitude: %.7f°\n", $xaphe[0]);
echo sprintf("  PHP Distance:  %.7f AU\n", $xaphe[2]);

echo "\n=== ALL OUTPUT VALUES ===\n";
echo "xnasc: [" . implode(', ', array_map(fn($v) => sprintf('%.10f', $v), $xnasc)) . "]\n";
echo "xndsc: [" . implode(', ', array_map(fn($v) => sprintf('%.10f', $v), $xndsc)) . "]\n";
echo "xperi: [" . implode(', ', array_map(fn($v) => sprintf('%.10f', $v), $xperi)) . "]\n";
echo "xaphe: [" . implode(', ', array_map(fn($v) => sprintf('%.10f', $v), $xaphe)) . "]\n";
