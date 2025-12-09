<?php
/**
 * Сравнение гелио vs баро координат
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;

$ephe_path = realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe');
swe_set_ephe_path($ephe_path);

$jd = 2451545.0; // J2000.0
$ipl = Constants::SE_MERCURY;

echo "=== Mercury J2000.0 ===\n\n";

// 1. SWIEPH Heliocentric XYZ
$xx_swieph_helio = [];
$serr = '';
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
PlanetsFunctions::calc($jd, $ipl, $iflag, $xx_swieph_helio, $serr);
printf("SWIEPH HELIO XYZ: x=%13.10f  y=%13.10f  z=%13.10f AU\n",
    $xx_swieph_helio[0], $xx_swieph_helio[1], $xx_swieph_helio[2]);

// 2. SWIEPH Barycentric XYZ
$xx_swieph_bary = [];
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
PlanetsFunctions::calc($jd, $ipl, $iflag, $xx_swieph_bary, $serr);
printf("SWIEPH BARY  XYZ: x=%13.10f  y=%13.10f  z=%13.10f AU\n",
    $xx_swieph_bary[0], $xx_swieph_bary[1], $xx_swieph_bary[2]);

// 3. Получим SunBary
$xx_sunbary = [];
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
PlanetsFunctions::calc($jd, Constants::SE_SUN, $iflag, $xx_sunbary, $serr);
printf("SunBary      XYZ: x=%13.10f  y=%13.10f  z=%13.10f AU\n",
    $xx_sunbary[0], $xx_sunbary[1], $xx_sunbary[2]);

// 4. Проверим: helio + sunbary = bary?
$calculated_bary_x = $xx_swieph_helio[0] + $xx_sunbary[0];
$calculated_bary_y = $xx_swieph_helio[1] + $xx_sunbary[1];
$calculated_bary_z = $xx_swieph_helio[2] + $xx_sunbary[2];
printf("\nHelio + SunBary = x=%13.10f  y=%13.10f  z=%13.10f AU\n",
    $calculated_bary_x, $calculated_bary_y, $calculated_bary_z);

$diff_x = abs($calculated_bary_x - $xx_swieph_bary[0]);
$diff_y = abs($calculated_bary_y - $xx_swieph_bary[1]);
$diff_z = abs($calculated_bary_z - $xx_swieph_bary[2]);
$dist_km = sqrt($diff_x*$diff_x + $diff_y*$diff_y + $diff_z*$diff_z) * 149597870.7;
printf("Разница с BARY:   dx=%13.10f  dy=%13.10f  dz=%13.10f (%.1f km)\n",
    $diff_x, $diff_y, $diff_z, $dist_km);

echo "\n=== VSOP87 ===\n\n";

// 5. VSOP87 Heliocentric XYZ
$xx_vsop_helio = [];
$iflag = Constants::SEFLG_VSOP87 | Constants::SEFLG_HELCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
PlanetsFunctions::calc($jd, $ipl, $iflag, $xx_vsop_helio, $serr);
printf("VSOP87 HELIO XYZ: x=%13.10f  y=%13.10f  z=%13.10f AU\n",
    $xx_vsop_helio[0], $xx_vsop_helio[1], $xx_vsop_helio[2]);

// 6. VSOP87 Barycentric XYZ
$xx_vsop_bary = [];
$iflag = Constants::SEFLG_VSOP87 | Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ;
PlanetsFunctions::calc($jd, $ipl, $iflag, $xx_vsop_bary, $serr);
printf("VSOP87 BARY  XYZ: x=%13.10f  y=%13.10f  z=%13.10f AU\n",
    $xx_vsop_bary[0], $xx_vsop_bary[1], $xx_vsop_bary[2]);

// 7. Helio diff
$helio_diff_x = abs($xx_vsop_helio[0] - $xx_swieph_helio[0]);
$helio_diff_y = abs($xx_vsop_helio[1] - $xx_swieph_helio[1]);
$helio_diff_z = abs($xx_vsop_helio[2] - $xx_swieph_helio[2]);
$helio_dist_km = sqrt($helio_diff_x**2 + $helio_diff_y**2 + $helio_diff_z**2) * 149597870.7;
printf("\nHELIO разница VSOP-SWIEPH: %.1f km\n", $helio_dist_km);

// 8. Bary diff
$bary_diff_x = abs($xx_vsop_bary[0] - $xx_swieph_bary[0]);
$bary_diff_y = abs($xx_vsop_bary[1] - $xx_swieph_bary[1]);
$bary_diff_z = abs($xx_vsop_bary[2] - $xx_swieph_bary[2]);
$bary_dist_km = sqrt($bary_diff_x**2 + $bary_diff_y**2 + $bary_diff_z**2) * 149597870.7;
printf("BARY  разница VSOP-SWIEPH: %.1f km\n", $bary_dist_km);
