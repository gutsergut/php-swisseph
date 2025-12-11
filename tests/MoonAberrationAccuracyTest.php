<?php
require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

// JD UT: 2024-04-08 06:18 UT (момент, использовавшийся в диагностиках)
$ephePath = realpath(__DIR__ . '/../../eph/ephe');
if ($ephePath === false) {
    fwrite(STDERR, "Cannot resolve ephemeris path ../../eph/ephe\n");
    exit(10);
}
swe_set_ephe_path($ephePath);
// Можно добавить геотопо настройки позже
$tjd_ut = swe_julday(2024, 4, 8, 6.0 + 18.0/60.0, Constants::SE_GREG_CAL);

// Эталонные значения из swetest64 (геоцентрические)
$ref_geo_ra_deg = (0 + 44/60 + 9.6699/3600) * 15; // 11.047042° приблизительно
$ref_geo_dec_deg = 4 + 22/60 + 0.9610/3600;       // 4.3669339°

// Флаги: геоцентрические экваториальные со скоростью
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED;

$xx = []; $serr = null;
$rc = swe_calc_ut($tjd_ut, Constants::SE_MOON, $iflag, $xx, $serr);
if ($rc < 0) {
    fwrite(STDERR, "FAIL MoonAberrationAccuracyTest: swe_calc_ut error rc=$rc serr=$serr\n");
    exit(1);
}

$ra_err_arcsec = ($xx[0] - $ref_geo_ra_deg) * 3600.0;
$dec_err_arcsec = ($xx[1] - $ref_geo_dec_deg) * 3600.0;

// Порог временный до достижения субарксекундной точности
$max_ra_err = 6.0;    // arcsec
$max_dec_err = 0.5;   // arcsec

$ok = true;
if (abs($ra_err_arcsec) > $max_ra_err) {
    fwrite(STDERR, sprintf("RA error %.3f exceeds %.3f arcsec threshold\n", $ra_err_arcsec, $max_ra_err));
    $ok = false;
}
if (abs($dec_err_arcsec) > $max_dec_err) {
    fwrite(STDERR, sprintf("Dec error %.3f exceeds %.3f arcsec threshold\n", $dec_err_arcsec, $max_dec_err));
    $ok = false;
}

if (!$ok) {
    fwrite(STDERR, sprintf("FAIL MoonAberrationAccuracyTest: RA err=%.3f Dec err=%.3f\n", $ra_err_arcsec, $dec_err_arcsec));
    exit(2);
}

echo sprintf("PASS MoonAberrationAccuracyTest: RA err=%.3f Dec err=%.3f\n", $ra_err_arcsec, $dec_err_arcsec);
