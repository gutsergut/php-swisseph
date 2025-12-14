<?php
/**
 * Test planet speeds after aberration speed fix
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd_ut = swe_julday(2025, 1, 1, 12.0, Constants::SE_GREG_CAL);
echo "JD UT: $jd_ut\n\n";

$planets = [
    Constants::SE_MOON => 'Moon',
    Constants::SE_MERCURY => 'Mercury',
    Constants::SE_VENUS => 'Venus',
    Constants::SE_MARS => 'Mars',
    Constants::SE_JUPITER => 'Jupiter',
    Constants::SE_SATURN => 'Saturn',
];

// C reference values from swetest64
$c_ref = [
    Constants::SE_MOON => 13.5441982199,
    Constants::SE_MERCURY => 1.2265055028,
    Constants::SE_VENUS => 1.2531877315,
    Constants::SE_MARS => 0.6119697451,
    Constants::SE_JUPITER => 0.2413339429,
    Constants::SE_SATURN => 0.1166816571,
];

echo sprintf("%-10s %18s %18s %12s\n", "Planet", "C Reference", "PHP Speed", "Error (\"/day)");
echo str_repeat("-", 60) . "\n";

foreach ($planets as $ipla => $name) {
    $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
    $xx = [];
    $ret = swe_calc_ut($jd_ut, $ipla, $iflag, $xx, $serr);

    if (isset($c_ref[$ipla])) {
        $error_arcsec = ($xx[3] - $c_ref[$ipla]) * 3600;
        echo sprintf("%-10s %18.10f %18.10f %+12.4f\n", $name, $c_ref[$ipla], $xx[3], $error_arcsec);
    } else {
        echo sprintf("%-10s %18s %18.10f %12s\n", $name, "N/A", $xx[3], "N/A");
    }
}
