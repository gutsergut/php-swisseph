<?php
$fp = fopen('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl/de406e.eph', 'rb');
fseek($fp, 2696, SEEK_SET);
$data = fread($fp, 12);

echo "Raw bytes for first 3 IPT values (12 bytes):\n";
for ($i = 0; $i < 12; $i++) {
    printf("%02x ", ord($data[$i]));
}
echo "\n\n";

echo "Interpreted as little-endian int32:\n";
$arr = unpack('V3', $data);  // V = unsigned 32-bit little-endian
print_r($arr);

echo "Interpreted as big-endian int32:\n";
$arr = unpack('N3', $data);  // N = unsigned 32-bit big-endian
print_r($arr);

// Expected: Mercury offset should be 3, ncf should be 14, na should be 4
// (typical values for JPL ephemerides)

fclose($fp);
