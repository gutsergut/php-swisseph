<?php
/**
 * Debug actual record content for early dates
 */

declare(strict_types=1);

$ephFile = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/de200.eph';

$fp = fopen($ephFile, 'rb');
if (!$fp) die("Cannot open file\n");

// Read header to get record size
fseek($fp, 2652); // TTL + CNAM
$ssRaw = fread($fp, 24);
$ss = unpack('d3', $ssRaw);
$ehSs = [$ss[1], $ss[2], $ss[3]];
echo "SS: [{$ehSs[0]}, {$ehSs[1]}, {$ehSs[2]}]\n";

// Skip to IPT
fseek($fp, 2652 + 24 + 4 + 8 + 8); // SS + NCON + AU + EMRAT
$data = fread($fp, 144);
$iptRaw = unpack('l36', $data);
$ipt = [];
for ($i = 0; $i < 36; $i++) {
    $ipt[$i] = $iptRaw[$i + 1];
}
echo "Mercury IPT: offset={$ipt[0]}, ncf={$ipt[1]}, na={$ipt[2]}\n";

// Calculate record size
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
echo "irecsz = $irecsz, ksize = $ksize\n\n";

// Read records 2, 3, 4 and check their start/end dates
for ($nr = 2; $nr <= 5; $nr++) {
    fseek($fp, $nr * $irecsz);
    $data = fread($fp, 16);
    $dates = unpack('d2', $data);
    echo "Record $nr: start={$dates[1]}, end={$dates[2]}\n";

    // Expected dates
    $expStart = ($nr - 2) * $ehSs[2] + $ehSs[0];
    $expEnd = $expStart + $ehSs[2];
    echo "  Expected: start=$expStart, end=$expEnd\n";

    if (abs($dates[1] - $expStart) > 0.1 || abs($dates[2] - $expEnd) > 0.1) {
        echo "  *** MISMATCH! ***\n";
    }
}

fclose($fp);
