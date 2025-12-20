<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// JD
$jd = swe_julday(2024,4,8,6+18/60, Constants::SE_GREG_CAL);

swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

// 1. Геоцентрические экваториальные (как C - без topocentric)
$iflag_geo = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED;
$xx_geo = []; $serr=null;
swe_calc_ut($jd, Constants::SE_MOON, $iflag_geo, $xx_geo, $serr);

// 2. Топоцентрические экваториальные
swe_set_topo(-96.8,32.8,0.0);
$iflag_topo = $iflag_geo | Constants::SEFLG_TOPOCTR;
$xx_topo = []; $serr2=null;
swe_calc_ut($jd, Constants::SE_MOON, $iflag_topo, $xx_topo, $serr2);

function deg2hms($deg) {
	$h = $deg / 15;
	$H = floor($h);
	$M = floor(($h - $H) * 60);
	$S = ($h - $H) * 3600 - $M * 60;
	return sprintf('%02dh %02dm %06.3fs', $H, $M, $S);
}

// 3. Геоцентрические эклиптические (убираем SEFLG_EQUATORIAL)
$iflag_geo_ecl = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED; // no EQUATORIAL
$xx_geo_ecl = []; $serr3 = null;
swe_calc_ut($jd, Constants::SE_MOON, $iflag_geo_ecl, $xx_geo_ecl, $serr3);

// 4. Топоцентрические эклиптические
$iflag_topo_ecl = $iflag_geo_ecl | Constants::SEFLG_TOPOCTR;
$xx_topo_ecl = []; $serr4 = null;
swe_calc_ut($jd, Constants::SE_MOON, $iflag_topo_ecl, $xx_topo_ecl, $serr4);

echo "JD: $jd\n\n";
echo "Geo RA:  ".$xx_geo[0]."° (".deg2hms($xx_geo[0]).") Dec: ".$xx_geo[1]."° Dist: ".$xx_geo[2]."\n";
echo "Topo RA: ".$xx_topo[0]."° (".deg2hms($xx_topo[0]).") Dec: ".$xx_topo[1]."° Dist: ".$xx_topo[2]."\n";
echo "Geo Ecl: Lon ".$xx_geo_ecl[0]."° Lat ".$xx_geo_ecl[1]."° Dist ".$xx_geo_ecl[2]."\n";
echo "Topo Ecl: Lon ".$xx_topo_ecl[0]."° Lat ".$xx_topo_ecl[1]."° Dist ".$xx_topo_ecl[2]."\n";

// Эталонные из swetest64
$ref_geo_ra = (0 + 44/60 + 9.6699/3600)*15; // 11.047, близко
$ref_geo_dec = 4 + 22/60 + 0.9610/3600; // 4.3669339°
$ref_geo_dist = 0.002400390;
$ref_geo_lon = 11.8575467; $ref_geo_lat = -0.3489593;
$ref_topo_lon = 11.6717004; $ref_topo_lat = -0.9251860; // из swetest -topo
$ref_topo_ra = (0 + 44/60 + 22.6312/3600)*15; // 11.094296°
$ref_topo_dec = 3 + 45/60 + 49.9800/3600; // 3.763883°
$ref_topo_dist = 0.002434472;

$err_geo_ra = ($xx_geo[0]-$ref_geo_ra)*3600; $err_geo_dec = ($xx_geo[1]-$ref_geo_dec)*3600; $err_geo_dist = $xx_geo[2]-$ref_geo_dist;
$err_topo_ra = ($xx_topo[0]-$ref_topo_ra)*3600; $err_topo_dec = ($xx_topo[1]-$ref_topo_dec)*3600; $err_topo_dist = $xx_topo[2]-$ref_topo_dist;

echo "\nErrors (arcsec unless stated):\n";
echo sprintf("Geo:  RA %+8.3f"."\" Dec %+8.3f"."\" Dist %+g\n", $err_geo_ra, $err_geo_dec, $err_geo_dist);
echo sprintf("Topo: RA %+8.3f"."\" Dec %+8.3f"."\" Dist %+g\n", $err_topo_ra, $err_topo_dec, $err_topo_dist);
// Ecliptic geo errors
$err_geo_lon = ($xx_geo_ecl[0]-$ref_geo_lon)*3600;
$err_geo_lat = ($xx_geo_ecl[1]-$ref_geo_lat)*3600;
echo sprintf("Geo Ecl: Lon %+8.3f\" Lat %+8.3f\"\n", $err_geo_lon, $err_geo_lat);

// Topo ecliptic errors
$err_topo_lon = ($xx_topo_ecl[0]-$ref_topo_lon)*3600;
$err_topo_lat = ($xx_topo_ecl[1]-$ref_topo_lat)*3600;
echo sprintf("Topo Ecl: Lon %+8.3f\" Lat %+8.3f\"\n", $err_topo_lon, $err_topo_lat);

// Параллакс в эклиптических координатах (разница между geo и topo)
$php_ecl_shift_lon = ($xx_topo_ecl[0]-$xx_geo_ecl[0])*3600;
$php_ecl_shift_lat = ($xx_topo_ecl[1]-$xx_geo_ecl[1])*3600;
$ref_ecl_shift_lon = ($ref_topo_lon-$ref_geo_lon)*3600;
$ref_ecl_shift_lat = ($ref_topo_lat-$ref_geo_lat)*3600;
echo "\nEcliptic Shift (Topo - Geo):\n";
echo sprintf("PHP shift Lon: %8.3f\"  Lat: %8.3f\"\n", $php_ecl_shift_lon, $php_ecl_shift_lat);
echo sprintf("REF shift Lon: %8.3f\"  Lat: %8.3f\"\n", $ref_ecl_shift_lon, $ref_ecl_shift_lat);
echo sprintf("Ratio shift Lon: %.4f  Lat: %.4f\n", $php_ecl_shift_lon/$ref_ecl_shift_lon, $php_ecl_shift_lat/$ref_ecl_shift_lat);

// Диагностика: разница между geo и topo в PHP vs эталон
$php_shift_ra = ($xx_topo[0]-$xx_geo[0])*3600; $php_shift_dec = ($xx_topo[1]-$xx_geo[1])*3600;
$ref_shift_ra = ($ref_topo_ra-$ref_geo_ra)*3600; $ref_shift_dec = ($ref_topo_dec-$ref_geo_dec)*3600;

echo "\nShift (Topo - Geo):\n";
echo sprintf("PHP shift RA: %8.3f\"  Dec: %8.3f\"\n", $php_shift_ra, $php_shift_dec);
echo sprintf("REF shift RA: %8.3f\"  Dec: %8.3f\"\n", $ref_shift_ra, $ref_shift_dec);

$ratio_ra = $php_shift_ra / $ref_shift_ra; $ratio_dec = $php_shift_dec / $ref_shift_dec;
echo sprintf("Ratio shift RA: %.4f  Dec: %.4f\n", $ratio_ra, $ratio_dec);

// Если geo уже отличается сильно — искать причину в прецессии/нутации/аберрации.
// Если geo совпадает, а topo уехал — искать проблему в топо параллаксе.
