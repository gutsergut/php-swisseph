<?php
/**
 * Compare Mercury coefficients between early and late records
 */

declare(strict_types=1);

$ephFile = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/de200.eph';

$fp = fopen($ephFile, 'rb');

// Constants from DE200
$irecsz = 6608;
$ncoeffs = 826;
$bufStart = 2;  // ipt[0] - 1 = 3 - 1 = 2
$ncf = 12;      // Mercury ncf

function readRecord($fp, $nr, $irecsz, $ncoeffs, $bufStart, $ncf) {
    fseek($fp, $nr * $irecsz);
    $buf = [];
    for ($k = 0; $k < min($ncoeffs, 50); $k++) {
        $data = fread($fp, 8);
        $buf[$k] = unpack('d', $data)[1];
    }
    return $buf;
}

echo "=== Record 4 (JD 2305488.5 - 2305520.5, early) ===\n";
$buf4 = readRecord($fp, 4, $irecsz, $ncoeffs, $bufStart, $ncf);
echo "buf[0] = {$buf4[0]} (segment start)\n";
echo "buf[1] = {$buf4[1]} (segment end)\n";
echo "Mercury X coefficients (sub-interval 0):\n";
for ($j = 0; $j < $ncf; $j++) {
    printf("  buf[%2d] = %.15e\n", $bufStart + $j, $buf4[$bufStart + $j]);
}

echo "\n=== Record 4568 (JD 2451536.5 - 2451568.5, J2000) ===\n";
$buf4568 = readRecord($fp, 4568, $irecsz, $ncoeffs, $bufStart, $ncf);
echo "buf[0] = {$buf4568[0]} (segment start)\n";
echo "buf[1] = {$buf4568[1]} (segment end)\n";
echo "Mercury X coefficients (sub-interval 0):\n";
for ($j = 0; $j < $ncf; $j++) {
    printf("  buf[%2d] = %.15e\n", $bufStart + $j, $buf4568[$bufStart + $j]);
}

fclose($fp);

// Check magnitude of coefficients
echo "\n=== Coefficient magnitude comparison ===\n";
for ($j = 0; $j < $ncf; $j++) {
    $c4 = $buf4[$bufStart + $j];
    $c4568 = $buf4568[$bufStart + $j];
    $ratio = $c4568 != 0 ? abs($c4 / $c4568) : 0;
    echo "  j=$j: early=" . sprintf("%.3e", $c4) . ", late=" . sprintf("%.3e", $c4568) . ", ratio=" . sprintf("%.3f", $ratio) . "\n";
}
