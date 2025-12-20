<?php
/**
 * Debug: Read IPT from JPL file directly and compare
 */

$ephPath = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/de406e.eph';

$fp = fopen($ephPath, 'rb');
if (!$fp) {
    die("Cannot open file\n");
}

// Skip to IPT position
// TTL: 252 bytes
// CNAM: 2400 bytes
// SS: 24 bytes
// NCON: 4 bytes
// AU: 8 bytes
// EMRAT: 8 bytes
// Total: 2696 bytes

fseek($fp, 2696, SEEK_SET);

// Read IPT (36 int32)
$data = fread($fp, 144);
$ipt = unpack('l36', $data);

echo "IPT values from file:\n";
$bodies = ['Mercury', 'Venus', 'EMB', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune', 'Pluto', 'Moon', 'Sun', 'Nutations'];
for ($i = 0; $i < 12; $i++) {
    $offset = $ipt[$i * 3 + 1];
    $ncf = $ipt[$i * 3 + 2];
    $na = $ipt[$i * 3 + 3];
    printf("%10s: offset=%4d, ncf=%2d, na=%d\n", $bodies[$i], $offset, $ncf, $na);
}

fclose($fp);

// Now test with the class
echo "\n=== Test via JplEphemeris class ===\n";

require_once 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/php-swisseph/vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

// Reset singleton for clean state
JplEphemeris::resetInstance();

$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = null;
$result = $jpl->open($ss, 'de406e.eph', 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/', $serr);

if ($result !== 0) {
    die("Open failed: $serr\n");
}

echo "DE Number: " . $jpl->getDenum() . "\n";
echo "AU: " . $jpl->getAu() . " km\n";
echo "EMRAT: " . $jpl->getEmrat() . "\n";
echo "SS: [{$ss[0]}, {$ss[1]}, {$ss[2]}]\n";

// Use reflection to get private ehIpt
$reflection = new ReflectionClass($jpl);
$prop = $reflection->getProperty('ehIpt');
$prop->setAccessible(true);
$ipt = $prop->getValue($jpl);

// Check doReorder
$propReorder = $reflection->getProperty('doReorder');
$propReorder->setAccessible(true);
$doReorder = $propReorder->getValue($jpl);
echo "doReorder: " . ($doReorder ? 'TRUE' : 'FALSE') . "\n";

echo "\nIPT from class (after possible reorder):\n";
$bodies = ['Mercury', 'Venus', 'EMB', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune', 'Pluto', 'Moon', 'Sun', 'Nutations'];
for ($i = 0; $i < 12; $i++) {
    $offset = $ipt[$i * 3];
    $ncf = $ipt[$i * 3 + 1];
    $na = $ipt[$i * 3 + 2];
    printf("%10s: offset=%4d, ncf=%2d, na=%d\n", $bodies[$i], $offset, $ncf, $na);
}

// Test pleph at segment boundary to see if we get expected results
// First segment starts at ss[0] = -254895.5
// JD at middle of first segment: -254895.5 + 32 = -254863.5

$jdTest = -254863.5;  // Middle of first segment
echo "\n=== Testing Mercury at JD $jdTest (middle of first segment) ===\n";

// Get irecsz and ncoeffs via reflection
$propIrecsz = $reflection->getProperty('irecsz');
$propIrecsz->setAccessible(true);
$irecsz = $propIrecsz->getValue($jpl);

$propNcoeffs = $reflection->getProperty('ncoeffs');
$propNcoeffs->setAccessible(true);
$ncoeffs = $propNcoeffs->getValue($jpl);

echo "irecsz (record size) = $irecsz bytes\n";
echo "ncoeffs = $ncoeffs\n";

// Calculate record number manually
$ss0 = $ss[0];
$ss2 = $ss[2];
$s = $jdTest - 0.5;
$etMn = floor($s);
$etFr = $s - $etMn;
$etMn += 0.5;
$nr = (int)(($etMn - $ss0) / $ss2) + 2;
echo "Calculated record nr = $nr\n";
echo "File offset = " . ($nr * $irecsz) . " bytes\n";

$pv = [];
$result = $jpl->pleph($jdTest, JplConstants::J_MERCURY, JplConstants::J_SBARY, $pv, $serr);

if ($result !== JplConstants::OK) {
    echo "pleph() failed: $serr\n";
} else {
    echo "Mercury barycentric position (AU):\n";
    printf("  X = %.15f\n", $pv[0]);
    printf("  Y = %.15f\n", $pv[1]);
    printf("  Z = %.15f\n", $pv[2]);
}

// Get reference from swetest
echo "\n=== Compare with swetest ===\n";
echo "Run: swetest -edirC:/path -ejplde406e.eph -p2 -bj$jdTest -head -bary -fPx\n";
