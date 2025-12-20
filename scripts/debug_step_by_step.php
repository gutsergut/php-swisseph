<?php
/**
 * Step-by-step comparison with swetest/C implementation
 * For finding exact source of ~5-10 km discrepancy
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Jpl\JplEphemeris;
use Swisseph\Swe\Jpl\JplConstants;

$ephDir = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl';

// Use DE200 (little-endian, simpler)
JplEphemeris::resetInstance();
$jpl = JplEphemeris::getInstance();
$ss = [];
$serr = '';

$ret = $jpl->open($ss, 'de200.eph', $ephDir, $serr);
if ($ret < 0) {
    die("Failed to open: $serr\n");
}

echo "=== Step 1: Header values ===\n";
echo "SS[0] (start)   = " . sprintf("%.15f", $ss[0]) . "\n";
echo "SS[1] (end)     = " . sprintf("%.15f", $ss[1]) . "\n";
echo "SS[2] (segment) = " . sprintf("%.15f", $ss[2]) . "\n";
echo "AU              = " . sprintf("%.15f", $jpl->getAu()) . "\n";
echo "EMRAT           = " . sprintf("%.15f", $jpl->getEmrat()) . "\n";
echo "DENUM           = " . $jpl->getDenum() . "\n";

// Test date
$jd = 2451545.0;  // J2000
echo "\n=== Step 2: Time calculation for JD $jd ===\n";

// Replicate state() time calculation
$s = $jd - 0.5;
$etMn = floor($s);
$etFr = $s - $etMn;
$etMn += 0.5;

echo "s     = " . sprintf("%.15f", $s) . "\n";
echo "etMn  = " . sprintf("%.15f", $etMn) . "\n";
echo "etFr  = " . sprintf("%.15f", $etFr) . "\n";

$nr = (int)(($etMn - $ss[0]) / $ss[2]) + 2;
echo "nr    = $nr\n";

$segStart = ($nr - 2) * $ss[2] + $ss[0];
$t = ($etMn - $segStart + $etFr) / $ss[2];
echo "segStart = " . sprintf("%.15f", $segStart) . "\n";
echo "t     = " . sprintf("%.20f", $t) . "\n";

// Mercury IPT for DE200
$ncf = 12;
$na = 4;
$ncm = 3;
$bufStart = 2;  // ipt[0] - 1 = 3 - 1

echo "\n=== Step 3: Sub-interval calculation ===\n";
if ($t >= 0) {
    $dt1 = floor($t);
} else {
    $dt1 = -floor(-$t);
}
$temp = $na * $t;
$ni = (int)($temp - $dt1);
$tc = (fmod($temp, 1.0) + $dt1) * 2.0 - 1.0;

echo "dt1   = " . sprintf("%.15f", $dt1) . "\n";
echo "temp  = " . sprintf("%.20f", $temp) . "\n";
echo "ni    = $ni\n";
echo "tc    = " . sprintf("%.20f", $tc) . "\n";
echo "twot  = " . sprintf("%.20f", $tc + $tc) . "\n";

echo "\n=== Step 4: Chebyshev polynomials ===\n";
$pc = array_fill(0, $ncf, 0.0);
$pc[0] = 1.0;
$pc[1] = $tc;
$twot = $tc + $tc;

for ($i = 2; $i < $ncf; $i++) {
    $pc[$i] = $twot * $pc[$i-1] - $pc[$i-2];
}

for ($i = 0; $i < $ncf; $i++) {
    echo "pc[$i] = " . sprintf("%.20f", $pc[$i]) . "\n";
}

// Now read actual coefficients from file
echo "\n=== Step 5: Read buffer coefficients ===\n";
$ephFile = "$ephDir/de200.eph";
$irecsz = 6608;  // DE200 record size

$fp = fopen($ephFile, 'rb');
fseek($fp, $nr * $irecsz);

$buf = [];
for ($k = 0; $k < 100; $k++) {  // Read first 100 coefficients
    $data = fread($fp, 8);
    $buf[$k] = unpack('d', $data)[1];
}
fclose($fp);

echo "buf[0] = " . sprintf("%.15f", $buf[0]) . " (segment start)\n";
echo "buf[1] = " . sprintf("%.15f", $buf[1]) . " (segment end)\n";

// Mercury X coefficients for sub-interval $ni
echo "\nMercury X coefficients (ni=$ni):\n";
$xStart = $bufStart + ($ni * $ncm) * $ncf;
for ($j = 0; $j < $ncf; $j++) {
    $idx = $xStart + $j;
    echo "buf[$idx] = " . sprintf("%.20e", $buf[$idx]) . "\n";
}

echo "\n=== Step 6: Interpolation ===\n";
$x = 0.0;
for ($j = $ncf - 1; $j >= 0; $j--) {
    $idx = $xStart + $j;
    $contrib = $pc[$j] * $buf[$idx];
    $x += $contrib;
    echo "j=$j: pc * buf[$idx] = " . sprintf("%.20e", $pc[$j]) . " * " . sprintf("%.20e", $buf[$idx]) . " = " . sprintf("%.20e", $contrib) . " => sum = " . sprintf("%.20e", $x) . "\n";
}

echo "\nX (km) = " . sprintf("%.15f", $x) . "\n";
echo "X (AU) = " . sprintf("%.20f", $x / $jpl->getAu()) . "\n";

// Now get the full result via pleph
echo "\n=== Step 7: Via pleph() ===\n";
$pv = [];
$jpl->pleph($jd, JplConstants::J_MERCURY, JplConstants::J_SBARY, $pv, $serr);
echo "pleph X = " . sprintf("%.20f", $pv[0]) . " AU\n";
echo "pleph Y = " . sprintf("%.20f", $pv[1]) . " AU\n";
echo "pleph Z = " . sprintf("%.20f", $pv[2]) . " AU\n";

echo "\n=== Reference (swetest -j2000) ===\n";
echo "Expected X = -0.137290981 AU\n";
echo "Expected Y = -0.403222600 AU\n";
echo "Expected Z = -0.201397090 AU\n";

echo "\n=== Difference ===\n";
$diffX = $pv[0] - (-0.137290981);
$diffY = $pv[1] - (-0.403222600);
$diffZ = $pv[2] - (-0.201397090);
echo "dX = " . sprintf("%.15f", $diffX) . " AU = " . sprintf("%.1f", $diffX * 149597870.66) . " km\n";
echo "dY = " . sprintf("%.15f", $diffY) . " AU = " . sprintf("%.1f", $diffY * 149597870.66) . " km\n";
echo "dZ = " . sprintf("%.15f", $diffZ) . " AU = " . sprintf("%.1f", $diffZ * 149597870.66) . " km\n";
