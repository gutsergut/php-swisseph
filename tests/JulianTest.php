<?php

require __DIR__ . '/../src/Julian.php';

use Swisseph\Julian;

function approxEqual(float $a, float $b, float $eps = 1e-9): bool
{
    return abs($a - $b) <= $eps;
}

// Round-trip test for Gregorian date
$jd = Julian::toJulianDay(2000, 1, 1, 12.0, 1);
if (!approxEqual($jd, 2451545.0, 1e-6)) {
    fwrite(STDERR, "Failed JD for 2000-01-01 12:00 UT: $jd\n");
    exit(1);
}
$rev = Julian::fromJulianDay($jd, 1);
if (!($rev['y'] === 2000 && $rev['m'] === 1 && $rev['d'] === 1 && approxEqual($rev['ut'], 12.0, 1e-6))) {
    fwrite(STDERR, "Failed reverse for 2000-01-01: ".json_encode($rev)."\n");
    exit(2);
}

// Julian calendar test (e.g., 1582-10-04 is last Julian day before Gregorian switch)
$jdJul = Julian::toJulianDay(1582, 10, 4, 0.0, 0);
$revJul = Julian::fromJulianDay($jdJul, 0);
if (!($revJul['y'] === 1582 && $revJul['m'] === 10 && $revJul['d'] === 4)) {
    fwrite(STDERR, "Failed Julian calendar round-trip: ".json_encode($revJul)."\n");
    exit(3);
}

echo "OK\n";
