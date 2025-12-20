<?php
/**
 * Check which ephemeris source is being used
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\Swe\SwedState;

$ephePath = __DIR__ . '/../../eph/ephe';
$absPath = realpath($ephePath);
swe_set_ephe_path($ephePath);

echo "=== Ephemeris Source Check ===\n";
echo "Ephemeris path: $absPath\n\n";

// List available files
echo "--- Available ephemeris files ---\n";
$files = glob($absPath . '/se*.se1');
echo "Found " . count($files) . " .se1 files\n";

// Check Moon files for 2100 coverage
echo "\n--- Moon files (semo_*) ---\n";
$moonFiles = glob($absPath . '/semo_*.se1');
foreach ($moonFiles as $f) {
    echo basename($f) . "\n";
}

// Test date 2100
$jd2100 = 2488070.0;
echo "\n--- Testing JD $jd2100 (1.1.2100) ---\n";

// Force Swiss Ephemeris
echo "\nTrying with SEFLG_SWIEPH:\n";
$xx = [];
$serr = '';
$ret = swe_calc($jd2100, Constants::SE_MOON, Constants::SEFLG_SPEED | Constants::SEFLG_SWIEPH, $xx, $serr);
echo "Return code: $ret\n";
echo "serr: " . ($serr ?: "(empty)") . "\n";
echo "Moon lon: {$xx[0]}\n";

// Check if Moshier is used
echo "\nTrying with SEFLG_MOSEPH:\n";
$xx2 = [];
$serr2 = '';
$ret2 = swe_calc($jd2100, Constants::SE_MOON, Constants::SEFLG_SPEED | Constants::SEFLG_MOSEPH, $xx2, $serr2);
echo "Return code: $ret2\n";
echo "serr: " . ($serr2 ?: "(empty)") . "\n";
echo "Moon lon: {$xx2[0]}\n";

echo "\nDifference SWIEPH vs MOSEPH: " . (($xx[0] - $xx2[0]) * 3600) . " arcsec\n";

// Compare with swetest Moshier
echo "\n--- swetest with Moshier (-emos) ---\n";
$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';

$cmd = sprintf('cmd /c ""%s" -b1.1.2100 -ut12:00:00 -p1 -fPl -head -emos"', $swetest);
exec($cmd, $output);
echo implode("\n", $output) . "\n";

$refMos = null;
foreach ($output as $line) {
    if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
        $refMos = (float)$matches[1];
    }
}
if ($refMos) {
    echo "PHP MOSEPH vs swetest -emos: " . (($xx2[0] - $refMos) * 3600) . " arcsec\n";
}
