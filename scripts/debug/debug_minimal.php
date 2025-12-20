<?php
/**
 * Minimal test - just J2000 TRUEPOS
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';
$absEphePath = realpath($ephePath);

echo "=== Minimal Test: J2000 + TRUEPOS ===\n\n";

// Test 2025-12-20
$jd = swe_julday(2025, 12, 20, 12, 1);
echo "JD: $jd (2025-12-20 12:00)\n\n";

// Most minimal flags: TRUEPOS + J2000 + NONUT = raw ephemeris without any transformations
$flags = Constants::SEFLG_TRUEPOS | Constants::SEFLG_J2000 | Constants::SEFLG_NONUT;
$xx = [];
$serr = '';
$ret = swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);
echo "PHP Moon (TRUEPOS+J2000+NONUT): lon={$xx[0]}, lat={$xx[1]}, dist={$xx[2]}\n";

$cmd = sprintf('cmd /c ""%s" -b20.12.2025 -ut12:00:00 -p1 -fPl -head -eswe -true -j2000 -nonut -edir%s"',
    $swetest, $absEphePath);
exec($cmd, $output);

$ref = null;
foreach ($output as $line) {
    if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
        $ref = (float)$matches[1];
    }
}
echo "swetest (TRUEPOS+J2000+NONUT): lon=$ref\n";
if ($ref) {
    $diff = ($xx[0] - $ref) * 3600;
    echo "Difference: {$diff}\"\n";
}

// Test also at J2000 date itself
echo "\n--- At J2000.0 date ---\n";
$jd2000 = 2451545.0;
$xx2 = [];
$ret = swe_calc($jd2000, Constants::SE_MOON, $flags, $xx2, $serr);
echo "PHP Moon at J2000: lon={$xx2[0]}\n";

$cmd2 = sprintf('cmd /c ""%s" -b1.1.2000 -ut12:00:00 -p1 -fPl -head -eswe -true -j2000 -nonut -edir%s"',
    $swetest, $absEphePath);
exec($cmd2, $output2);

$ref2 = null;
foreach ($output2 as $line) {
    if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
        $ref2 = (float)$matches[1];
    }
}
echo "swetest at J2000: lon=$ref2\n";
if ($ref2) {
    $diff2 = ($xx2[0] - $ref2) * 3600;
    echo "Difference: {$diff2}\"\n";
}

// Test Sun too
echo "\n--- Sun test ---\n";
$xxs = [];
$ret = swe_calc($jd, Constants::SE_SUN, $flags, $xxs, $serr);
echo "PHP Sun (TRUEPOS+J2000+NONUT): lon={$xxs[0]}\n";

$cmd3 = sprintf('cmd /c ""%s" -b20.12.2025 -ut12:00:00 -p0 -fPl -head -eswe -true -j2000 -nonut -edir%s"',
    $swetest, $absEphePath);
exec($cmd3, $output3);

$ref3 = null;
foreach ($output3 as $line) {
    if (preg_match('/^\s*\S+\s+([\d.]+)/', trim($line), $matches)) {
        $ref3 = (float)$matches[1];
    }
}
echo "swetest Sun: lon=$ref3\n";
if ($ref3) {
    $diff3 = ($xxs[0] - $ref3) * 3600;
    echo "Difference: {$diff3}\"\n";
}
