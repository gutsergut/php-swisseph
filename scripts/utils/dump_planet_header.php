<?php
$f = __DIR__ . '/../../eph/ephe/seplm18.se1';
$fp = fopen($f, 'rb');
if (!$fp) {
    echo "Cannot open file\n";
    exit(1);
}

echo 'Line 1: ' . trim(fgets($fp)) . PHP_EOL;
echo 'Line 2: ' . trim(fgets($fp)) . PHP_EOL;
echo 'Line 3: ' . trim(fgets($fp)) . PHP_EOL;

$pos = ftell($fp);
echo "Position after text lines: $pos\n\n";

$data = fread($fp, 100);
echo "First 100 bytes after text:\n";
for ($i = 0; $i < 100; $i++) {
    echo sprintf('%02d: 0x%02X (%3d) %s', $i, ord($data[$i]), ord($data[$i]),
        ctype_print($data[$i]) ? $data[$i] : '.');
    if (($i + 1) % 4 == 0) echo PHP_EOL;
    else echo '  ';
}

fclose($fp);
