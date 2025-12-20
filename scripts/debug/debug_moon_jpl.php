<?php
/**
 * Debug Moon with JPL ephemeris
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$jplPath = __DIR__ . '/../../eph/data/ephemerides/jpl';
swe_set_ephe_path($jplPath);
swe_set_jpl_file('de441.eph');

$jd = 2451545.0;
$xx = [];
$iflag = Constants::SEFLG_JPLEPH | Constants::SEFLG_SPEED;

echo "Testing Moon with JPL...\n";
echo "JD: {$jd}\n";
echo "iflag: " . sprintf("0x%08X", $iflag) . "\n\n";

try {
    $ret = swe_calc($jd, Constants::SE_MOON, $iflag, $xx, $serr);
    echo "Return: {$ret}\n";
    echo "Serr: {$serr}\n";
    echo "xx: ";
    print_r($xx);
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
