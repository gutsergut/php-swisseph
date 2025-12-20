<?php
/**
 * Debug: Read raw data from Swiss Ephemeris file and compare with swetest
 *
 * Goal: Find at which stage the error is introduced
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwephReader;
use Swisseph\Swe\State;
use Swisseph\Swe\SwedState;

$ephePath = __DIR__ . '/../../eph/ephe';
$absPath = realpath($ephePath);
swe_set_ephe_path($ephePath);

$swetest = 'C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe';

echo "=== Raw Ephemeris Data Comparison ===\n\n";

// Test at J2000.0 where we should have perfect accuracy
$jd = 2451545.0;
echo "Testing at J2000.0 (JD $jd)\n\n";

// Get swetest with XYZ output - raw rectangular coordinates
echo "--- XYZ coordinates (J2000, TRUEPOS, NONUT) ---\n";

$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_XYZ;
$xx = [];
$serr = '';
$ret = swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

echo "PHP Moon XYZ: X={$xx[0]}, Y={$xx[1]}, Z={$xx[2]}\n";

// Get swetest XYZ
$cmd = sprintf('cmd /c ""%s" -b1.1.2000 -ut12:00:00 -p1 -fPXYZ -head -eswe -true -j2000 -nonut -edir%s"',
    $swetest, $absPath);
exec($cmd, $output);

echo "swetest output: \n";
foreach ($output as $line) {
    echo "  $line\n";
}

// Also try equatorial
echo "\n--- Equatorial coordinates (J2000, TRUEPOS, NONUT) ---\n";

$flags2 = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_EQUATORIAL;
$xx2 = [];
$ret = swe_calc($jd, Constants::SE_MOON, $flags2, $xx2, $serr);

// Convert RA to hms for comparison
$ra_h = $xx2[0] / 15.0;
$ra_hh = (int)$ra_h;
$ra_mm = (int)(($ra_h - $ra_hh) * 60);
$ra_ss = (($ra_h - $ra_hh) * 60 - $ra_mm) * 60;

$dec = $xx2[1];
$dec_sign = $dec >= 0 ? '+' : '-';
$dec = abs($dec);
$dec_dd = (int)$dec;
$dec_mm = (int)(($dec - $dec_dd) * 60);
$dec_ss = (($dec - $dec_dd) * 60 - $dec_mm) * 60;

echo sprintf("PHP Moon EQ: RA=%dh%02dm%.4fs, Dec=%s%d°%02d'%.4f\"\n",
    $ra_hh, $ra_mm, $ra_ss, $dec_sign, $dec_dd, $dec_mm, $dec_ss);
echo "PHP Moon EQ raw: RA={$xx2[0]}°, Dec={$xx2[1]}°\n";

$cmd2 = sprintf('cmd /c ""%s" -b1.1.2000 -ut12:00:00 -p1 -fPAD -head -eswe -true -j2000 -nonut -edir%s"',
    $swetest, $absPath);
$output2 = [];
exec($cmd2, $output2);

echo "swetest output:\n";
foreach ($output2 as $line) {
    echo "  $line\n";
}

// Parse swetest RA
foreach ($output2 as $line) {
    if (preg_match('/(\d+)h\s*(\d+)\'([\d.]+)/', $line, $m)) {
        $swe_ra = $m[1] * 15 + $m[2] / 4 + $m[3] / 240;
        echo "swetest RA = {$swe_ra}°\n";
        echo "Difference: " . (($xx2[0] - $swe_ra) * 3600) . " arcsec\n";
    }
}

// Test Sun too
echo "\n--- Sun XYZ ---\n";
$xxs = [];
swe_calc($jd, Constants::SE_SUN, $flags, $xxs, $serr);
echo "PHP Sun XYZ: X={$xxs[0]}, Y={$xxs[1]}, Z={$xxs[2]}\n";

$cmds = sprintf('cmd /c ""%s" -b1.1.2000 -ut12:00:00 -p0 -fPXYZ -head -eswe -true -j2000 -nonut -edir%s"',
    $swetest, $absPath);
exec($cmds, $outputs);
echo "swetest: " . implode(" ", $outputs) . "\n";
