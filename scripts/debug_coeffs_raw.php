<?php
/**
 * Debug: Compare raw coefficient reading with C program
 * Direct file access - no class usage
 */

$ephPath = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/de406e.eph';

$fp = fopen($ephPath, 'rb');
if (!$fp) {
    die("Cannot open file\n");
}

// Read header - first 3 records
// Record 1: TTL (3x84 bytes) + constant names (400*6 bytes) + SS (3 doubles) + NCON (int32) + AU + EMRAT
// Read TTL
$ttl = [];
for ($i = 0; $i < 3; $i++) {
    $ttl[] = trim(fread($fp, 84));
}
echo "TTL:\n";
foreach ($ttl as $t) echo "  $t\n";

// Read constant names (400 * 6 bytes)
$cnam = fread($fp, 2400);
echo "\nFirst few constant names:\n";
for ($i = 0; $i < 10; $i++) {
    echo "  " . trim(substr($cnam, $i * 6, 6)) . "\n";
}

// Read SS (start, stop, step) - 3 doubles
$ss = [];
for ($i = 0; $i < 3; $i++) {
    $data = fread($fp, 8);
    $ss[] = unpack('d', $data)[1];
}
echo "\nSS (start, stop, step):\n";
printf("  Start: %.1f\n", $ss[0]);
printf("  Stop: %.1f\n", $ss[1]);
printf("  Step: %.1f days\n", $ss[2]);

// Read NCON
$ncon = unpack('V', fread($fp, 4))[1]; // V = unsigned 32-bit little-endian
echo "\nNCON: $ncon\n";

// Read AU
$au = unpack('d', fread($fp, 8))[1];
printf("AU: %.10f km\n", $au);

// Read EMRAT
$emrat = unpack('d', fread($fp, 8))[1];
printf("EMRAT: %.10f\n", $emrat);

// Read IPT (13*3 = 39 elements) - each int32
$ipt = [];
for ($i = 0; $i < 13; $i++) {
    $ipt[$i] = [];
    for ($j = 0; $j < 3; $j++) {
        $ipt[$i][$j] = unpack('V', fread($fp, 4))[1];
    }
}

echo "\nIPT (interpolation pointers):\n";
$bodies = ['Mercury', 'Venus', 'EMB', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune', 'Pluto', 'Moon', 'Sun', 'Nutations', 'Librations'];
for ($i = 0; $i < 13; $i++) {
    printf("  %-10s: offset=%4d, ncf=%2d, na=%d\n", $bodies[$i], $ipt[$i][0], $ipt[$i][1], $ipt[$i][2]);
}

// Read DENUM
$denum = unpack('V', fread($fp, 4))[1];
echo "\nDE Number: $denum\n";

// Calculate ncoeffs
function calculateNcoeffs($ipt, $denum) {
    // Find maximum extent used
    $max = 0;
    for ($i = 0; $i < 13; $i++) {
        $extent = $ipt[$i][0] + $ipt[$i][1] * $ipt[$i][2] * 3;
        if ($i == 11) { // nutations - only 2 components
            $extent = $ipt[$i][0] + $ipt[$i][1] * $ipt[$i][2] * 2;
        }
        if ($extent > $max) $max = $extent;
    }
    return $max - 1;
}

$ncoeffs = calculateNcoeffs($ipt, $denum);
$irecsz = $ncoeffs * 8;
echo "Ncoeffs: $ncoeffs, Record size: $irecsz bytes\n";

// Test date: 2460016.5
$jd = 2460016.5;
echo "\n=== Test JD: $jd ===\n";

// Calculate record number
$s = $jd - 0.5;
$etMn = floor($s);
$etFr = $s - $etMn;
$etMn += 0.5;

$nr = (int)(($etMn - $ss[0]) / $ss[2]) + 2;
echo "etMn: $etMn, etFr: $etFr, Record: $nr\n";

// Calculate normalized time t
$t = ($etMn - (($nr - 2) * $ss[2] + $ss[0]) + $etFr) / $ss[2];
echo "Normalized time t: $t\n";

// File offset for this record
$offset = $nr * $irecsz;
echo "File offset: $offset bytes\n";

// Read the record
fseek($fp, $offset, SEEK_SET);

$buf = [];
for ($i = 0; $i < $ncoeffs; $i++) {
    $data = fread($fp, 8);
    $buf[$i] = unpack('d', $data)[1];
}

// Show first values from buffer
echo "\nFirst 10 buffer values:\n";
for ($i = 0; $i < 10; $i++) {
    printf("buf[%d] = %.15e\n", $i, $buf[$i]);
}

// Get EMB coefficients (index 2 = EMB in 0-based, index 3 in bodies array)
$embIdx = 2; // EMB is body index 2 (0=Mercury, 1=Venus, 2=EMB...)
$embOff = $ipt[$embIdx][0] - 1; // Convert to 0-based
$embNcf = $ipt[$embIdx][1];
$embNa = $ipt[$embIdx][2];

echo "\nEMB: offset=$embOff, ncf=$embNcf, na=$embNa\n";

// Show EMB coefficients
echo "\nEMB coefficients (first sub-interval, X component, first $embNcf values):\n";
for ($i = 0; $i < $embNcf; $i++) {
    printf("emb_x[%2d] = %.15e\n", $i, $buf[$embOff + $i]);
}

echo "\nEMB coefficients (first sub-interval, Y component):\n";
for ($i = 0; $i < $embNcf; $i++) {
    printf("emb_y[%2d] = %.15e\n", $i, $buf[$embOff + $embNcf + $i]);
}

echo "\nEMB coefficients (first sub-interval, Z component):\n";
for ($i = 0; $i < $embNcf; $i++) {
    printf("emb_z[%2d] = %.15e\n", $i, $buf[$embOff + 2 * $embNcf + $i]);
}

// Now do Chebyshev interpolation manually
$tc = 2 * $t - 1; // Chebyshev argument
echo "\nChebyshev argument tc = 2*t - 1 = $tc\n";

// Initialize P (position polynomial values)
$pc = [];
$pc[0] = 1.0;
$pc[1] = $tc;
for ($i = 2; $i < $embNcf; $i++) {
    $pc[$i] = 2 * $tc * $pc[$i-1] - $pc[$i-2];
}

// Compute position for X
$x = 0.0;
for ($i = 0; $i < $embNcf; $i++) {
    $x += $buf[$embOff + $i] * $pc[$i];
}

$y = 0.0;
for ($i = 0; $i < $embNcf; $i++) {
    $y += $buf[$embOff + $embNcf + $i] * $pc[$i];
}

$z = 0.0;
for ($i = 0; $i < $embNcf; $i++) {
    $z += $buf[$embOff + 2 * $embNcf + $i] * $pc[$i];
}

echo "\n=== EMB position from manual interpolation ===\n";
printf("X = %.15f AU\n", $x);
printf("Y = %.15f AU\n", $y);
printf("Z = %.15f AU\n", $z);

fclose($fp);
