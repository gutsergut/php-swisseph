<?php
/**
 * Direct Chebyshev interpolation test - bypass pleph()
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

// Force record read
$jd = 2460016.5;
$rrd = [];
$jpl->pleph($jd, JplConstants::J_EARTH, JplConstants::J_SUN, $rrd, $serr);

$ref = new ReflectionClass($jpl);
$buf = $ref->getProperty('buf');
$buf->setAccessible(true);
$bufVal = $buf->getValue($jpl);

$ipt = $ref->getProperty('ehIpt');
$ipt->setAccessible(true);
$iptVal = $ipt->getValue($jpl);

$au = $jpl->getAu();

// EMB: ipt[6]=207, ncf=9, na=2
$embPtr = $iptVal[6] - 1;  // 206
$ncf = $iptVal[7];  // 9
$na = $iptVal[8];   // 2

echo "EMB: ptr=$embPtr, ncf=$ncf, na=$na\n";
echo "Segment: {$ss[2]} days\n\n";

// For t=0.5 (JD 2460016.5 is middle of segment JD 2459984.5 - 2460048.5)
$t = 0.5;  // normalized time 0..1 within segment

// Calculate sub-interval and tc
$temp = $na * $t;  // 2 * 0.5 = 1.0
$ni = (int)$temp;   // 1 (second sub-interval)
if ($ni >= $na) $ni = $na - 1;  // clamp

$tc = ($temp - $ni) * 2.0 - 1.0;  // (1.0 - 1) * 2 - 1 = -1

echo "t=$t => temp=$temp, ni=$ni, tc=$tc\n\n";

// Coefficients for sub-interval ni
$ncm = 3;  // X, Y, Z

// Read coefficients for all 3 components
$xCoeffs = [];
$yCoeffs = [];
$zCoeffs = [];

for ($j = 0; $j < $ncf; $j++) {
    $idx = $embPtr + $j + ($ni * $ncm) * $ncf;
    $xCoeffs[$j] = $bufVal[$idx];
    $yCoeffs[$j] = $bufVal[$idx + $ncf];
    $zCoeffs[$j] = $bufVal[$idx + 2*$ncf];
}

// Wait, that formula is wrong. Let me check C code again:
// buf[j + (i + ni * ncm) * ncf]
// for i=0 (X): buf[j + (0 + ni*3)*9] = buf[j + ni*27]
// for i=1 (Y): buf[j + (1 + ni*3)*9] = buf[j + 9 + ni*27]
// for i=2 (Z): buf[j + (2 + ni*3)*9] = buf[j + 18 + ni*27]

echo "Re-reading coefficients with correct formula:\n";
for ($j = 0; $j < $ncf; $j++) {
    // For sub-interval ni, component i: buf[bufStart + j + (i + ni * ncm) * ncf]
    $xIdx = $embPtr + $j + (0 + $ni * $ncm) * $ncf;  // X
    $yIdx = $embPtr + $j + (1 + $ni * $ncm) * $ncf;  // Y
    $zIdx = $embPtr + $j + (2 + $ni * $ncm) * $ncf;  // Z

    $xCoeffs[$j] = $bufVal[$xIdx];
    $yCoeffs[$j] = $bufVal[$yIdx];
    $zCoeffs[$j] = $bufVal[$zIdx];
}

echo "Sub-interval $ni coefficients:\n";
printf("  X[0]=%e  Y[0]=%e  Z[0]=%e\n", $xCoeffs[0], $yCoeffs[0], $zCoeffs[0]);

// Compute Chebyshev polynomials at tc
$pc = array_fill(0, $ncf, 0.0);
$pc[0] = 1.0;
$pc[1] = $tc;
$twot = $tc + $tc;
for ($i = 2; $i < $ncf; $i++) {
    $pc[$i] = $twot * $pc[$i-1] - $pc[$i-2];
}

echo "\nChebyshev polynomials at tc=$tc:\n";
for ($i = 0; $i < $ncf; $i++) {
    printf("  T_%d(%.1f) = %.6f\n", $i, $tc, $pc[$i]);
}

// Interpolate X, Y, Z
$x = 0;
$y = 0;
$z = 0;
for ($j = 0; $j < $ncf; $j++) {
    $x += $pc[$j] * $xCoeffs[$j];
    $y += $pc[$j] * $yCoeffs[$j];
    $z += $pc[$j] * $zCoeffs[$j];
}

echo "\nInterpolated EMB position (km):\n";
printf("  X = %.3f\n", $x);
printf("  Y = %.3f\n", $y);
printf("  Z = %.3f\n", $z);

echo "\nIn AU:\n";
printf("  X = %.9f\n", $x / $au);
printf("  Y = %.9f\n", $y / $au);
printf("  Z = %.9f\n", $z / $au);

// Compare with swetest EMB - let's get it
echo "\n--- Now let's compare with what pleph returns for EMB ---\n";
$embResult = [];
$jpl->pleph($jd, JplConstants::J_EMB, JplConstants::J_SBARY, $embResult, $serr);
echo "pleph EMB (barycentric):\n";
printf("  X = %.9f AU\n", $embResult[0]);
printf("  Y = %.9f AU\n", $embResult[1]);
printf("  Z = %.9f AU\n", $embResult[2]);

$jpl->close();
