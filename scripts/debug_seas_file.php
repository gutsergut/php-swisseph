<?php

/**
 * Debug script to inspect seas_*.se1 file structure
 */

require_once __DIR__ . '/../vendor/autoload.php';

$ephePath = __DIR__ . '/../../eph/ephe';
$fname = 'seas_18.se1'; // JD 2460000.5 falls in century 18-23 (1800-2399)

$fullpath = $ephePath . DIRECTORY_SEPARATOR . $fname;

if (!file_exists($fullpath)) {
    echo "File not found: $fullpath\n";
    exit(1);
}

echo "Inspecting: $fullpath\n";
echo str_repeat("=", 60) . "\n\n";

$fp = fopen($fullpath, 'rb');

// Read version line
$line1 = fgets($fp);
echo "Line 1 (version): " . trim($line1) . "\n";

// Read filename line
$line2 = fgets($fp);
echo "Line 2 (filename): " . trim($line2) . "\n";

// Read copyright line
$line3 = fgets($fp);
echo "Line 3 (copyright): " . trim($line3) . "\n";

// Read test endian (4 bytes)
$testendian = unpack('V', fread($fp, 4))[1];
echo sprintf("Test endian: 0x%08X (expected 0x00616263)\n", $testendian);

// Read file length
$flen_stored = unpack('V', fread($fp, 4))[1];
echo "Stored file length: $flen_stored\n";

// Read DE number
$denum = unpack('V', fread($fp, 4))[1];
echo "DE number: $denum\n";

// Read start/end epochs
$tfstart = unpack('d', fread($fp, 8))[1];
$tfend = unpack('d', fread($fp, 8))[1];
echo sprintf("Epoch range: %.1f - %.1f\n", $tfstart, $tfend);

// Read number of planets
$nplan_raw = unpack('v', fread($fp, 2))[1]; // 2 bytes
$nbytes_ipl = 2;
if ($nplan_raw > 256) {
    $nbytes_ipl = 4;
    $nplan = $nplan_raw % 256;
} else {
    $nplan = $nplan_raw;
}
echo "Number of planets in file: $nplan (raw=$nplan_raw, bytes_per_ipl=$nbytes_ipl)\n\n";

// Read planet numbers
echo "Planet indices in file:\n";
$ipl = [];
for ($i = 0; $i < $nplan; $i++) {
    if ($nbytes_ipl == 2) {
        $ipl[$i] = unpack('v', fread($fp, 2))[1];
    } else {
        $ipl[$i] = unpack('V', fread($fp, 4))[1];
    }

    // Map to planet name (using C sweph.h SEI_* constants)
    $name = match($ipl[$i]) {
        10 => 'SEI_SUNBARY',
        11 => 'SEI_ANYBODY',
        12 => 'SEI_CHIRON',
        13 => 'SEI_PHOLUS',
        14 => 'SEI_CERES',
        15 => 'SEI_PALLAS',
        16 => 'SEI_JUNO',
        17 => 'SEI_VESTA',
        default => 'UNKNOWN (' . $ipl[$i] . ')',
    };

    echo "  [$i] ipli = {$ipl[$i]} ($name)\n";
}

// Skip CRC (4 bytes)
fread($fp, 4);

// Skip general constants (5 x 8 bytes)
fread($fp, 40);

echo "\nPlanet constants from file header:\n";
echo str_repeat("-", 80) . "\n";

for ($i = 0; $i < $nplan; $i++) {
    $lndx0 = unpack('V', fread($fp, 4))[1]; // file position of index
    $iflg = ord(fread($fp, 1));              // flags
    $ncoe = ord(fread($fp, 1));              // number of coefficients
    $rmax_lng = unpack('V', fread($fp, 4))[1]; // rmax * 1000
    $rmax = $rmax_lng / 1000.0;

    // Read 10 doubles
    $tfstart_p = unpack('d', fread($fp, 8))[1];
    $tfend_p = unpack('d', fread($fp, 8))[1];
    $dseg = unpack('d', fread($fp, 8))[1];
    fread($fp, 56); // skip remaining 7 doubles

    $name = match($ipl[$i]) {
        12 => 'CHIRON',
        13 => 'PHOLUS',
        14 => 'CERES',
        15 => 'PALLAS',
        16 => 'JUNO',
        17 => 'VESTA',
        default => 'PLANET_' . $ipl[$i],
    };

    echo sprintf("  %s (ipli=%d): lndx0=%d, iflg=0x%02X, ncoe=%d, rmax=%.3f, dseg=%.1f\n",
        $name, $ipl[$i], $lndx0, $iflg, $ncoe, $rmax, $dseg);
}

fclose($fp);

echo "\nDone.\n";
