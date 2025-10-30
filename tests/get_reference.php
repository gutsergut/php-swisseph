<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\Constants;

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$jd = 2451545.0; // J2000.0
$iflag = Constants::SEFLG_SWIEPH |
         Constants::SEFLG_HELCTR |
         Constants::SEFLG_J2000 |
         Constants::SEFLG_TRUEPOS |
         Constants::SEFLG_NONUT;

echo "Heliocentric Polar Coordinates at J2000.0:\n\n";

foreach (['Saturn' => Constants::SE_SATURN, 'Jupiter' => Constants::SE_JUPITER] as $name => $id) {
    $xx = [];
    $serr = '';
    swe_calc($jd, $id, $iflag, $xx, $serr);

    printf("%-10s lon=%12.8f° lat=%12.8f° dist=%12.8f AU\n", $name . ':', $xx[0], $xx[1], $xx[2]);
}
