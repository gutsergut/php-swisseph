<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
if ($ephePath === false) {
    fwrite(STDERR, "Cannot resolve ephemeris path ../../eph/ephe\n");
    exit(10);
}
swe_set_ephe_path($ephePath);

$jd_ut = swe_julday(2024, 4, 8, 6.0 + 18.0/60.0, Constants::SE_GREG_CAL);

// Compute PHP geo and topo RA/Dec
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED;
$xx_geo = []; $serr = null;
swe_calc_ut($jd_ut, Constants::SE_MOON, $iflag, $xx_geo, $serr);

// Dallas, TX (~ -96.8E, 32.8N)
swe_set_topo(-96.8, 32.8, 0.0);
$xx_topo = []; $serr2 = null;
swe_calc_ut($jd_ut, Constants::SE_MOON, $iflag | Constants::SEFLG_TOPOCTR, $xx_topo, $serr2);

$dra_php = ($xx_topo[0] - $xx_geo[0]) * 3600.0;
$ddec_php = ($xx_topo[1] - $xx_geo[1]) * 3600.0;

// Reference values from swetest64 (recorded)
$ref_geo_ra = (0 + 44/60 + 9.6699/3600) * 15;
$ref_geo_dec = 4 + 22/60 + 0.9610/3600;
$ref_topo_ra = (0 + 44/60 + 22.6312/3600) * 15;
$ref_topo_dec = 3 + 45/60 + 49.9800/3600;
$dra_ref = ($ref_topo_ra - $ref_geo_ra) * 3600.0;
$ddec_ref = ($ref_topo_dec - $ref_geo_dec) * 3600.0;

$ratio_ra = $dra_php / $dra_ref;
$ratio_dec = $ddec_php / $ddec_ref;

$ok = abs($ratio_ra - 1.0) <= 0.002 && abs($ratio_dec - 1.0) <= 0.002;

if (!$ok) {
    fwrite(STDERR, sprintf("FAIL MoonTopoParallaxTest: ratio_ra=%.4f ratio_dec=%.4f\n", $ratio_ra, $ratio_dec));
    exit(2);
}

echo sprintf("PASS MoonTopoParallaxTest: ratio_ra=%.4f ratio_dec=%.4f\n", $ratio_ra, $ratio_dec);
