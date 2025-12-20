<?php
/**
 * Debug: Compare RA/Dec with swetest
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = __DIR__ . '/../../eph/ephe';
swe_set_ephe_path($ephePath);

echo "=== RA/Dec Comparison (J2000, TRUEPOS, NONUT) ===\n\n";

$jd = 2451545.0;

// Get equatorial coordinates
$flags = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_EQUATORIAL;
$xx = [];
$serr = '';
swe_calc($jd, Constants::SE_MOON, $flags, $xx, $serr);

$ra = $xx[0];
$dec = $xx[1];

// Convert RA to hours
$ra_h = $ra / 15.0;
$ra_hh = (int)$ra_h;
$ra_mm = (int)(($ra_h - $ra_hh) * 60);
$ra_ss = (($ra_h - $ra_hh) * 60 - $ra_mm) * 60;

$dec_sign = $dec >= 0 ? '+' : '-';
$dec_abs = abs($dec);
$dec_dd = (int)$dec_abs;
$dec_mm = (int)(($dec_abs - $dec_dd) * 60);
$dec_ss = (($dec_abs - $dec_dd) * 60 - $dec_mm) * 60;

echo "PHP Moon:\n";
echo sprintf("  RA  = %dh%02dm%.4fs (%.10f°)\n", $ra_hh, $ra_mm, $ra_ss, $ra);
echo sprintf("  Dec = %s%d°%02d'%.4f\" (%.10f°)\n", $dec_sign, $dec_dd, $dec_mm, $dec_ss, $dec);

echo "\nswetest:\n";
echo "  RA  = 14h49m49.4090s\n";
echo "  Dec = -10°54'10.4880\"\n";

// Convert swetest RA/Dec to degrees
$swe_ra = 14*15 + 49/4 + 49.4090/240;
$swe_dec = -(10 + 54/60 + 10.4880/3600);

echo "\nIn degrees:\n";
echo sprintf("  swetest RA  = %.10f°\n", $swe_ra);
echo sprintf("  swetest Dec = %.10f°\n", $swe_dec);

$diff_ra = ($ra - $swe_ra) * 3600;
$diff_dec = ($dec - $swe_dec) * 3600;

echo "\nDifferences:\n";
echo sprintf("  RA diff  = %.4f arcsec\n", $diff_ra);
echo sprintf("  Dec diff = %.4f arcsec\n", $diff_dec);

// Also get equatorial XYZ
$flagsXYZ = Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ;
$xxXYZ = [];
swe_calc($jd, Constants::SE_MOON, $flagsXYZ, $xxXYZ, $serr);

echo "\nPHP Equatorial XYZ:\n";
echo sprintf("  X = %.15f\n", $xxXYZ[0]);
echo sprintf("  Y = %.15f\n", $xxXYZ[1]);
echo sprintf("  Z = %.15f\n", $xxXYZ[2]);
