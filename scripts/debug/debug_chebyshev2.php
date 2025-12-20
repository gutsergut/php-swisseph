<?php
/**
 * Debug Chebyshev polynomial computation
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

// Force read of record
$jd = 2459984.5;  // segment start
$rrd = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

// Get internal state
$ref = new ReflectionClass($jpl);
$pc = $ref->getProperty('pc');
$pc->setAccessible(true);
$pcVal = $pc->getValue($jpl);

$twot = $ref->getProperty('twot');
$twot->setAccessible(true);
$twotVal = $twot->getValue($jpl);

echo "After pleph for JD $jd:\n";
echo "twot = $twotVal\n";
echo "pc[0] = " . $pcVal[0] . " (should be 1)\n";
echo "pc[1] = " . $pcVal[1] . " (should be tc = twot/2)\n";
echo "pc[2] = " . $pcVal[2] . " (should be 2*tc^2 - 1)\n";

$tc = $pcVal[1];
echo "\ntc = $tc\n";
echo "Expected tc at segment start: depends on sub-interval\n";

// At JD 2459984.5 which is start of 64-day segment
// With na=2 (two 32-day sub-intervals), t=0
// temp = na * t = 2 * 0 = 0
// ni = (int)(0 - 0) = 0 (sub-interval 0)
// tc = (fmod(0, 1.0) + 0) * 2.0 - 1.0 = -1

echo "\nFor t=0 (segment start):\n";
echo "  temp = na * t = 2 * 0 = 0\n";
echo "  ni = 0 (sub-interval 0)\n";
echo "  tc = (0 + 0) * 2 - 1 = -1\n";

// Let's verify: T_n(-1) values
echo "\nChebyshev T_n(-1):\n";
echo "  T_0(-1) = 1\n";
echo "  T_1(-1) = -1\n";
echo "  T_2(-1) = 2*(-1)^2 - 1 = 1\n";
echo "  T_3(-1) = -1\n";
echo "  T_4(-1) = 1\n";
echo "  T_n(-1) = (-1)^n\n";

// Now let's compute manually
$buf = $ref->getProperty('buf');
$buf->setAccessible(true);
$bufVal = $buf->getValue($jpl);

$ipt = $ref->getProperty('ehIpt');
$ipt->setAccessible(true);
$iptVal = $ipt->getValue($jpl);

$au = $jpl->getAu();

$embPtr = $iptVal[6] - 1;
$embNcf = $iptVal[7];
$embNa = $iptVal[8];

echo "\n=== Manual calculation for EMB X at tc=-1 ===\n";
$xCoeffs = [];
for ($j = 0; $j < $embNcf; $j++) {
    $xCoeffs[$j] = $bufVal[$embPtr + $j];
}

// T_n(-1) = (-1)^n
$xManual = 0;
for ($j = 0; $j < $embNcf; $j++) {
    $tn = pow(-1, $j);
    $xManual += $xCoeffs[$j] * $tn;
    printf("  c[%d] * T_%d(-1) = %.3f * %.0f = %.3f\n", $j, $j, $xCoeffs[$j], $tn, $xCoeffs[$j] * $tn);
}
printf("\nSum = %.6f km = %.9f AU\n", $xManual, $xManual / $au);

// What does PHP compute?
echo "\nPHP computed X: " . $rrd[0] . " AU\n";

// Difference
printf("Difference: %.9f AU = %.0f km\n", abs($rrd[0]) - abs($xManual / $au), (abs($rrd[0]) - abs($xManual / $au)) * $au);

$jpl->close();
