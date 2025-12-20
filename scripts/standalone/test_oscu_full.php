<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Swe\Functions\NodesApsidesFunctions;
use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../eph/ephe');

// Test osculating nodes with full coordinate transformation pipeline
$jd_ut = 2451545.0; // J2000.0
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;

echo "Testing osculating nodes for Jupiter with full transformation pipeline\n";
echo "==================================================================\n\n";

$retflag = NodesApsidesFunctions::nodAps(
    $jd_ut,
    Constants::SE_JUPITER,
    $iflag,
    Constants::SE_NODBIT_OSCU,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($retflag < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Ascending Node:\n";
echo sprintf("  Longitude: %.6f°\n", $xnasc[0]);
echo sprintf("  Latitude:  %.6f°\n", $xnasc[1]);
echo sprintf("  Distance:  %.6f AU\n", $xnasc[2]);
echo sprintf("  Speed lon: %.6f°/day\n", $xnasc[3]);
echo sprintf("  Speed lat: %.6f°/day\n", $xnasc[4]);
echo sprintf("  Speed rad: %.6f AU/day\n\n", $xnasc[5]);

echo "Reference (from swetest):\n";
echo "  Longitude: 100.640000° (expected)\n";
echo "  Latitude:  0.003200°\n";
echo "  Distance:  5.152000 AU\n\n";

$lonError = abs($xnasc[0] - 100.640000);
echo sprintf("Longitude error: %.6f° (%.2f arcsec)\n", $lonError, $lonError * 3600);

if ($lonError < 0.01) {
    echo "\n✅ SUCCESS: Error < 0.01° (36 arcsec)\n";
} else if ($lonError < 0.1) {
    echo "\n⚠️ PARTIAL: Error < 0.1° but needs improvement\n";
} else {
    echo "\n❌ FAILED: Error >= 0.1°\n";
}
