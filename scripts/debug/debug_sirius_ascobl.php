<?php

require_once __DIR__ . '/vendor/autoload.php';

// Set ephemeris path
\swe_set_ephe_path(__DIR__ . '/../eph/ephe');

$dgeo = [13.4, 52.5, 100.0]; // Amsterdam
\swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);

$datm = [0.0, 0.0, 0.0, 0.0];
$dobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
$ObjectName = 'Sirius';
$TypeEvent = 1; // morning first
$helflag = 0;

// Test at critical JDs from PHP debug output
$test_jds = [
    2451770.41803,  // Cosmic rising found by PHP
    2451784.64528,  // Expected heliacal rising (C)
    2451788.61211,  // Actual heliacal rising (PHP)
    2452024.96503,  // Actual heliacal setting (PHP)
    2452029.29441   // Expected heliacal setting (C)
];

echo "JD\t\t\tSun asc.obl\tSirius asc.obl\tDifference\n";
echo str_repeat("=", 72) . "\n";

foreach ($test_jds as $tjd_ut) {
    // Get Sun ascensio obliqua
    $serr = '';
    $xx = [];
    $retval = \swe_calc_ut($tjd_ut, 0, $helflag | 2048, $xx, $serr); // SE_SUN=0, SEFLG_EQUATORIAL=2048
    if ($retval < 0) {
        echo "Error calculating Sun: $serr\n";
        continue;
    }

    $lat_rad = deg2rad($dgeo[1]);
    $dec_rad = deg2rad($xx[1]);
    $ra_sun = $xx[0];

    // Formula: AO = RA - arcsin(tan(lat) * tan(dec))
    $tan_lat = tan($lat_rad);
    $tan_dec = tan($dec_rad);
    $asc_diff = rad2deg(asin($tan_lat * $tan_dec));
    $sun_ao = $ra_sun - $asc_diff;

    // Get Sirius ascensio obliqua
    $star_name = $ObjectName;
    $xx = []; // Reset array
    $retval = \swe_fixstar2($star_name, $tjd_ut, $helflag | 2048, $xx, $serr); // SEFLG_EQUATORIAL=2048
    if ($retval < 0) {
        echo "Error calculating Sirius: $serr (retval=$retval)\n";
        continue;
    }

    if (!isset($xx[0]) || !isset($xx[1])) {
        echo "Missing coordinates for Sirius at JD $tjd_ut\n";
        continue;
    }

    $ra_star = $xx[0];
    $dec_rad = deg2rad($xx[1]);
    $tan_dec = tan($dec_rad);
    $asc_diff = rad2deg(asin($tan_lat * $tan_dec));
    $star_ao = $ra_star - $asc_diff;

    // Calculate difference (normalize to -180..180)
    $diff = $star_ao - $sun_ao;
    while ($diff > 180.0) $diff -= 360.0;
    while ($diff < -180.0) $diff += 360.0;

    printf("%.5f\t%.6f\t%.6f\t%.6f\n", $tjd_ut, $sun_ao, $star_ao, $diff);
}

\swe_close();
