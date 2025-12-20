<?php
/**
 * Check with TRUEPOS flag (geometric position, no light-time, no aberration)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';
$absEphePath = realpath($ephePath);

$jd2100 = 2488070.0;

echo "=== TRUEPOS Check (2100) ===\n\n";

// PHP TRUEPOS
$xx = [];
$serr = '';
$ret = swe_calc($jd2100, Constants::SE_MOON, Constants::SEFLG_TRUEPOS | Constants::SEFLG_SPEED, $xx, $serr);
echo "PHP Moon TRUEPOS: lon={$xx[0]}\n";

// swetest TRUEPOS
$cmd = sprintf('cmd /c ""%s" -b1.1.2100 -ut12:00:00 -p1 -fPl -head -eswe -true -edir%s"',
    $swetest, $absEphePath);
exec($cmd, $output);
echo "swetest output: " . implode(" ", $output) . "\n";

$ref = null;
foreach ($output as $line) {
    if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
        $ref = (float)$matches[1];
    }
}
if ($ref) {
    $diff = ($xx[0] - $ref) * 3600;
    echo "Difference: {$diff}\"\n";
}

// PHP TRUEPOS + J2000
echo "\n--- TRUEPOS + J2000 ---\n";
$xx2 = [];
$ret = swe_calc($jd2100, Constants::SE_MOON, Constants::SEFLG_TRUEPOS | Constants::SEFLG_J2000 | Constants::SEFLG_SPEED, $xx2, $serr);
echo "PHP Moon TRUEPOS+J2000: lon={$xx2[0]}\n";

$output2 = [];
$cmd2 = sprintf('cmd /c ""%s" -b1.1.2100 -ut12:00:00 -p1 -fPl -head -eswe -true -j2000 -edir%s"',
    $swetest, $absEphePath);
exec($cmd2, $output2);

$ref2 = null;
foreach ($output2 as $line) {
    if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
        $ref2 = (float)$matches[1];
    }
}
if ($ref2) {
    $diff2 = ($xx2[0] - $ref2) * 3600;
    echo "swetest TRUEPOS+J2000: lon={$ref2}\n";
    echo "Difference: {$diff2}\"\n";
}

// Also check equatorial coordinates
echo "\n--- EQUATORIAL + TRUEPOS + J2000 ---\n";
$xx3 = [];
$ret = swe_calc($jd2100, Constants::SE_MOON, Constants::SEFLG_TRUEPOS | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED, $xx3, $serr);
echo "PHP Moon EQ TRUEPOS J2000: RA={$xx3[0]}, Dec={$xx3[1]}\n";

$output3 = [];
$cmd3 = sprintf('cmd /c ""%s" -b1.1.2100 -ut12:00:00 -p1 -fPTAD -head -eswe -true -j2000 -edir%s"',
    $swetest, $absEphePath);
exec($cmd3, $output3);
echo "swetest output: " . implode(" ", $output3) . "\n";

// Check near date (should be accurate)
echo "\n=== TRUEPOS Check (2025 - should be accurate) ===\n";
$jd2025 = swe_julday(2025, 12, 20, 12, 1);

$xx4 = [];
swe_calc($jd2025, Constants::SE_MOON, Constants::SEFLG_TRUEPOS | Constants::SEFLG_SPEED, $xx4, $serr);
echo "PHP Moon TRUEPOS (2025): lon={$xx4[0]}\n";

$output4 = [];
$cmd4 = sprintf('cmd /c ""%s" -b20.12.2025 -ut12:00:00 -p1 -fPl -head -eswe -true -edir%s"',
    $swetest, $absEphePath);
exec($cmd4, $output4);

$ref4 = null;
foreach ($output4 as $line) {
    if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
        $ref4 = (float)$matches[1];
    }
}
if ($ref4) {
    $diff4 = ($xx4[0] - $ref4) * 3600;
    echo "swetest TRUEPOS (2025): lon={$ref4}\n";
    echo "Difference: {$diff4}\"\n";
}
