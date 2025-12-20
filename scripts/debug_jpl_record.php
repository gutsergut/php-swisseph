<?php
/**
 * Debug JPL record calculation
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

echo "DE{$jpl->getDenum()}\n";
echo "Date range: JD {$ss[0]} to {$ss[1]}\n";
echo "Segment: {$ss[2]} days\n\n";

$jd = 2460000.5;

echo "Test JD: $jd\n\n";

// Calculate record number manually
$s = $jd - 0.5;
$etMn = floor($s);
$etFr = $s - $etMn;
$etMn += 0.5;

echo "etMn = $etMn (date part)\n";
echo "etFr = $etFr (fraction part)\n";

$nr = (int)(($etMn - $ss[0]) / $ss[2]) + 2;
if ($etMn === $ss[1]) {
    $nr--;
}

echo "Record number: $nr\n";

// Normalized time
$t = ($etMn - (($nr - 2) * $ss[2] + $ss[0]) + $etFr) / $ss[2];
echo "Normalized t = $t (should be 0 <= t <= 1)\n\n";

// Check segment start/end
$segStart = ($nr - 2) * $ss[2] + $ss[0];
$segEnd = $segStart + $ss[2];
echo "Segment covers: JD $segStart to $segEnd\n";
echo "Our JD $jd is in this segment: " . (($jd >= $segStart && $jd <= $segEnd) ? "YES" : "NO") . "\n\n";

// Check record offset
$ref = new ReflectionClass($jpl);
$irecszProp = $ref->getProperty('irecsz');
$irecszProp->setAccessible(true);
$irecsz = $irecszProp->getValue($jpl);

echo "Record size: $irecsz bytes\n";
echo "Offset in file: " . ($nr * $irecsz) . " bytes\n";

$jpl->close();
