<?php
/**
 * Full debug: read buffer and perform interpolation step-by-step
 * Compare with expected swetest output
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$ephFile = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/de200.eph';

function reorderDouble(float $val): float {
    $packed = pack('d', $val);
    $reversed = strrev($packed);
    return unpack('d', $reversed)[1];
}

function reorderInt32(int $val): int {
    $packed = pack('l', $val);
    $reversed = strrev($packed);
    return unpack('l', $reversed)[1];
}

// Read header
$fp = fopen($ephFile, 'rb');
fseek($fp, 2652); // Skip TTL + CNAM

$ssRaw = fread($fp, 24);
$ss = unpack('d3', $ssRaw);
$doReorder = ($ss[3] < 1 || $ss[3] > 200);

if ($doReorder) {
    $ss[1] = reorderDouble($ss[1]);
    $ss[2] = reorderDouble($ss[2]);
    $ss[3] = reorderDouble($ss[3]);
}
$ehSs = [$ss[1], $ss[2], $ss[3]];

// Read NCON
$data = fread($fp, 4);
$ncon = unpack('l', $data)[1];
if ($doReorder) $ncon = reorderInt32($ncon);

// Read AU
$data = fread($fp, 8);
$au = unpack('d', $data)[1];
if ($doReorder) $au = reorderDouble($au);

// Read EMRAT
$data = fread($fp, 8);
$emrat = unpack('d', $data)[1];
if ($doReorder) $emrat = reorderDouble($emrat);

// Read IPT
$data = fread($fp, 144);
$iptRaw = unpack('l36', $data);
$ipt = [];
for ($i = 0; $i < 36; $i++) {
    $ipt[$i] = $iptRaw[$i + 1];
    if ($doReorder) {
        $ipt[$i] = reorderInt32($ipt[$i]);
    }
}

echo "SS: [{$ehSs[0]}, {$ehSs[1]}, {$ehSs[2]}]\n";
echo "AU: $au\n";
echo "Mercury IPT: offset={$ipt[0]}, ncf={$ipt[1]}, na={$ipt[2]}\n\n";

// Calculate record size (same as JplEphemeris)
$kmx = 0;
$khi = 0;
for ($i = 0; $i < 13; $i++) {
    if ($ipt[$i * 3] > $kmx) {
        $kmx = $ipt[$i * 3];
        $khi = $i + 1;
    }
}
$nd = ($khi === 12) ? 2 : 3;
$ksize = ($ipt[$khi * 3 - 3] + $nd * $ipt[$khi * 3 - 2] * $ipt[$khi * 3 - 1] - 1) * 2;
$irecsz = 4 * $ksize;
$ncoeffs = (int)($ksize / 2);

echo "irecsz = $irecsz, ncoeffs = $ncoeffs\n\n";

// Test at early date in DE200
$jd = 2305500.0;
echo "=== Testing Mercury at JD $jd (early DE200) ===\n\n";

// Calculate record number
$s = $jd - 0.5;
$etMn = floor($s);
$etFr = $s - $etMn;
$etMn += 0.5;

$nr = (int)(($etMn - $ehSs[0]) / $ehSs[2]) + 2;
if ($etMn === $ehSs[1]) $nr--;

// Normalized time within segment
$t = ($etMn - (($nr - 2) * $ehSs[2] + $ehSs[0]) + $etFr) / $ehSs[2];

echo "Record nr = $nr\n";
echo "t = $t\n";

// Read buffer from file
fseek($fp, $nr * $irecsz);
$buf = [];
for ($k = 0; $k < $ncoeffs; $k++) {
    $data = fread($fp, 8);
    $buf[$k] = unpack('d', $data)[1];
    if ($doReorder) {
        $buf[$k] = reorderDouble($buf[$k]);
    }
}

echo "buf[0] (segment start) = {$buf[0]}\n";
echo "buf[1] (segment end) = {$buf[1]}\n";

// Verify segment contains our date
$segStart = $buf[0];
$segEnd = $buf[1];
if ($jd < $segStart || $jd > $segEnd) {
    echo "WARNING: JD $jd not in segment [$segStart, $segEnd]\n";
}

// Set up intv and aufac (for AU output)
$intv = $ehSs[2];  // 64 days
$aufac = 1.0 / $au;

// Interpolate Mercury
$bufStart = $ipt[0] - 1;  // 2
$ncf = $ipt[1];           // 14
$na = $ipt[2];            // 4
$ncm = 3;                 // X, Y, Z

echo "\nMercury: bufStart=$bufStart, ncf=$ncf, na=$na\n";

// interp calculations
if ($t >= 0) {
    $dt1 = floor($t);
} else {
    $dt1 = -floor(-$t);
}
$temp = $na * $t;
$ni = (int)($temp - $dt1);
$tc = (fmod($temp, 1.0) + $dt1) * 2.0 - 1.0;

echo "dt1 = $dt1, temp = $temp, ni = $ni, tc = $tc\n";

// Chebyshev polynomials
$pc = [1.0, $tc];
$twot = 2.0 * $tc;
for ($i = 2; $i < $ncf; $i++) {
    $pc[$i] = $twot * $pc[$i-1] - $pc[$i-2];
}

// Interpolate position
$pv = [];
for ($i = 0; $i < $ncm; $i++) {
    $pv[$i] = 0.0;
    for ($j = $ncf - 1; $j >= 0; $j--) {
        $idx = $bufStart + $j + ($i + $ni * $ncm) * $ncf;
        $pv[$i] += $pc[$j] * $buf[$idx];
    }
}

echo "\nMercury barycentric (km):\n";
echo "  X = {$pv[0]}\n";
echo "  Y = {$pv[1]}\n";
echo "  Z = {$pv[2]}\n";

echo "\nMercury barycentric (AU):\n";
echo "  X = " . ($pv[0] * $aufac) . "\n";
echo "  Y = " . ($pv[1] * $aufac) . "\n";
echo "  Z = " . ($pv[2] * $aufac) . "\n";

// Show coefficients used
echo "\nCoefficients used for X (bufStart + j + (0 + $ni * 3) * 14):\n";
for ($j = 0; $j < $ncf; $j++) {
    $idx = $bufStart + $j + (0 + $ni * 3) * $ncf;
    printf("  buf[%3d] = %.15e, pc[$j] = %.10f\n", $idx, $buf[$idx], $pc[$j]);
}

fclose($fp);

echo "\n=== Now run swetest to compare ===\n";
echo "swetest -edirC:/... -ejplde406e.eph -p2 -bj2451545.0 -fPx -head -bary\n";
