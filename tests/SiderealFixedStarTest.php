<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\State;
use Swisseph\Constants;
use Swisseph\Swe\FixedStars\StarRegistry;
use Swisseph\Swe\FixedStars\StarCalculator;

echo "=== Sidereal Fixed Star Test ===\n\n";

$ephePath = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\eph\ephe';
State::setEphePath($ephePath);
echo "Ephemeris path set to: $ephePath\n";
echo "File exists: " . (file_exists($ephePath . '/sefstars.txt') ? 'yes' : 'no') . "\n\n";

// Load star catalog
$serr = null;
$retc = StarRegistry::loadAll($serr);
if ($retc < 0) {
    echo "Error loading catalog: $serr\n";
    exit(1);
}
echo "Catalog loaded: " . StarRegistry::getNamedCount() . " named stars\n\n";

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

// Test 1: Tropical (baseline)
echo "=== Test 1: Tropical (default) ===\n";
$xx_trop = [];
$star_trop = $starName;
$serr = null;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$retc = StarCalculator::calculate($starData, $tjd, $iflag, $star_trop, $xx_trop, $serr);

if ($retc < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "Tropical position:\n";
echo "  Longitude: {$xx_trop[0]}°\n";
echo "  Latitude: {$xx_trop[1]}°\n";
echo "  Distance: {$xx_trop[2]} AU\n";
echo "  dLon/day: {$xx_trop[3]}°\n";
echo "  dLat/day: {$xx_trop[4]}°\n\n";

// Test 2: Sidereal (Fagan-Bradley)
echo "=== Test 2: Sidereal Fagan-Bradley ===\n";
State::setSidMode(Constants::SE_SIDM_FAGAN_BRADLEY, 0, 0.0, 0.0);
$xx_sid = [];
$star_sid = $starName;
$serr = null;
$iflag_sid = Constants::SEFLG_SWIEPH | Constants::SEFLG_SIDEREAL | Constants::SEFLG_SPEED;
$retc = StarCalculator::calculate($starData, $tjd, $iflag_sid, $star_sid, $xx_sid, $serr);

if ($retc < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "Sidereal position:\n";
echo "  Longitude: {$xx_sid[0]}°\n";
echo "  Latitude: {$xx_sid[1]}°\n";
echo "  Distance: {$xx_sid[2]} AU\n";
echo "  dLon/day: {$xx_sid[3]}°\n";
echo "  dLat/day: {$xx_sid[4]}°\n\n";

// Calculate differences
$diff_lon = $xx_trop[0] - $xx_sid[0];
$diff_lat = $xx_trop[1] - $xx_sid[1];

echo "=== Tropical vs Sidereal Difference ===\n";
echo "  ΔLongitude: " . number_format($diff_lon, 6) . "°\n";
echo "  ΔLatitude: " . number_format($diff_lat, 6) . "°\n";
echo "  Expected: Ayanamsha ~24° (Fagan-Bradley at J2000)\n\n";

// Test 3: Sidereal Lahiri
echo "=== Test 3: Sidereal Lahiri ===\n";
State::setSidMode(Constants::SE_SIDM_LAHIRI, 0, 0.0, 0.0);
$xx_lahiri = [];
$star_lahiri = $starName;
$serr = null;
$retc = StarCalculator::calculate($starData, $tjd, $iflag_sid, $star_lahiri, $xx_lahiri, $serr);

if ($retc < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "Sidereal position (Lahiri):\n";
echo "  Longitude: {$xx_lahiri[0]}°\n";
echo "  Latitude: {$xx_lahiri[1]}°\n";
echo "  Distance: {$xx_lahiri[2]} AU\n\n";

$diff_fb_lahiri = $xx_sid[0] - $xx_lahiri[0];
echo "Fagan-Bradley vs Lahiri difference: " . number_format($diff_fb_lahiri, 6) . "°\n";
echo "Expected: ~0.2° (small difference between ayanamsha systems)\n\n";

echo "=== Tests completed ===\n";
