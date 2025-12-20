<?php
/**
 * Debug: check if first Chebyshev coefficient approximates position at segment center
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$jpl = JplEphemeris::getInstance();
$ss = [];
$jpl->open($ss, 'de406e.eph',
    'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl',
    $serr);

// Force read of record for JD 2460000.5
$jd = 2460000.5;
$rrd = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

// Get segment info
$segStart = 2459984.5;
$segMid = $segStart + 32;  // segment is 64 days, mid is +32

echo "Segment: JD $segStart to " . ($segStart + 64) . "\n";
echo "Segment midpoint: JD $segMid\n\n";

// Access internal data
$ref = new ReflectionClass($jpl);
$buf = $ref->getProperty('buf');
$buf->setAccessible(true);
$bufVal = $buf->getValue($jpl);

$ipt = $ref->getProperty('ehIpt');
$ipt->setAccessible(true);
$iptVal = $ipt->getValue($jpl);

$auProp = $ref->getProperty('ehAu');
$auProp->setAccessible(true);
$au = $auProp->getValue($jpl);

// EMB ptr (index 2), adjusted for 0-based and sub-interval 0
$embPtr = $iptVal[6] - 1;  // 207 - 1 = 206
$embNcf = $iptVal[7];      // 9 coefficients
$embNa = $iptVal[8];       // 2 sub-intervals

echo "EMB: ptr=$embPtr (0-based), ncf=$embNcf, na=$embNa\n";
echo "AU = $au km\n\n";

// First coefficient T0(x) = 1 gives the value at tc=1 (end of sub-interval)
// Actually: sum of even coefficients = value at tc=1
// At tc=0: alternating sum (+c0 -c1 +c2 -c3 ...)
// At tc=1: all positive sum

// For Chebyshev, T0(tc)=1, T1(tc)=tc, T2(tc)=2tc²-1, etc.
// At tc=0: T0=1, T1=0, T2=-1, T3=0, T4=1, ...
// At tc=1: T0=1, T1=1, T2=1, T3=1, ...

// The first coefficient c0 is typically ~ average value over interval

// EMB X coefficients (sub-interval 0)
echo "EMB X coefficients (sub-interval 0):\n";
$xCoeffs = [];
for ($j = 0; $j < $embNcf; $j++) {
    $xCoeffs[$j] = $bufVal[$embPtr + $j];
    printf("  c[%d] = %20.6f km\n", $j, $xCoeffs[$j]);
}

// Compute position at tc=0 (start of sub-interval = JD 2459984.5)
$xAtTc0 = 0;
$sign = 1;
for ($j = 0; $j < $embNcf; $j++) {
    $tn = ($j % 2 == 0) ? 1 : 0;  // T_n(0): 1 for even, 0 for odd
    if ($j % 4 >= 2 && $j % 2 == 0) $tn = -1;  // T2(0)=-1, T4(0)=1, T6(0)=-1
    $xAtTc0 += $xCoeffs[$j] * $tn;
}

// Actually let's compute it properly
// T_n(0) for n=0,1,2,3,4,5,6,7,8: 1,0,-1,0,1,0,-1,0,1
$tn0 = [1, 0, -1, 0, 1, 0, -1, 0, 1];
$xAtTc0 = 0;
for ($j = 0; $j < $embNcf; $j++) {
    $xAtTc0 += $xCoeffs[$j] * $tn0[$j];
}

printf("\nX at tc=0 (sub-interval 0 start): %.6f km = %.9f AU\n", $xAtTc0, $xAtTc0 / $au);

// T_n(1) = 1 for all n
$xAtTc1 = array_sum($xCoeffs);
printf("X at tc=1 (sub-interval 0 end):   %.6f km = %.9f AU\n", $xAtTc1, $xAtTc1 / $au);

// Compare with actual computed value
echo "\n--- Actual computed values ---\n";

// JD at sub-interval 0 start (tc=0 maps to segment start)
$jdStart = 2459984.5;
$rrd = [];
$jpl->pleph($jdStart, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);
printf("PHP X at JD %.1f: %.9f AU\n", $jdStart, $rrd[0]);

// Now get swetest reference for segment start
echo "\nswetest reference at JD 2459984.5:\n";
echo "  X: -0.755127890 AU = " . (-0.755127890 * $au) . " km\n";

// Compare first coefficient with expected X
$expectedX = -0.755127890 * $au;
printf("\nFirst coeff c[0] = %.0f km\n", $xCoeffs[0]);
printf("Expected ~X at center: %.0f km\n", $expectedX);
printf("Difference: %.0f km (%.6f AU)\n", $xCoeffs[0] - $expectedX, ($xCoeffs[0] - $expectedX) / $au);

$jpl->close();
