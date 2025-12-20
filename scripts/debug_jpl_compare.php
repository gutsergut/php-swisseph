<?php
/**
 * Debug JPL ephemeris - compare with swetest64.exe
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$ephFile = 'de406e.eph';
$ephPath = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl';

$jpl = JplEphemeris::getInstance();

$ss = [];
$serr = null;

$result = $jpl->open($ss, $ephFile, $ephPath, $serr);
if ($result !== JplConstants::OK) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "DE{$jpl->getDenum()} loaded, AU={$jpl->getAu()}, EMRAT={$jpl->getEmrat()}\n\n";

// JD for comparison - swetest shows:
// Mars: X=-0.667032539, Y=1.478519021, Z=0.047298527 (helio)
// Earth: X=-0.904891727, Y=0.400876861, Z=0.000000852 (helio)
$jd = 2460000.5;

echo "=== JD $jd ===\n\n";

// Test Mars helio
$rrd = [];
$result = $jpl->pleph($jd, JplConstants::J_MARS, JplConstants::J_SUN, $rrd, $serr);
if ($result === JplConstants::OK) {
    echo "PHP Mars helio:\n";
    echo sprintf("  X: %15.9f\n", $rrd[0]);
    echo sprintf("  Y: %15.9f\n", $rrd[1]);
    echo sprintf("  Z: %15.9f\n", $rrd[2]);
    echo "\nswetest reference:\n";
    echo "  X: -0.667032539\n";
    echo "  Y:  1.478519021\n";
    echo "  Z:  0.047298527\n";
    echo sprintf("\nDiff X: %.9f\n", $rrd[0] - (-0.667032539));
    echo sprintf("Diff Y: %.9f\n", $rrd[1] - 1.478519021);
    echo sprintf("Diff Z: %.9f\n", $rrd[2] - 0.047298527);
}

echo "\n";

// Test Earth helio
$result = $jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);
if ($result === JplConstants::OK) {
    echo "PHP Earth helio:\n";
    echo sprintf("  X: %15.9f\n", $rrd[0]);
    echo sprintf("  Y: %15.9f\n", $rrd[1]);
    echo sprintf("  Z: %15.9f\n", $rrd[2]);
    echo "\nswetest reference:\n";
    echo "  X: -0.904891727\n";
    echo "  Y:  0.400876861\n";
    echo "  Z:  0.000000852\n";
    echo sprintf("\nDiff X: %.9f\n", $rrd[0] - (-0.904891727));
    echo sprintf("Diff Y: %.9f\n", $rrd[1] - 0.400876861);
    echo sprintf("Diff Z: %.9f\n", $rrd[2] - 0.000000852);
}

echo "\n";

// Mars relative to barycenter
$result = $jpl->pleph($jd, JplConstants::J_MARS, JplConstants::J_SBARY, $rrd, $serr);
if ($result === JplConstants::OK) {
    echo "PHP Mars barycentric:\n";
    echo sprintf("  X: %15.9f\n", $rrd[0]);
    echo sprintf("  Y: %15.9f\n", $rrd[1]);
    echo sprintf("  Z: %15.9f\n", $rrd[2]);
}

echo "\n";

// Sun relative to barycenter
$result = $jpl->pleph($jd, JplConstants::J_SUN, JplConstants::J_SBARY, $rrd, $serr);
if ($result === JplConstants::OK) {
    echo "PHP Sun barycentric (should be small ~0.001-0.01 AU):\n";
    echo sprintf("  X: %15.9f\n", $rrd[0]);
    echo sprintf("  Y: %15.9f\n", $rrd[1]);
    echo sprintf("  Z: %15.9f\n", $rrd[2]);
}

$jpl->close();
