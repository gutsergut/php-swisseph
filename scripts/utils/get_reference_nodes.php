<?php

// Quick script to get reference osculating nodes from C library (if available)
// Or use direct calculation to verify expected values

$jd_ut = 2451545.0; // J2000.0

// Try using C extension if available
if (function_exists('swe_nod_aps')) {
    swe_set_ephe_path(__DIR__ . '/../eph/ephe');

    $xnasc = [];
    $xndsc = [];
    $xperi = [];
    $xaphe = [];
    $serr = '';

    $iflag = SEFLG_SWIEPH | SEFLG_SPEED;

    $retflag = swe_nod_aps(
        $jd_ut,
        SE_JUPITER,
        $iflag,
        SE_NODBIT_OSCU,
        $xnasc,
        $xndsc,
        $xperi,
        $xaphe,
        $serr
    );

    if ($retflag >= 0) {
        echo "Reference from C library (swe_nod_aps):\n";
        echo sprintf("  Ascending Node Longitude: %.6f°\n", $xnasc[0]);
        echo sprintf("  Ascending Node Latitude:  %.6f°\n", $xnasc[1]);
        echo sprintf("  Ascending Node Distance:  %.6f AU\n", $xnasc[2]);
    } else {
        echo "Error from C library: $serr\n";
    }
} else {
    echo "C extension not available. Install php-swisseph extension to get reference values.\n";
    echo "Expected values based on swetest/documentation:\n";
    echo "  For Jupiter osculating nodes at J2000.0:\n";
    echo "  The reference value 100.64° may need verification.\n";
    echo "\n";
    echo "Let's check with swetest manually:\n";
    echo "Run: swetest64.exe -pC -j2451545 -eswe -hel -fTlbr\n";
    echo "This gives heliocentric Earth (for barycentric Sun calculation)\n";
}
