<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// Test swe_calc for Moon at specific time
$tjd = 2460409.2630702;  // Initial tjd from eclipse calculation
$geopos = [-96.8, 32.8, 0.0];

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

echo "Testing swe_calc for Moon at tjd = $tjd\n";
echo "Location: lon={$geopos[0]}, lat={$geopos[1]}, alt={$geopos[2]}\n\n";

// Set topocentric
swe_set_topo($geopos[0], $geopos[1], $geopos[2]);

$iflag = Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR;
$iflagcart = $iflag | Constants::SEFLG_XYZ;

$serr = null;
$xm = array_fill(0, 6, 0.0);
$lm = array_fill(0, 6, 0.0);

// Calculate Moon position
$ret = swe_calc($tjd, Constants::SE_MOON, $iflagcart, $xm, $serr);
echo "swe_calc with SEFLG_XYZ returned: $ret\n";
if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit;
}

echo "xm (cartesian) = [" . implode(", ", array_map(fn($x) => sprintf("%.9f", $x), $xm)) . "]\n";

$ret = swe_calc($tjd, Constants::SE_MOON, $iflag, $lm, $serr);
echo "swe_calc without XYZ returned: $ret\n";
if ($ret < 0) {
    echo "ERROR: $serr\n";
    exit;
}

echo "lm (equatorial) = [" . implode(", ", array_map(fn($x) => sprintf("%.9f", $x), $lm)) . "]\n\n";

// Calculate distance and normalized vector
$dm = sqrt($xm[0] * $xm[0] + $xm[1] * $xm[1] + $xm[2] * $xm[2]);
echo "dm = $dm\n";

$x2 = [
    $xm[0] / $dm,
    $xm[1] / $dm,
    $xm[2] / $dm
];
echo "x2 (normalized) = [" . implode(", ", array_map(fn($x) => sprintf("%.9f", $x), $x2)) . "]\n";
