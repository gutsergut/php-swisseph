<?php
/**
 * Test JPL ephemeris reader with de406e.eph
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$ephFile = 'de406e.eph';
$ephPath = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl';

echo "=== Testing JPL Ephemeris Reader ===\n\n";
echo "File: $ephFile\n";
echo "Path: $ephPath\n\n";

$jpl = JplEphemeris::getInstance();

$ss = [];
$serr = null;

$result = $jpl->open($ss, $ephFile, $ephPath, $serr);

if ($result !== JplConstants::OK) {
    echo "ERROR: Failed to open JPL file: $serr\n";
    exit(1);
}

echo "SUCCESS: JPL file opened successfully!\n\n";
echo "Date range:\n";
echo "  Start JD: " . $ss[0] . " (" . jdToDate($ss[0]) . ")\n";
echo "  End JD:   " . $ss[1] . " (" . jdToDate($ss[1]) . ")\n";
echo "  Segment:  " . $ss[2] . " days\n\n";

echo "Constants:\n";
echo "  DE number: " . $jpl->getDenum() . "\n";
echo "  AU:        " . $jpl->getAu() . " km\n";
echo "  EMRAT:     " . $jpl->getEmrat() . "\n\n";

// Test calculation for current date
$jd = 2460000.5;  // Around Jan 2023
echo "Test calculation for JD $jd:\n\n";

// Calculate Mars position relative to Sun
$rrd = [];
$serr = null;

$result = $jpl->pleph($jd, JplConstants::J_MARS, JplConstants::J_SUN, $rrd, $serr);

if ($result === JplConstants::OK) {
    echo "Mars relative to Sun:\n";
    echo sprintf("  X: %20.10f AU\n", $rrd[0]);
    echo sprintf("  Y: %20.10f AU\n", $rrd[1]);
    echo sprintf("  Z: %20.10f AU\n", $rrd[2]);
    echo sprintf("  VX: %20.10f AU/day\n", $rrd[3]);
    echo sprintf("  VY: %20.10f AU/day\n", $rrd[4]);
    echo sprintf("  VZ: %20.10f AU/day\n", $rrd[5]);

    $distance = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
    echo sprintf("  Distance: %15.10f AU (%.0f km)\n", $distance, $distance * $jpl->getAu());
} else {
    echo "ERROR calculating Mars: $serr\n";
}

echo "\n";

// Calculate Earth position relative to Sun
$result = $jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

if ($result === JplConstants::OK) {
    echo "Earth relative to Sun:\n";
    echo sprintf("  X: %20.10f AU\n", $rrd[0]);
    echo sprintf("  Y: %20.10f AU\n", $rrd[1]);
    echo sprintf("  Z: %20.10f AU\n", $rrd[2]);

    $distance = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
    echo sprintf("  Distance: %15.10f AU (%.0f km)\n", $distance, $distance * $jpl->getAu());
} else {
    echo "ERROR calculating Earth: $serr\n";
}

echo "\n";

// Calculate Moon position relative to Earth
$result = $jpl->pleph($jd, JplConstants::J_MOON, JplConstants::J_EARTH, $rrd, $serr);

if ($result === JplConstants::OK) {
    echo "Moon relative to Earth:\n";
    echo sprintf("  X: %20.10f AU\n", $rrd[0]);
    echo sprintf("  Y: %20.10f AU\n", $rrd[1]);
    echo sprintf("  Z: %20.10f AU\n", $rrd[2]);

    $distance = sqrt($rrd[0]**2 + $rrd[1]**2 + $rrd[2]**2);
    echo sprintf("  Distance: %15.10f AU (%.0f km)\n", $distance, $distance * $jpl->getAu());
} else {
    echo "ERROR calculating Moon: $serr\n";
}

$jpl->close();

echo "\nDone.\n";

function jdToDate(float $jd): string
{
    $z = (int)($jd + 0.5);
    $f = $jd + 0.5 - $z;

    if ($z < 2299161) {
        $a = $z;
    } else {
        $alpha = (int)(($z - 1867216.25) / 36524.25);
        $a = $z + 1 + $alpha - (int)($alpha / 4);
    }

    $b = $a + 1524;
    $c = (int)(($b - 122.1) / 365.25);
    $d = (int)(365.25 * $c);
    $e = (int)(($b - $d) / 30.6001);

    $day = $b - $d - (int)(30.6001 * $e) + $f;

    if ($e < 14) {
        $month = $e - 1;
    } else {
        $month = $e - 13;
    }

    if ($month > 2) {
        $year = $c - 4716;
    } else {
        $year = $c - 4715;
    }

    return sprintf('%04d-%02d-%02d', $year, $month, (int)$day);
}
