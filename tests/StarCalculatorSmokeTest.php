<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\FixedStars\FixedStarData;
use Swisseph\Swe\FixedStars\StarCalculator;
use Swisseph\Swe\FixedStars\StarRegistry;
use Swisseph\State;

/**
 * Basic smoke test for StarCalculator
 * Compare output with original fixstar() for same star
 */

echo "=== StarCalculator Smoke Test ===\n\n";

// Initialize ephemeris path
$ephePath = realpath(__DIR__ . '/../../eph/ephe');
if ($ephePath === false || !is_dir($ephePath)) {
    die("ERROR: Ephemeris path not found: " . (__DIR__ . '/../../eph/ephe') . "\n");
}
State::setEphePath($ephePath);
echo "Ephemeris path set to: " . State::getEphePath() . "\n";
echo "File exists: " . (file_exists($ephePath . '/sepl_18.se1') ? 'yes' : 'no') . "\n\n";

// Load star catalog
$serr = null;
$result = StarRegistry::loadAll($serr);
if ($result !== Constants::SE_OK) {
    die("ERROR: Failed to load catalog: $serr\n");
}

echo "Catalog loaded: " . StarRegistry::getRealCount() . " stars, " . StarRegistry::getNamedCount() . " named\n\n";

// Test star: Sirius
$starname = 'sirius';
$searchResult = StarRegistry::search($starname, $serr);
if ($searchResult === null) {
    die("ERROR: Star '$starname' not found: $serr\n");
}

echo "Found star: {$searchResult->getFullName()}\n";
echo "  Epoch: {$searchResult->epoch}\n";
echo "  RA: " . ($searchResult->ra * Constants::RADTODEG) . "°\n";
echo "  Dec: " . ($searchResult->de * Constants::RADTODEG) . "°\n";
echo "  Mag: {$searchResult->mag}\n\n";

// Test calculation at J2000.0
$tjd = Constants::J2000;
$iflag = 0; // Default flags
$star = '';
$xx = [];
$serr = null;

echo "Calculating position at J2000.0 (JD {$tjd})...\n";
$retc = StarCalculator::calculate($searchResult, $tjd, $iflag, $star, $xx, $serr);

if ($retc < 0) {
    echo "ERROR: Calculation failed: $serr\n";
    exit(1);
}

echo "Result:\n";
echo "  Star: $star\n";
echo "  Longitude: {$xx[0]}°\n";
echo "  Latitude: {$xx[1]}°\n";
echo "  Distance: {$xx[2]} AU\n";
if ($iflag & Constants::SEFLG_SPEED) {
    echo "  dLon/day: {$xx[3]}°\n";
    echo "  dLat/day: {$xx[4]}°\n";
    echo "  dDist/day: {$xx[5]} AU\n";
}

echo "\n=== Test with SEFLG_SPEED ===\n\n";
$iflag2 = Constants::SEFLG_SPEED;
$xx2 = [];
$serr2 = null;
$retc2 = StarCalculator::calculate($searchResult, $tjd, $iflag2, $star, $xx2, $serr2);

if ($retc2 < 0) {
    echo "ERROR: Calculation with speed failed: $serr2\n";
    exit(1);
}

echo "Result with speed:\n";
echo "  Longitude: {$xx2[0]}°\n";
echo "  Latitude: {$xx2[1]}°\n";
echo "  Distance: {$xx2[2]} AU\n";
echo "  dLon/day: {$xx2[3]}°\n";
echo "  dLat/day: {$xx2[4]}°\n";
echo "  dDist/day: {$xx2[5]} AU\n";

echo "\n=== Test with SEFLG_EQUATORIAL ===\n\n";
$iflag3 = Constants::SEFLG_EQUATORIAL;
$xx3 = [];
$serr3 = null;
$retc3 = StarCalculator::calculate($searchResult, $tjd, $iflag3, $star, $xx3, $serr3);

if ($retc3 < 0) {
    echo "ERROR: Calculation in equatorial failed: $serr3\n";
    exit(1);
}

echo "Result in equatorial:\n";
echo "  RA: {$xx3[0]}°\n";
echo "  Dec: {$xx3[1]}°\n";
echo "  Distance: {$xx3[2]} AU\n";

echo "\n=== Tests completed ===\n";
