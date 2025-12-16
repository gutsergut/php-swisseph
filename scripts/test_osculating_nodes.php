<?php
/**
 * Test osculating (true) nodes to verify nutation is applied correctly
 * when isTrueNodaps=TRUE
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Test case: Jupiter osculating node on 2000-01-01 12:00 TT
$jd = 2451545.0; // J2000.0
$planet = Constants::SE_JUPITER;

// Set ephemeris path
swe_set_ephe_path('C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe');

// Calculate osculating node (TRUE node)
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

echo "=== OSCULATING NODE TEST (isTrueNodaps=TRUE) ===\n\n";
echo "Planet: Jupiter\n";
echo "Date: J2000.0 (2000-01-01 12:00 TT)\n";
echo "Flags: SEFLG_SWIEPH | SEFLG_SPEED\n";
echo "Method: SE_NODBIT_OSCU (osculating)\n\n";

echo "Ascending Node:\n";
echo sprintf("  Longitude: %.10f°\n", $xnasc[0]);
echo sprintf("  Latitude:  %.10f°\n", $xnasc[1]);
echo sprintf("  Distance:  %.10f AU\n", $xnasc[2]);
echo sprintf("  Speed Lon: %.10f°/day\n", $xnasc[3]);
echo sprintf("  Speed Lat: %.10f°/day\n", $xnasc[4]);
echo sprintf("  Speed Dst: %.10f AU/day\n\n", $xnasc[5]);

// Now test with SEFLG_NONUT to verify nutation makes a difference
$xnasc_nonut = [];
$xndsc_nonut = [];
$xperi_nonut = [];
$xaphe_nonut = [];
$iflag_nonut = $iflag | Constants::SEFLG_NONUT;
$ret = swe_nod_aps_ut($jd, $planet, $iflag_nonut, Constants::SE_NODBIT_OSCU, $xnasc_nonut, $xndsc_nonut, $xperi_nonut, $xaphe_nonut, $serr);

if ($ret < 0) {
    echo "ERROR (NONUT): $serr\n";
    exit(1);
}

echo "With SEFLG_NONUT:\n";
echo sprintf("  Longitude: %.10f°\n", $xnasc_nonut[0]);
echo sprintf("  Latitude:  %.10f°\n", $xnasc_nonut[1]);
echo sprintf("  Distance:  %.10f AU\n\n", $xnasc_nonut[2]);

$diff_lon = abs($xnasc[0] - $xnasc_nonut[0]) * 3600; // arcseconds
$diff_lat = abs($xnasc[1] - $xnasc_nonut[1]) * 3600;

echo "=== NUTATION EFFECT ===\n";
echo sprintf("Longitude difference: %.3f\" (arcsec)\n", $diff_lon);
echo sprintf("Latitude difference:  %.3f\" (arcsec)\n\n", $diff_lat);

if ($diff_lon > 0.1 || $diff_lat > 0.1) {
    echo "✅ PASS: Nutation is being applied to osculating nodes\n";
    echo "   (Expected difference >0.1\" because nutation should affect coordinates)\n";
} else {
    echo "❌ FAIL: Nutation appears NOT to be applied to osculating nodes\n";
    echo "   (Difference too small: should be ~10-20\" for nutation effect)\n";
}

// Compare with reference values from swetest
echo "\n=== SWETEST REFERENCE ===\n";
echo "Run this command to get reference:\n";
echo "swetest64.exe -b1.1.2000 -p5 -fPl -eswe -n1 -s0 +N\n";
echo "(Use flags: +N for true node with nutation)\n";
