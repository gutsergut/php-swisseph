<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\SwephFile\SwephCalculator;
use Swisseph\SwephFile\SwephConstants;

// Set ephemeris path (use forward slashes on Windows for PHP)
\swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe');

// Test Jupiter position at J2000.0
$jd = 2451545.0; // J2000.0
$ipli = SwephConstants::SEI_JUPITER;
$ifno = SwephConstants::SEI_FILE_PLANET;
$iflag = 0;
$xpret = [];
$serr = null;

echo "Testing SwephCalculator for Jupiter at J2000.0\n\n";

$retc = SwephCalculator::calculate(
    $jd,
    $ipli,
    $ifno,
    $iflag,
    null,   // xsunb
    false,  // doSave
    $xpret,
    $serr
);

if ($retc < 0) {
    echo "ERROR: $serr\n";
    exit(1);
}

echo "Success!\n";
echo "Barycentric J2000 ecliptic cartesian coordinates:\n";
echo sprintf("  x = %.10f AU\n", $xpret[0]);
echo sprintf("  y = %.10f AU\n", $xpret[1]);
echo sprintf("  z = %.10f AU\n", $xpret[2]);
echo sprintf("  dx = %.10f AU/day\n", $xpret[3] ?? 0);
echo sprintf("  dy = %.10f AU/day\n", $xpret[4] ?? 0);
echo sprintf("  dz = %.10f AU/day\n", $xpret[5] ?? 0);

// Convert to spherical
$r = sqrt($xpret[0]**2 + $xpret[1]**2 + $xpret[2]**2);
$lon = atan2($xpret[1], $xpret[0]);
if ($lon < 0) $lon += 2 * M_PI;
$lat = atan2($xpret[2], sqrt($xpret[0]**2 + $xpret[1]**2));

echo "\nSpherical (barycentric ecliptic):\n";
echo sprintf("  lon = %.10f° (%.6f rad)\n", rad2deg($lon), $lon);
echo sprintf("  lat = %.10f° (%.6f rad)\n", rad2deg($lat), $lat);
echo sprintf("  r = %.10f AU\n", $r);
