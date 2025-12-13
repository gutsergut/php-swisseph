<?php
/**
 * Тест восхода, захода и транзита
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

// Spring equinox 2000, equator - Sun should rise/set
$jd_start = swe_julday(2000, 3, 20, 0.0, Constants::SE_GREG_CAL);
$geolon = 0.0;
$geolat = 0.0;

// Just verify functions exist and don't crash
$tret = 0.0;
$serr = null;
$rsmi = Constants::SE_CALC_RISE;
$ret = swe_rise_trans($jd_start, Constants::SE_SUN, '', Constants::SEFLG_SWIEPH,
                      $rsmi, [$geolon, $geolat, 0.0], 1013.25, 15.0, $tret, $serr);

// Function should execute without fatal errors
if (!is_numeric($ret)) {
    fwrite(STDERR, "swe_rise_trans returned non-numeric\n");
    exit(1);
}

echo "OK\n";
