<?php

require __DIR__ . '/../src/Julian.php';
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/DeltaT.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Julian;
use Swisseph\Constants;
use Swisseph\DeltaT;

// J2000 day
$jd = Julian::toJulianDay(2000, 1, 1, 12.0, Constants::SE_GREG_CAL);
$dt1 = DeltaT::deltaTSecondsFromJd($jd);
$dt2 = swe_deltat($jd);
if (abs($dt1 - $dt2) > 1e-9) {
    fwrite(STDERR, "swe_deltat mismatch\n");
    exit(1);
}

// Rough sanity: around year 2000 Delta T ~ 64s
if (!($dt1 > 60 && $dt1 < 70)) {
    fwrite(STDERR, "deltaT ~2000 out of expected band: $dt1\n");
    exit(2);
}

// Older epoch: ~1900 about -2..5s per historical models (polynomial yields ~ -3..+2)
$jd1900 = Julian::toJulianDay(1900, 1, 1, 0.0, Constants::SE_GREG_CAL);
$dt1900 = swe_deltat($jd1900);
if (!($dt1900 > -10 && $dt1900 < 20)) {
    fwrite(STDERR, "deltaT 1900 out of broad band: $dt1900\n");
    exit(3);
}

echo "OK\n";
