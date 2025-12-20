<?php
/**
 * Debug: Compare PHP buffer reading with C debug output
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

JplEphemeris::resetInstance();
$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = '';
$jpl->open($ss, 'de200.eph', 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/', $serr);

// Use reflection to access private properties
$reflection = new ReflectionClass($jpl);

$propIpt = $reflection->getProperty('ehIpt');
$propIpt->setAccessible(true);
$ipt = $propIpt->getValue($jpl);

$propAu = $reflection->getProperty('ehAu');
$propAu->setAccessible(true);
$au = $propAu->getValue($jpl);

echo "=== IPT for Mercury ===\n";
printf("ipt[0] (offset) = %d\n", $ipt[0]);
printf("ipt[1] (ncf)    = %d\n", $ipt[1]);
printf("ipt[2] (na)     = %d\n", $ipt[2]);

echo "\n=== IPT for Sun ===\n";
printf("ipt[30] (offset) = %d\n", $ipt[30]);
printf("ipt[31] (ncf)    = %d\n", $ipt[31]);
printf("ipt[32] (na)     = %d\n", $ipt[32]);

echo "\n=== AU ===\n";
printf("AU = %.15f km\n", $au);

// Force buffer loading by calling pleph
$p = [];
$jpl->pleph(2451545.0, JplConstants::J_MERCURY, JplConstants::J_SBARY, $p, $serr);

// Get buffer
$propBuf = $reflection->getProperty('buf');
$propBuf->setAccessible(true);
$buf = $propBuf->getValue($jpl);

echo "\n=== Buffer values ===\n";
printf("buf[0] = %.1f (should be segment start JD)\n", $buf[0]);
printf("buf[1] = %.1f (should be segment end JD)\n", $buf[1]);

// Mercury X coefficients at buf[38..49] (for ni=1)
echo "\n=== Mercury X coefficients (ni=1) ===\n";
echo "Expected from C debug:\n";
echo "buf[38] = -8.96676148966892622411e+06\n";
echo "\nPHP values:\n";
for ($j = 38; $j < 50; $j++) {
    printf("buf[%d] = %.20e\n", $j, $buf[$j]);
}

// Calculate interpolation manually
echo "\n=== Manual interpolation ===\n";

$t = 0.265625;
$na = 4;
$ncf = 12;
$ncm = 3;

$dt1 = floor($t);
$temp = $na * $t;
$ni = (int)($temp - $dt1);
$tc = (fmod($temp, 1.0) + $dt1) * 2.0 - 1.0;
$twot = $tc + $tc;

printf("t=%.15f, na=%d, dt1=%.1f, temp=%.15f\n", $t, $na, $dt1, $temp);
printf("ni=%d, tc=%.15f, twot=%.15f\n", $ni, $tc, $twot);

// Chebyshev polynomials
$pc = [1.0, $tc];
for ($i = 2; $i < $ncf; $i++) {
    $pc[$i] = $twot * $pc[$i-1] - $pc[$i-2];
}

echo "\nChebyshev polynomials:\n";
for ($i = 0; $i < $ncf; $i++) {
    printf("pc[%d] = %.20f\n", $i, $pc[$i]);
}

// Interpolate X
$bufStart = $ipt[0] - 1;  // = 2
$xStart = $bufStart + (0 + $ni * $ncm) * $ncf;  // = 2 + (0 + 1*3)*12 = 2 + 36 = 38

echo "\nbufStart = $bufStart, xStart = $xStart\n";

$x = 0.0;
for ($j = $ncf - 1; $j >= 0; $j--) {
    $x += $pc[$j] * $buf[$xStart + $j];
}

printf("\nX (km) = %.15f\n", $x);
printf("X (AU) = %.20f\n", $x / $au);

// Compare with expected from swetest
echo "\n=== Comparison with swetest -ejplde200.eph -t12 ===\n";
echo "swetest X = -0.137290981 AU\n";
printf("PHP X     = %.9f AU\n", $x / $au);
printf("Diff      = %.9f AU = %.0f km\n", ($x / $au) - (-0.137290981), (($x / $au) - (-0.137290981)) * $au);
