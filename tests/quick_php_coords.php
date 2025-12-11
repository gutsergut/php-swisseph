<?php
require __DIR__ . '/../vendor/autoload.php';

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');
swe_set_topo(-96.8, 32.8, 0.0);
$iflag = \Swisseph\Constants::SEFLG_SWIEPH | \Swisseph\Constants::SEFLG_TOPOCTR | \Swisseph\Constants::SEFLG_EQUATORIAL | \Swisseph\Constants::SEFLG_SPEED;
$jd = swe_julday(2024, 4, 8, 6 + 18/60, \Swisseph\Constants::SE_GREG_CAL);
$xx = [];
$serr = null;
swe_calc_ut($jd, \Swisseph\Constants::SE_MOON, $iflag, $xx, $serr);
print_r([$jd, $xx, $serr]);
