<?php
/**
 * Debug JPL buffer coefficients for EMB
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$jpl = JplEphemeris::getInstance();

$ss = [];
$serr = null;
$result = $jpl->open($ss, 'de406e.eph',
    'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl',
    $serr);

if ($result !== JplConstants::OK) {
    echo "ERROR: $serr\n";
    exit(1);
}

// Force read of a record by calling pleph
$jd = 2460000.5;
$rrd = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

// Use reflection to access private properties
$ref = new ReflectionClass($jpl);

$ipt = $ref->getProperty('ehIpt');
$ipt->setAccessible(true);
$iptVal = $ipt->getValue($jpl);

$buf = $ref->getProperty('buf');
$buf->setAccessible(true);
$bufVal = $buf->getValue($jpl);

$nrl = $ref->getProperty('nrl');
$nrl->setAccessible(true);
$nrlVal = $nrl->getValue($jpl);

echo "Current record: $nrlVal\n\n";

// EMB is index 2
// ptr=207, ncf=9, na=2
$embPtr = $iptVal[6] - 1;  // ipt[2*3] = 207, minus 1 for 0-based
$embNcf = $iptVal[7];      // 9
$embNa = $iptVal[8];       // 2
$embNcm = 3;

echo "EMB coefficients (ptr=$embPtr, ncf=$embNcf, na=$embNa):\n\n";

// Print first few coefficients for X component (sub-interval 0)
echo "X coefficients (sub-interval 0):\n";
for ($j = 0; $j < $embNcf; $j++) {
    $idx = $embPtr + $j;
    printf("  buf[%3d] = %20.12e\n", $idx, $bufVal[$idx]);
}

echo "\nY coefficients (sub-interval 0):\n";
for ($j = 0; $j < $embNcf; $j++) {
    $idx = $embPtr + $embNcf + $j;  // Y is after X
    printf("  buf[%3d] = %20.12e\n", $idx, $bufVal[$idx]);
}

echo "\nZ coefficients (sub-interval 0):\n";
for ($j = 0; $j < $embNcf; $j++) {
    $idx = $embPtr + 2 * $embNcf + $j;  // Z is after Y
    printf("  buf[%3d] = %20.12e\n", $idx, $bufVal[$idx]);
}

// Check if Z coefficients are suspiciously large
$zStart = $embPtr + 2 * $embNcf;
$zCoeffs = array_slice($bufVal, $zStart, $embNcf);
$maxZ = max(array_map('abs', $zCoeffs));
echo "\nMax absolute Z coeff for EMB: $maxZ\n";

// Compare with expected order of magnitude
// For ecliptic coordinates, Z should be ~0 for EMB
// X and Y should be ~0.99 to 1.01 AU scale

echo "\n=== Checking coefficient magnitudes ===\n";
echo "Expected: X,Y ~1e8 km, Z ~1e5 km (ecliptic)\n\n";

$auKm = $jpl->getAu();
echo "AU = $auKm km\n\n";

$xCoeffs = array_slice($bufVal, $embPtr, $embNcf);
$yCoeffs = array_slice($bufVal, $embPtr + $embNcf, $embNcf);

printf("X[0] = %.3e km (%.6f AU)\n", $xCoeffs[0], $xCoeffs[0] / $auKm);
printf("Y[0] = %.3e km (%.6f AU)\n", $yCoeffs[0], $yCoeffs[0] / $auKm);
printf("Z[0] = %.3e km (%.6f AU)\n", $zCoeffs[0], $zCoeffs[0] / $auKm);

$jpl->close();
