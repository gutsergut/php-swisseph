<?php
require 'vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path('C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/data/ephemerides/jpl');
swe_set_jpl_file('de200.eph');

echo "=== Mercury at J2000.0 ===\n";

// Test with JPL
$xx = [];
$serr = '';
$ret = swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_JPLEPH, $xx, $serr);
printf("JPL:    lon=%.6f, lat=%.6f, dist=%.6f\n", $xx[0], $xx[1], $xx[2]);

// Test with Swiss Ephemeris (for comparison)
$swiPath = 'C:/Users/serge/OneDrive/Documents/Fractal/Projects/Component/Swisseph/eph/ephe';
echo "SWIEPH path: $swiPath\n";
swe_set_ephe_path($swiPath);
$xxSwe = [];
$serrSwe = '';
$ret = swe_calc(2451545.0, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH, $xxSwe, $serrSwe);
if ($ret < 0) {
    echo "SWIEPH error: $serrSwe\n";
}
printf("SWIEPH: lon=%.6f, lat=%.6f, dist=%.6f\n", $xxSwe[0], $xxSwe[1], $xxSwe[2]);

// Difference
printf("Diff:   dlon=%.3f\", dlat=%.3f\", ddist=%.0fkm\n",
    ($xx[0] - $xxSwe[0]) * 3600,
    ($xx[1] - $xxSwe[1]) * 3600,
    ($xx[2] - $xxSwe[2]) * 149597870.7);

echo "\nReference from swetest (DE200):\n";
echo "swetest: lon=271.889277°, lat=-0.994°, dist=1.415469 AU\n";
