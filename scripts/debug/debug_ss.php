<?php
$fp = fopen('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/de406e.eph', 'rb');

// Skip TTL (252) and CNAM (2400) = 2652
fseek($fp, 2652, SEEK_SET);

$data = fread($fp, 24);

echo "Raw bytes for SS (24 bytes = 3 doubles):\n";
for ($i = 0; $i < 24; $i++) {
    printf("%02x ", ord($data[$i]));
    if (($i + 1) % 8 == 0) echo "| ";
}
echo "\n\n";

echo "Interpreted as native double:\n";
$arr = unpack('d3', $data);
print_r($arr);

// Try reversing bytes for each double
echo "\nReversed bytes (big-endian to little-endian):\n";
for ($i = 0; $i < 3; $i++) {
    $chunk = substr($data, $i * 8, 8);
    $reversed = strrev($chunk);
    $val = unpack('d', $reversed)[1];
    echo "ss[$i] = $val\n";
}

fclose($fp);
