<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\State;
use Swisseph\Constants;
use Swisseph\Swe\FixedStars\StarRegistry;
use Swisseph\Swe\FixedStars\StarCalculator;

echo "=== Topocentric Fixed Star Test ===\n\n";

$ephePath = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe';
State::setEphePath($ephePath);
echo "Ephemeris path set to: $ephePath\n";
echo "File exists: " . (file_exists($ephePath . '/sefstars.txt') ? 'yes' : 'no') . "\n\n";

// Set topocentric position: Zurich, Switzerland
// Longitude: 8.5417° E, Latitude: 47.3769° N, Altitude: 408 m
$lon = 8.5417;
$lat = 47.3769;
$alt = 408.0;
State::setTopo($lon, $lat, $alt);
echo "Observer position set to: Zurich, Switzerland\n";
echo "  Longitude: {$lon}° E\n";
echo "  Latitude: {$lat}° N\n";
echo "  Altitude: {$alt} m\n\n";

// Load star catalog
$serr = null;
$retc = StarRegistry::loadAll($serr);
if ($retc < 0) {
    echo "Error loading catalog: $serr\n";
    exit(1);
}
echo "Catalog loaded: " . StarRegistry::getNamedCount() . " named stars, ";
echo StarRegistry::getRealCount() . " total\n\n";

// Find Sirius
$starName = 'Sirius';
$starData = StarRegistry::search($starName, $serr);
if (!$starData) {
    echo "Error: Star '$starName' not found\n";
    exit(1);
}

echo "Found star: {$starData->skey}\n";
echo "  Epoch: {$starData->epoch}\n";
echo "  RA: {$starData->ra}°\n";
echo "  Dec: {$starData->de}°\n\n";

// Test date: J2000.0
$tjd = 2451545.0;
echo "Calculating position at J2000.0 (JD $tjd)...\n\n";

// Test 1: Geocentric position (baseline)
echo "=== Test 1: Geocentric (SEFLG_EQUATORIAL) ===\n";
$xx = [];
$star = $starName;
$serr = null;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED;
$retc = StarCalculator::calculate($starData, $tjd, $iflag, $star, $xx, $serr);

if ($retc < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "Geocentric position:\n";
echo "  RA: {$xx[0]}°\n";
echo "  Dec: {$xx[1]}°\n";
echo "  Distance: {$xx[2]} AU\n";
echo "  dRA/day: {$xx[3]}°\n";
echo "  dDec/day: {$xx[4]}°\n\n";

// Test 2: Topocentric position
echo "=== Test 2: Topocentric (SEFLG_TOPOCTR + SEFLG_EQUATORIAL) ===\n";
$xx_topo = [];
$star_topo = $starName;
$serr = null;
$iflag_topo = Constants::SEFLG_SWIEPH | Constants::SEFLG_TOPOCTR | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED;
$retc = StarCalculator::calculate($starData, $tjd, $iflag_topo, $star_topo, $xx_topo, $serr);

if ($retc < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "Topocentric position:\n";
echo "  RA: {$xx_topo[0]}°\n";
echo "  Dec: {$xx_topo[1]}°\n";
echo "  Distance: {$xx_topo[2]} AU\n";
echo "  dRA/day: {$xx_topo[3]}°\n";
echo "  dDec/day: {$xx_topo[4]}°\n\n";

// Calculate differences
$diff_ra = ($xx_topo[0] - $xx[0]) * 3600.0; // arcseconds
$diff_dec = ($xx_topo[1] - $xx[1]) * 3600.0; // arcseconds
$diff_dist = $xx_topo[2] - $xx[2]; // AU

echo "=== Geocentric vs Topocentric Difference ===\n";
echo "  ΔRA: " . number_format($diff_ra, 6) . " arcsec\n";
echo "  ΔDec: " . number_format($diff_dec, 6) . " arcsec\n";
echo "  ΔDistance: " . number_format($diff_dist, 9) . " AU\n";
echo "  Expected: Small difference due to parallax (~0.1-0.5 arcsec for distant stars)\n\n";

echo "=== Tests completed ===\n";
