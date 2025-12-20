<?php
/**
 * Manual Chebyshev interpolation for JD 2305500
 * With full debug output
 */

declare(strict_types=1);

$ephFile = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/de200.eph';

$ss = [2305424.5, 2513392.5, 32.0];
$jd = 2305500.0;
$AU = 149597870.66;

// Time calculations
$s = $jd - 0.5;
$etMn = floor($s);
$etFr = $s - $etMn;
$etMn += 0.5;

$nr = (int)(($etMn - $ss[0]) / $ss[2]) + 2;  // = 4
$segStart = ($nr - 2) * $ss[2] + $ss[0];     // = 2305488.5
$t = ($etMn - $segStart + $etFr) / $ss[2];   // = 0.359375

echo "JD = $jd\n";
echo "Record = $nr, Segment = [$segStart, " . ($segStart + $ss[2]) . "]\n";
echo "t = $t\n\n";

// Mercury parameters for DE200
$ipt_mercury = [3, 12, 4];  // offset, ncf, na
$bufStart = $ipt_mercury[0] - 1;  // 2
$ncf = $ipt_mercury[1];           // 12
$na = $ipt_mercury[2];            // 4
$ncm = 3;

// Read record
$irecsz = 6608;
$ncoeffs = 826;

$fp = fopen($ephFile, 'rb');
fseek($fp, $nr * $irecsz);
$buf = [];
for ($k = 0; $k < $ncoeffs; $k++) {
    $data = fread($fp, 8);
    $buf[$k] = unpack('d', $data)[1];
}
fclose($fp);

echo "buf[0] = {$buf[0]} (segment start)\n";
echo "buf[1] = {$buf[1]} (segment end)\n\n";

// Sub-interval calculation
if ($t >= 0) {
    $dt1 = floor($t);
} else {
    $dt1 = -floor(-$t);
}
$temp = $na * $t;          // 4 * 0.359375 = 1.4375
$ni = (int)($temp - $dt1); // (int)(1.4375 - 0) = 1

// tc is normalized Chebyshev time (-1 <= tc <= 1)
$tc = (fmod($temp, 1.0) + $dt1) * 2.0 - 1.0;  // (0.4375 + 0) * 2 - 1 = -0.125

echo "na = $na, ncf = $ncf, ncm = $ncm\n";
echo "temp = na * t = $temp\n";
echo "dt1 = $dt1\n";
echo "ni = $ni (sub-interval)\n";
echo "tc = $tc (Chebyshev time)\n\n";

// Chebyshev polynomials
$pc = [1.0, $tc];
$twot = 2.0 * $tc;
for ($i = 2; $i < $ncf; $i++) {
    $pc[$i] = $twot * $pc[$i-1] - $pc[$i-2];
}

echo "Chebyshev polynomials:\n";
for ($i = 0; $i < $ncf; $i++) {
    printf("  pc[%2d] = %.10f\n", $i, $pc[$i]);
}

// Interpolate position for each component
$pv = [0.0, 0.0, 0.0];
echo "\nInterpolation:\n";

for ($i = 0; $i < $ncm; $i++) {
    $compName = ['X', 'Y', 'Z'][$i];
    $pv[$i] = 0.0;

    echo "\n$compName component (i=$i):\n";
    echo "  Coefficient indices: bufStart + j + (i + ni*ncm)*ncf = $bufStart + j + ($i + $ni*$ncm)*$ncf\n";

    for ($j = $ncf - 1; $j >= 0; $j--) {
        $idx = $bufStart + $j + ($i + $ni * $ncm) * $ncf;
        $coeff = $buf[$idx];
        $contrib = $pc[$j] * $coeff;
        $pv[$i] += $contrib;

        if ($j >= $ncf - 3 || $j <= 2) {
            printf("    j=%2d: idx=%3d, coeff=%.6e, pc=%.10f, contrib=%.6e, sum=%.6e\n",
                   $j, $idx, $coeff, $pc[$j], $contrib, $pv[$i]);
        } elseif ($j == $ncf - 4) {
            echo "    ...\n";
        }
    }
}

echo "\nResult in km:\n";
printf("  X = %.6f\n", $pv[0]);
printf("  Y = %.6f\n", $pv[1]);
printf("  Z = %.6f\n", $pv[2]);

echo "\nResult in AU:\n";
printf("  X = %.9f\n", $pv[0] / $AU);
printf("  Y = %.9f\n", $pv[1] / $AU);
printf("  Z = %.9f\n", $pv[2] / $AU);

echo "\nswetest reference:\n";
echo "  X = -0.390715271\n";
echo "  Y = -0.152384310\n";
echo "  Z = -0.044691034\n";
