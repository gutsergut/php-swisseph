<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\State;

/**
 * Simple test: Can we calculate Earth position?
 */

echo "=== Simple Earth Position Test ===\n\n";

// Try without setting path
$xx = [];
$serr = null;
$retc = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
    Constants::J2000,
    Constants::SE_EARTH,
    0, // No flags
    $xx,
    $serr
);

if ($retc < 0) {
    echo "ERROR: Calculation failed without ephe path: $serr\n";
    echo "Trying with path set...\n\n";

    // Set path and retry
    $ephePath = realpath(__DIR__ . '/../../eph/ephe');
    if ($ephePath === false) {
        die("ERROR: Cannot resolve ephe path\n");
    }
    State::setEphePath($ephePath);
    echo "Path set to: " . State::getEphePath() . "\n\n";

    $xx = [];
    $serr = null;
    $retc = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
        Constants::J2000,
        Constants::SE_EARTH,
        0,
        $xx,
        $serr
    );

    if ($retc < 0) {
        die("ERROR: Still failed: $serr\n");
    }
}

echo "SUCCESS: Earth position calculated\n";
echo "  Longitude: {$xx[0]}°\n";
echo "  Latitude: {$xx[1]}°\n";
echo "  Distance: {$xx[2]} AU\n";

echo "\n=== Test completed ===\n";
