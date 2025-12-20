<?php
/**
 * Debug test: compare Jupiter J2000 equatorial XYZ with C reference
 */
require_once __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;

swe_set_ephe_path('C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe');

$jd = 2451545.0;

// Match C flags exactly: iflJ2000 = SEFLG_SWIEPH|SEFLG_J2000|SEFLG_EQUATORIAL|SEFLG_XYZ|SEFLG_TRUEPOS|SEFLG_NONUT|SEFLG_SPEED|SEFLG_HELCTR
$iflJ2000 = Constants::SEFLG_SWIEPH |
            Constants::SEFLG_J2000 |
            Constants::SEFLG_EQUATORIAL |
            Constants::SEFLG_XYZ |
            Constants::SEFLG_TRUEPOS |
            Constants::SEFLG_NONUT |
            Constants::SEFLG_SPEED |
            Constants::SEFLG_HELCTR;

echo "Testing Jupiter J2000 Equatorial XYZ at JD 2451545.0\n";
echo "iflag = 0x" . dechex($iflJ2000) . " = " . $iflJ2000 . "\n\n";

$xx = [];
$serr = null;
$ret = swe_calc($jd, Constants::SE_JUPITER, $iflJ2000, $xx, $serr);

if ($ret < 0) {
    echo "Error: $serr\n";
    exit(1);
}

echo "PHP result:\n";
printf("  x = %.15f AU\n", $xx[0]);
printf("  y = %.15f AU\n", $xx[1]);
printf("  z = %.15f AU\n", $xx[2]);
printf("  vx = %.15f AU/day\n", $xx[3]);
printf("  vy = %.15f AU/day\n", $xx[4]);
printf("  vz = %.15f AU/day\n", $xx[5]);

// Now compare with swetest64.exe
echo "\nRun this in swetest to compare:\n";
echo "swetest64.exe -b1.1.2000 -ut12:00:00 -p5 -fP -xs -xequ -xcar\n";
