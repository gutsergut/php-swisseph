<?php
/**
 * Test JPL DE440 ephemeris reading
 */

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

echo "=== JPL DE440 Ephemeris Test ===\n\n";

$jplPath = __DIR__ . '/../../eph/data/ephemerides/jpl';
$jplFile = 'de440.eph';

echo "Path: $jplPath\n";
echo "File: $jplFile\n\n";

// Check file exists
$fullPath = $jplPath . '/' . $jplFile;
if (!file_exists($fullPath)) {
    echo "ERROR: File not found: $fullPath\n";
    exit(1);
}

echo "File size: " . filesize($fullPath) . " bytes\n\n";

// Open JPL ephemeris
$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = null;

$ret = $jpl->open($ss, $jplFile, $jplPath, $serr);

if ($ret !== JplConstants::OK) {
    echo "ERROR: Failed to open: $serr\n";
    exit(1);
}

echo "=== File Header ===\n";
echo "DE Number: " . $jpl->getDenum() . "\n";
echo "Start epoch: " . $ss[0] . " JD\n";
echo "End epoch: " . $ss[1] . " JD\n";
echo "Segment size: " . $ss[2] . " days\n";
echo "EMRAT: " . $jpl->getEmrat() . "\n";
echo "AU: " . $jpl->getAu() . " km\n\n";

// Test reading planet positions at J2000.0
echo "=== Planet Positions at J2000.0 (JD 2451545.0) ===\n\n";

$jd = 2451545.0;

// Test Sun relative to SSB
$rrd = [];
$ret = $jpl->pleph($jd, JplConstants::J_SUN, JplConstants::J_SBARY, $rrd, $serr);
if ($ret === JplConstants::OK) {
    printf("Sun (rel SSB):\n");
    printf("  pos: [%.15f, %.15f, %.15f] AU\n", $rrd[0], $rrd[1], $rrd[2]);
    printf("  vel: [%.15f, %.15f, %.15f] AU/day\n", $rrd[3], $rrd[4], $rrd[5]);
} else {
    echo "ERROR: Sun calculation failed: $serr\n";
}

echo "\n";

// Test Moon relative to Earth
$rrd = [];
$ret = $jpl->pleph($jd, JplConstants::J_MOON, JplConstants::J_EARTH, $rrd, $serr);
if ($ret === JplConstants::OK) {
    printf("Moon (rel Earth):\n");
    printf("  pos: [%.15f, %.15f, %.15f] AU\n", $rrd[0], $rrd[1], $rrd[2]);
    printf("  vel: [%.15f, %.15f, %.15f] AU/day\n", $rrd[3], $rrd[4], $rrd[5]);
    $dist = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
    printf("  distance: %.9f AU (%.1f km)\n", $dist, $dist * 149597870.7);
} else {
    echo "ERROR: Moon calculation failed: $serr\n";
}

echo "\n";

// Test Mercury relative to Sun (heliocentric)
$rrd = [];
$ret = $jpl->pleph($jd, JplConstants::J_MERCURY, JplConstants::J_SUN, $rrd, $serr);
if ($ret === JplConstants::OK) {
    printf("Mercury (rel Sun):\n");
    printf("  pos: [%.15f, %.15f, %.15f] AU\n", $rrd[0], $rrd[1], $rrd[2]);
    printf("  vel: [%.15f, %.15f, %.15f] AU/day\n", $rrd[3], $rrd[4], $rrd[5]);
    $dist = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
    printf("  distance: %.9f AU\n", $dist);
} else {
    echo "ERROR: Mercury calculation failed: $serr\n";
}

echo "\n";

// Test Jupiter relative to Sun
$rrd = [];
$ret = $jpl->pleph($jd, JplConstants::J_JUPITER, JplConstants::J_SUN, $rrd, $serr);
if ($ret === JplConstants::OK) {
    printf("Jupiter (rel Sun):\n");
    printf("  pos: [%.15f, %.15f, %.15f] AU\n", $rrd[0], $rrd[1], $rrd[2]);
    printf("  vel: [%.15f, %.15f, %.15f] AU/day\n", $rrd[3], $rrd[4], $rrd[5]);
    $dist = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
    printf("  distance: %.9f AU\n", $dist);
} else {
    echo "ERROR: Jupiter calculation failed: $serr\n";
}

$jpl->close();

echo "\n✓ JPL DE440 test completed\n";
