<?php

require __DIR__ . '/../src/Math.php';

use Swisseph\Math;

function approx(float $a, float $b, float $e = 1e-12): bool
{
    return abs($a - $b) <= $e;
}

// deg<->rad
if (!approx(Math::degToRad(180.0), Math::PI)) {
    fwrite(STDERR, "degToRad failed\n");
    exit(1);
}
if (!approx(Math::radToDeg(Math::PI), 180.0)) {
    fwrite(STDERR, "radToDeg failed\n");
    exit(2);
}

// normalization deg
if (!approx(Math::normAngleDeg(-10.0), 350.0)) {
    fwrite(STDERR, "normAngleDeg -10 failed\n");
    exit(3);
}
if (!approx(Math::normAngleDeg(370.0), 10.0)) {
    fwrite(STDERR, "normAngleDeg 370 failed\n");
    exit(4);
}

// normalization rad
if (!approx(Math::normAngleRad(-0.5), Math::TWO_PI - 0.5)) {
    fwrite(STDERR, "normAngleRad failed\n");
    exit(5);
}

// wrap
$w = Math::wrap(15.0, -5.0, 5.0);
if (!approx($w, -5.0 + fmod(20.0, 10.0))) {
    fwrite(STDERR, "wrap failed: $w\n");
    exit(6);
}

echo "OK\n";
