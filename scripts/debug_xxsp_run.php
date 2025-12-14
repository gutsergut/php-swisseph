<?php
/**
 * Debug xxsp calculation
 */

define('DEBUG_XXSP', true);

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd_ut = swe_julday(2025, 1, 1, 12.0, Constants::SE_GREG_CAL);

$serr = '';
$xx = [];
swe_calc_ut($jd_ut, Constants::SE_MERCURY, Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_NOABERR, $xx, $serr);

echo "\nMercury NOABERR result:\n";
echo "  lon_speed: {$xx[3]} deg/day\n";
echo "  C reference: 1.2990739 deg/day\n";
echo "  Error: " . (($xx[3] - 1.2990739) * 3600) . " arcsec/day\n";
