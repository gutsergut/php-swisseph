<?php

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;

$jd_ut = 2460677.0;
$serr = '';

// Get Mercury via swe_calc_ut (this uses the full pipeline)
$xx = [];
swe_calc_ut($jd_ut, Constants::SE_MERCURY, Constants::SEFLG_SPEED, $xx, $serr);

echo "Final result from swe_calc_ut:\n";
echo "  lon: {$xx[0]}°, lat: {$xx[1]}°, dist: {$xx[2]} AU\n";
echo "  lon_speed: {$xx[3]}°/day, lat_speed: {$xx[4]}°/day, dist_speed: {$xx[5]} AU/day\n\n";

// Get cached planet data
$swed = SwedState::getInstance();
$mercury_pd = $swed->pldat[SwephConstants::SEI_MERCURY] ?? null;

if ($mercury_pd) {
    echo "Cached Mercury pldat->x (barycentric J2000):\n";
    echo "  pos: [{$mercury_pd->x[0]}, {$mercury_pd->x[1]}, {$mercury_pd->x[2]}]\n";
    echo "  vel: [{$mercury_pd->x[3]}, {$mercury_pd->x[4]}, {$mercury_pd->x[5]}]\n\n";

    echo "Cached Mercury xreturn[0..5] (ecliptic spherical):\n";
    printf("  lon: %.10f°, lat: %.10f°, dist: %.10f AU\n",
        $mercury_pd->xreturn[0], $mercury_pd->xreturn[1], $mercury_pd->xreturn[2]);
    printf("  lon_speed: %.10f°/day, lat_speed: %.10f°/day, dist_speed: %.10f AU/day\n\n",
        $mercury_pd->xreturn[3], $mercury_pd->xreturn[4], $mercury_pd->xreturn[5]);
}

// Compare with C reference
$c_speed = 1.2990637828; // from C swetest
$php_speed = $xx[3];
$error_deg = $php_speed - $c_speed;
$error_arcsec = $error_deg * 3600;

echo "Speed comparison:\n";
echo "  C reference:  $c_speed °/day\n";
echo "  PHP result:   $php_speed °/day\n";
echo "  Error:        $error_arcsec arcsec/day\n";
