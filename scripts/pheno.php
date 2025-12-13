<?php
/**
 * Тест планетарных явлений (фазы, видимость)
 */

require __DIR__ . '/../tests/bootstrap.php';

use Swisseph\Constants;

$jd = 2451545.0; // J2000.0

// Test Moon phase
$attr = [];
$serr = null;
$ret = swe_pheno($jd, Constants::SE_MOON, Constants::SEFLG_SWIEPH, $attr, $serr);

if ($ret < 0) {
    fwrite(STDERR, "Moon pheno failed: $serr\n");
    exit(1);
}

if (count($attr) < 6) {
    fwrite(STDERR, "Pheno attr array too short: " . count($attr) . "\n");
    exit(2);
}

// Phase angle should be 0-180
$phase_angle = $attr[0];
if ($phase_angle < 0.0 || $phase_angle > 180.0) {
    fwrite(STDERR, "Moon phase angle out of range: $phase_angle\n");
    exit(3);
}

// Phase (illumination) 0-1
$phase = $attr[1];
if ($phase < 0.0 || $phase > 1.0) {
    fwrite(STDERR, "Moon phase out of range: $phase\n");
    exit(4);
}

// Elongation should be reasonable
$elongation = $attr[2];
if ($elongation < 0.0 || $elongation > 180.0) {
    fwrite(STDERR, "Elongation out of range: $elongation\n");
    exit(5);
}

// Apparent diameter should be positive (in arcseconds for Moon ~1800")
$diameter = $attr[3];
if ($diameter <= 0.0 || $diameter > 4000.0) {
    fwrite(STDERR, "Apparent diameter suspicious: $diameter arcsec\n");
    exit(6);
}

// Magnitude should be reasonable for Moon
$magnitude = $attr[4];
if ($magnitude < -15.0 || $magnitude > 0.0) {
    fwrite(STDERR, "Moon magnitude suspicious: $magnitude\n");
    exit(7);
}

// Test Venus phenomena
$attr_venus = [];
$ret = swe_pheno($jd, Constants::SE_VENUS, Constants::SEFLG_SWIEPH, $attr_venus, $serr);

if ($ret < 0) {
    fwrite(STDERR, "Venus pheno failed: $serr\n");
    exit(8);
}

// Venus magnitude should be around -4
$mag_venus = $attr_venus[4];
if ($mag_venus < -5.0 || $mag_venus > -2.0) {
    fwrite(STDERR, "Venus magnitude suspicious: $mag_venus\n");
    exit(9);
}

// Test swe_pheno_ut variant
$attr_ut = [];
$ret = swe_pheno_ut($jd, Constants::SE_JUPITER, Constants::SEFLG_SWIEPH, $attr_ut, $serr);

if ($ret < 0) {
    fwrite(STDERR, "Jupiter pheno_ut failed: $serr\n");
    exit(10);
}

if (count($attr_ut) < 6) {
    fwrite(STDERR, "pheno_ut attr array too short\n");
    exit(11);
}

// Jupiter magnitude around -2
$mag_jupiter = $attr_ut[4];
if ($mag_jupiter < -3.0 || $mag_jupiter > 0.0) {
    fwrite(STDERR, "Jupiter magnitude suspicious: $mag_jupiter\n");
    exit(12);
}

// Test Mars
$attr_mars = [];
$ret = swe_pheno($jd, Constants::SE_MARS, Constants::SEFLG_SWIEPH, $attr_mars, $serr);

if ($ret < 0) {
    fwrite(STDERR, "Mars pheno failed: $serr\n");
    exit(13);
}

// Mars phase should be close to full (Earth is between Mars and Sun often)
// But can vary significantly
$phase_mars = $attr_mars[1];
if ($phase_mars < 0.5 || $phase_mars > 1.0) {
    fwrite(STDERR, "Mars phase suspicious: $phase_mars\n");
    exit(14);
}

echo "OK\n";
