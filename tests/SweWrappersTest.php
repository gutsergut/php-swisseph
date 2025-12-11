<?php

require __DIR__ . '/../src/Julian.php';
require __DIR__ . '/../src/functions.php';

$jd = swe_julday(2000, 1, 1, 12.0, 1);
if (abs($jd - 2451545.0) > 1e-6) {
    fwrite(STDERR, "swe_julday failed: $jd\n");
    exit(1);
}
$rev = swe_revjul($jd, 1);
if (!($rev['y'] === 2000 && $rev['m'] === 1 && $rev['d'] === 1 && abs($rev['ut'] - 12.0) <= 1e-6)) {
    fwrite(STDERR, "swe_revjul failed: ".json_encode($rev)."\n");
    exit(2);
}

echo "OK\n";
