<?php
/**
 * Debug the Chebyshev interpolation step by step
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Test at middle of first segment
$jd = -254863.5;  // ss[0] + 32

$ss = [-254895.5, 3696976.5, 64.0];
$ipt_mercury = [3, 14, 4];  // offset, ncf, na

// Mercury X coefficients from buffer (14 coefficients)
$mercury_x_coeffs = [
    47002704.2777906284,
    -2409487.5853005443,
    -5495283.7688090429,
    -280597.0200197978,
    45531.3079939998,
    9575.2898428988,
    652.9803174690,
    -81.9736927485,
    -26.4648724998,
    -2.5586430970,
    0.1722171651,
    0.0892973923,
    0.0111268360,
    -0.0002305484,
];

echo "=== Testing Chebyshev interpolation at JD $jd ===\n\n";

// Step 1: Calculate t (normalized time in segment 0..1)
// From state() code:
$s = $jd - 0.5;
$etMn = floor($s);
$etFr = $s - $etMn;
$etMn += 0.5;
echo "etMn = $etMn, etFr = $etFr\n";

// Calculate record number
$nr = (int)(($etMn - $ss[0]) / $ss[2]) + 2;
echo "Record nr = $nr\n";

// Normalized time within segment
$t = ($etMn - (($nr - 2) * $ss[2] + $ss[0]) + $etFr) / $ss[2];
echo "t (normalized, 0..1) = $t\n\n";

// Step 2: interp calculations
// From interp():
$na = $ipt_mercury[2];  // 4 sub-intervals
$ncf = $ipt_mercury[1]; // 14 coefficients
$ncm = 3;  // 3 components (X, Y, Z)

echo "na (sub-intervals) = $na\n";
echo "ncf (coeffs per component) = $ncf\n";

// Calculate sub-interval
if ($t >= 0) {
    $dt1 = floor($t);
} else {
    $dt1 = -floor(-$t);
}
$temp = $na * $t;
$ni = (int)($temp - $dt1);

echo "dt1 = $dt1\n";
echo "temp = na * t = $temp\n";
echo "ni (sub-interval) = $ni\n";

// tc is normalized Chebyshev time (-1 <= tc <= 1)
$tc = (fmod($temp, 1.0) + $dt1) * 2.0 - 1.0;
echo "tc (Chebyshev time, -1..1) = $tc\n\n";

// Step 3: Chebyshev polynomials
$pc = [1.0, $tc];  // P_0 = 1, P_1 = tc
$twot = 2.0 * $tc;

echo "Chebyshev polynomials:\n";
echo "  pc[0] = {$pc[0]}\n";
echo "  pc[1] = {$pc[1]}\n";

for ($i = 2; $i < $ncf; $i++) {
    $pc[$i] = $twot * $pc[$i-1] - $pc[$i-2];
    echo "  pc[$i] = $pc[$i]\n";
}

// Step 4: Interpolate X position
// The tricky part: which coefficients to use?
// For sub-interval ni, component i:
//   buf[bufStart + j + (i + ni*ncm)*ncf]
//
// For Mercury (bufStart=2), X component (i=0), sub-interval ni:
//   buf[2 + j + (0 + ni*3)*14] = buf[2 + j + ni*42]

echo "\n=== Coefficient access pattern ===\n";
echo "bufStart = ipt[0] - 1 = 3 - 1 = 2\n";
echo "For X (i=0), sub-interval $ni:\n";
$bufStart = 2;
$component = 0;  // X
$coeff_start = $bufStart + ($component + $ni * $ncm) * $ncf;
echo "  Coefficients start at buf[$coeff_start]\n";

// If ni=2, we'd need buf[2 + 2*3*14] = buf[86], not buf[2]!
// But we only have first 14 coeffs in our test

if ($ni == 0) {
    echo "\n=== Interpolating X (sub-interval 0) ===\n";
    $x = 0.0;
    for ($j = $ncf - 1; $j >= 0; $j--) {
        $contrib = $pc[$j] * $mercury_x_coeffs[$j];
        $x += $contrib;
        // echo "  j=$j: pc[$j] * coeff[$j] = {$pc[$j]} * {$mercury_x_coeffs[$j]} = $contrib, sum=$x\n";
    }
    echo "X position (km) = $x\n";

    // Convert to AU
    $AU = 149597870.691;
    $x_au = $x / $AU;
    echo "X position (AU) = $x_au\n";
} else {
    echo "\nWARNING: ni=$ni, but we only have sub-interval 0 coefficients!\n";
    echo "Need to read coefficients for sub-interval $ni from buffer.\n";
}

// Let's compute what Mercury's X should be at middle of FIRST sub-interval (ni=0)
// First sub-interval: JD from -254895.5 to -254895.5 + 64/4 = -254879.5
// t=0.5 would be middle of segment, which is ni=2 (sub-interval 2)!

echo "\n=== Sub-interval analysis ===\n";
$segment_length = $ss[2];  // 64 days
$sub_interval_length = $segment_length / $na;  // 16 days
echo "Segment length = $segment_length days\n";
echo "Sub-interval length = $sub_interval_length days\n";
echo "Sub-interval boundaries:\n";
for ($i = 0; $i <= $na; $i++) {
    $boundary_jd = $ss[0] + $i * $sub_interval_length;
    echo "  ni=$i boundary at JD $boundary_jd\n";
}

// At JD -254863.5, we're 32 days into the segment
$days_into_segment = $jd - $ss[0];
echo "\nDays into segment: $days_into_segment\n";
$expected_ni = (int)($days_into_segment / $sub_interval_length);
echo "Expected ni: $expected_ni\n";
