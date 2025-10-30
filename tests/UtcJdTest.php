<?php

require __DIR__ . '/../src/Julian.php';
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/DeltaT.php';
require __DIR__ . '/../src/Utc.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Julian;
use Swisseph\Utc;

// Round-trip J2000 noon
[$jd_ut, $jd_tt] = Utc::utcToJd(2000, 1, 1, 12, 0, 0.0, 1);
list($y, $m, $d, $H, $M, $S) = Utc::jdToUtc($jd_ut, 1);
if (!($y === 2000 && $m === 1 && $d === 1 && $H === 12 && $M === 0 && abs($S) < 1e-6)) {
    fwrite(STDERR, "UTC round-trip failed: $y-$m-$d $H:$M:$S\n");
    exit(1);
}

// Wrapper swe_utc_to_jd should return [TT, UT] in this order
$res = swe_utc_to_jd(2000, 1, 1, 12, 0, 30.0, 1);
if (count($res) !== 2) {
    fwrite(STDERR, "swe_utc_to_jd shape failed\n");
    exit(2);
}
if (!is_float($res[0]) || !is_float($res[1])) {
    fwrite(STDERR, "swe_utc_to_jd types failed\n");
    exit(3);
}

// And back
$utc = swe_jd_to_utc($res[1], 1);
if ($utc[0] !== 2000 || $utc[1] !== 1 || $utc[2] !== 1) {
    fwrite(STDERR, "swe_jd_to_utc date failed\n");
    exit(4);
}

echo "OK\n";
