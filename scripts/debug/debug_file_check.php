<?php
/**
 * Debug: Check if Swiss Ephemeris files are being read correctly
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\FilenameGenerator;
use Swisseph\SwephFile\SwephReader;
use Swisseph\Swe\State;

$ephePath = __DIR__ . '/../../eph/ephe';
$absPath = realpath($ephePath);
swe_set_ephe_path($ephePath);

echo "=== Swiss Ephemeris File Check ===\n";
echo "Path: $absPath\n\n";

// Get expected filename for Moon at 2025
$jd2025 = swe_julday(2025, 12, 20, 12, 1);
echo "JD for 2025-12-20: $jd2025\n";

// Check what file should be used
$century = (int)floor(($jd2025 - 2415020.5) / 36525 / 100) * 6 + 18;
$moonFile = $absPath . "/semo_" . sprintf("%02d", $century) . ".se1";
echo "Expected Moon file: $moonFile\n";

// Check if file exists
if (file_exists($moonFile)) {
    echo "File EXISTS: " . filesize($moonFile) . " bytes\n";
} else {
    echo "File NOT FOUND\n";
}

// Let's trace what happens during swe_calc
echo "\n--- Tracing swe_calc ---\n";

// Enable error reporting
error_reporting(E_ALL);

// Try with verbose error
$xx = [];
$serr = '';
$ret = swe_calc($jd2025, Constants::SE_MOON, Constants::SEFLG_SPEED, $xx, $serr);

echo "Return code: $ret (binary: " . decbin($ret) . ")\n";
echo "serr: " . ($serr ?: "(empty)") . "\n";
echo "Moon: lon={$xx[0]}, lat={$xx[1]}, dist={$xx[2]}\n";

// Check if SEFLG_SWIEPH is set in return
if ($ret & Constants::SEFLG_SWIEPH) {
    echo "SEFLG_SWIEPH is set in return - Swiss Ephemeris files used\n";
} else {
    echo "SEFLG_SWIEPH is NOT set\n";
}

if ($ret & Constants::SEFLG_MOSEPH) {
    echo "SEFLG_MOSEPH is set in return - Moshier ephemeris used\n";
} else {
    echo "SEFLG_MOSEPH is NOT set\n";
}

// Compare with explicit Moshier
echo "\n--- Explicit Moshier test ---\n";
$xx_mos = [];
$serr_mos = '';
$ret_mos = swe_calc($jd2025, Constants::SE_MOON, Constants::SEFLG_SPEED | Constants::SEFLG_MOSEPH, $xx_mos, $serr_mos);

echo "Moshier Moon: lon={$xx_mos[0]}\n";
echo "Difference from default: " . (($xx[0] - $xx_mos[0]) * 3600) . " arcsec\n";

if (abs($xx[0] - $xx_mos[0]) < 0.0001) {
    echo "WARNING: Results are identical - Swiss Ephemeris files may not be loaded!\n";
}
