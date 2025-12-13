<?php
/**
 * Raw hex dump of seas_18.se1 header
 */

$f = __DIR__ . '/../../eph/ephe/seas_18.se1';
$fp = fopen($f, 'rb');

// Read everything including text header
$allData = fread($fp, 1000);
fclose($fp);

// Find end of text header (last \n before binary data)
$pos = 0;
$lines = 0;
while ($lines < 3) {
    if ($allData[$pos] === "\n") {
        $lines++;
    }
    $pos++;
}

echo "Text header ends at position: $pos\n";
echo "Text header:\n";
echo str_repeat("-", 60) . "\n";
echo substr($allData, 0, $pos);
echo str_repeat("-", 60) . "\n\n";

echo "Binary header (first 200 bytes after text):\n";
$binStart = $pos;
for ($i = 0; $i < 200; $i++) {
    $b = ord($allData[$binStart + $i]);
    echo sprintf("%03d: 0x%02X (%3d)", $i, $b, $b);
    if (($i + 1) % 4 == 0) echo "\n";
    else echo "  ";
}

// Parse key fields
echo "\n\nParsed fields:\n";
$offset = $binStart;

// Test endian (4 bytes)
$testEndian = unpack('V', substr($allData, $offset, 4))[1];
echo sprintf("Test endian: 0x%08X\n", $testEndian);
$offset += 4;

// File length (4 bytes)
$flen = unpack('V', substr($allData, $offset, 4))[1];
echo "File length: $flen\n";
$offset += 4;

// DE number (4 bytes)
$denum = unpack('V', substr($allData, $offset, 4))[1];
echo "DE number: $denum\n";
$offset += 4;

// tfstart (8 bytes)
$tfstart = unpack('d', substr($allData, $offset, 8))[1];
echo sprintf("tfstart: %.1f\n", $tfstart);
$offset += 8;

// tfend (8 bytes)
$tfend = unpack('d', substr($allData, $offset, 8))[1];
echo sprintf("tfend: %.1f\n", $tfend);
$offset += 8;

// nplan (2 bytes)
$nplan = unpack('v', substr($allData, $offset, 2))[1];
echo "nplan: $nplan\n";
$offset += 2;

// Planet indices
echo "Planet indices: ";
for ($i = 0; $i < $nplan; $i++) {
    $ipl = unpack('v', substr($allData, $offset, 2))[1];
    echo "$ipl ";
    $offset += 2;
}
echo "\n";

// CRC (4 bytes)
$crc = unpack('V', substr($allData, $offset, 4))[1];
echo sprintf("CRC: 0x%08X\n", $crc);
$offset += 4;

// General constants (5 x 8 bytes)
echo "General constants:\n";
for ($i = 0; $i < 5; $i++) {
    $val = unpack('d', substr($allData, $offset, 8))[1];
    echo sprintf("  [%d]: %.10e\n", $i, $val);
    $offset += 8;
}

// Planet data
echo "\nPlanet data:\n";
for ($i = 0; $i < $nplan; $i++) {
    $lndx0 = unpack('V', substr($allData, $offset, 4))[1];
    $offset += 4;

    $iflg = ord($allData[$offset]);
    $offset += 1;

    $ncoe = ord($allData[$offset]);
    $offset += 1;

    $rmax_lng = unpack('V', substr($allData, $offset, 4))[1];
    $offset += 4;

    // First double (tfstart)
    $tfstart_p = unpack('d', substr($allData, $offset, 8))[1];
    $offset += 8;

    // Skip remaining 9 doubles
    $offset += 72;

    echo sprintf("  Planet %d: lndx0=%d, iflg=%d (0x%02X), ncoe=%d, rmax=%.3f, tfstart=%.1f\n",
        $i, $lndx0, $iflg, $iflg, $ncoe, $rmax_lng / 1000.0, $tfstart_p);
    echo sprintf("    HELIO=%d ROTATE=%d ELLIPSE=%d EMBHEL=%d\n",
        $iflg & 1, ($iflg >> 1) & 1, ($iflg >> 2) & 1, ($iflg >> 3) & 1);
}
