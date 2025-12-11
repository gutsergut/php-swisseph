<?php

require_once __DIR__ . '/bootstrap.php';

use Swisseph\Constants;

/**
 * Basic smoke test for swe_nod_aps functions
 * Tests mean nodes and apsides for Earth's Moon and planets
 */

echo "=== swe_nod_aps Smoke Test ===\n\n";

// Test date: J2000.0
$jd_tt = 2451545.0;
echo "Test date: JD {$jd_tt} (J2000.0)\n\n";

// Test Moon mean nodes and apogee
echo "Moon Mean Nodes and Apogee:\n";
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;
$ret = swe_nod_aps(
    $jd_tt,
    Constants::SE_MOON,
    Constants::SEFLG_SPEED,
    Constants::SE_NODBIT_MEAN,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("  Ascending Node:  %10.6f° (speed: %8.6f°/day)\n", $xnasc[0], $xnasc[3]);
    printf("  Descending Node: %10.6f° (speed: %8.6f°/day)\n", $xndsc[0], $xndsc[3]);
    printf("  Perigee:         %10.6f° (speed: %8.6f°/day)\n", $xperi[0], $xperi[3]);
    printf("  Apogee:          %10.6f° (speed: %8.6f°/day)\n", $xaphe[0], $xaphe[3]);
}
echo "\n";

// Test Earth mean nodes and perihelion
echo "Earth Mean Nodes and Perihelion:\n";
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;
$ret = swe_nod_aps(
    $jd_tt,
    Constants::SE_EARTH,
    Constants::SEFLG_SPEED,
    Constants::SE_NODBIT_MEAN,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("  Ascending Node:  %10.6f° (speed: %8.6f°/day)\n", $xnasc[0], $xnasc[3]);
    printf("  Descending Node: %10.6f° (speed: %8.6f°/day)\n", $xndsc[0], $xndsc[3]);
    printf("  Perihelion:      %10.6f° (speed: %8.6f°/day)\n", $xperi[0], $xperi[3]);
    printf("  Aphelion:        %10.6f° (speed: %8.6f°/day)\n", $xaphe[0], $xaphe[3]);
}
echo "\n";

// Test Mars mean nodes and perihelion
echo "Mars Mean Nodes and Perihelion:\n";
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;
$ret = swe_nod_aps(
    $jd_tt,
    Constants::SE_MARS,
    Constants::SEFLG_SPEED,
    Constants::SE_NODBIT_MEAN,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("  Ascending Node:  %10.6f° (speed: %8.6f°/day)\n", $xnasc[0], $xnasc[3]);
    printf("  Descending Node: %10.6f° (speed: %8.6f°/day)\n", $xndsc[0], $xndsc[3]);
    printf("  Perihelion:      %10.6f° (speed: %8.6f°/day)\n", $xperi[0], $xperi[3]);
    printf("  Aphelion:        %10.6f° (speed: %8.6f°/day)\n", $xaphe[0], $xaphe[3]);
}
echo "\n";

// Test Jupiter mean nodes and perihelion
echo "Jupiter Mean Nodes and Perihelion:\n";
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;
$ret = swe_nod_aps(
    $jd_tt,
    Constants::SE_JUPITER,
    Constants::SEFLG_SPEED,
    Constants::SE_NODBIT_MEAN,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("  Ascending Node:  %10.6f° (speed: %8.6f°/day)\n", $xnasc[0], $xnasc[3]);
    printf("  Descending Node: %10.6f° (speed: %8.6f°/day)\n", $xndsc[0], $xndsc[3]);
    printf("  Perihelion:      %10.6f° (speed: %8.6f°/day)\n", $xperi[0], $xperi[3]);
    printf("  Aphelion:        %10.6f° (speed: %8.6f°/day)\n", $xaphe[0], $xaphe[3]);
}
echo "\n";

// Test with UT version
echo "Test swe_nod_aps_ut (UT -> ET conversion):\n";
$jd_ut = 2451544.5;  // 2000-01-01 00:00 UT
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;
$ret = swe_nod_aps_ut(
    $jd_ut,
    Constants::SE_EARTH,
    0,  // no speed
    Constants::SE_NODBIT_MEAN,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("  Earth Perihelion at JD %.1f UT: %10.6f°\n", $jd_ut, $xperi[0]);
}
echo "\n";

// Test focal point option
echo "Test SE_NODBIT_FOPOINT (focal point instead of aphelion):\n";
$xnasc = [];
$xndsc = [];
$xperi = [];
$xaphe = [];
$serr = null;
$ret = swe_nod_aps(
    $jd_tt,
    Constants::SE_EARTH,
    0,
    Constants::SE_NODBIT_MEAN | Constants::SE_NODBIT_FOPOINT,
    $xnasc,
    $xndsc,
    $xperi,
    $xaphe,
    $serr
);

if ($ret < 0) {
    echo "ERROR: $serr\n";
} else {
    printf("  Perihelion dist: %.6f AU\n", $xperi[2]);
    printf("  Focal point dist: %.6f AU (should be 2 * eccentricity * semi-major axis)\n", $xaphe[2]);
}
echo "\n";

echo "=== Test completed ===\n";
