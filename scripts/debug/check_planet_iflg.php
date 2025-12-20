<?php
$fp = fopen(__DIR__ . '/../../eph/ephe/seplm18.se1', 'rb');
if (!$fp) {
    echo "File not found\n";
    exit(1);
}

// Skip header lines
fgets($fp);
fgets($fp);
fgets($fp);

// Skip test endian (4), file length (4), DE num (4), tfstart (8), tfend (8), nplan (2)
fread($fp, 30);

// Read planet indices
for ($i = 0; $i < 10; $i++) {
    $ipl = unpack('v', fread($fp, 2))[1];
    echo "Planet $i: ipli = $ipl\n";
}

// Skip CRC (4 bytes) and general constants (5 x 8 bytes = 40)
fread($fp, 44);

echo "\nPlanet constants:\n";

for ($i = 0; $i < 10; $i++) {
    $lndx0 = unpack('V', fread($fp, 4))[1];
    $iflg = ord(fread($fp, 1));
    $ncoe = ord(fread($fp, 1));
    $rmax_lng = unpack('V', fread($fp, 4))[1];

    // Skip 10 doubles (80 bytes)
    fread($fp, 80);

    echo "  Planet $i: lndx0=$lndx0, iflg=$iflg (0x" . dechex($iflg) . "), ncoe=$ncoe\n";
    echo "    iflg bits: HELIO=" . ($iflg & 1) . ", ROTATE=" . (($iflg & 2) >> 1) . ", ELLIPSE=" . (($iflg & 4) >> 2) . ", EMBHEL=" . (($iflg & 8) >> 3) . "\n";
}

fclose($fp);
