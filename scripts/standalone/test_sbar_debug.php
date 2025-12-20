<?php
/**
 * Debug app_pos_etc_sbar logic - compare with C
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\Bias;
use Swisseph\Precession;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/../../eph/ephe');

$tjd = 2460000.5;

echo "=== Debug app_pos_etc_sbar for Sun BARYCTR (PHP) ===\n";
echo "JD = $tjd\n\n";

// First, trigger calculation to populate pldat
$xx_earth = [];
$serr = '';
swe_calc($tjd, Constants::SE_EARTH, Constants::SEFLG_SWIEPH | Constants::SEFLG_J2000 | Constants::SEFLG_XYZ | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT, $xx_earth, $serr);

$swed = SwedState::getInstance();
$psbdp = $swed->pldat[SwephConstants::SEI_SUNBARY] ?? null;
$psdp = $swed->pldat[SwephConstants::SEI_EARTH] ?? null;

if ($psbdp === null) {
    echo "ERROR: SUNBARY not populated!\n";
    exit(1);
}

echo "psbdp->x (SUNBARY raw position):\n";
for ($i = 0; $i < 6; $i++) {
    printf("  [%d] = %.15f\n", $i, $psbdp->x[$i]);
}
echo "\n";

printf("psdp->teval = %.15f\n", $psdp->teval ?? 0);
printf("psbdp->teval = %.15f\n\n", $psbdp->teval ?? 0);

// Copy to xx for processing
$xx = [];
for ($i = 0; $i <= 5; $i++) {
    $xx[$i] = $psbdp->x[$i];
}

echo "Step 1: Copy psbdp->x to xx\n";
printf("  xx = [%.15f, %.15f, %.15f, %.15f, %.15f, %.15f]\n\n",
       $xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]);

// Light-time correction
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_BARYCTR | Constants::SEFLG_XYZ;

if (!($iflag & Constants::SEFLG_TRUEPOS)) {
    $r2 = $xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2];
    $r = sqrt($r2);
    // AUNIT = 1.4959787066e+11 m, CLIGHT = 299792458 m/s
    $dt = $r * Constants::AUNIT / Constants::CLIGHT / 86400.0;

    echo "Step 2: Light-time correction\n";
    printf("  r = sqrt(%.15f) = %.15f AU\n", $r2, $r);
    printf("  dt = r * AUNIT / CLIGHT / 86400 = %.15f days\n", $dt);

    for ($i = 0; $i <= 2; $i++) {
        printf("  xx[%d] -= dt * xx[%d+3] = %.15f -= %.15f * %.15f\n",
               $i, $i, $xx[$i], $dt, $xx[$i+3]);
        $xx[$i] -= $dt * $xx[$i + 3];
    }
    printf("  After light-time: xx = [%.15f, %.15f, %.15f]\n\n", $xx[0], $xx[1], $xx[2]);
}

// Zeroing speeds
if (!($iflag & Constants::SEFLG_SPEED)) {
    for ($i = 3; $i <= 5; $i++) {
        $xx[$i] = 0.0;
    }
    echo "Step 3: Zeroing speeds (SEFLG_SPEED not set)\n\n";
}

// Frame bias
echo "Step 4: Frame bias (ICRS to J2000)\n";
printf("  Before bias: xx = [%.15f, %.15f, %.15f]\n", $xx[0], $xx[1], $xx[2]);

$xxsv = $xx;

if (!($iflag & Constants::SEFLG_ICRS)) {
    $xx = Bias::apply($xx, $psdp->teval, $iflag, Bias::MODEL_DEFAULT, false);
    printf("  After bias:  xx = [%.15f, %.15f, %.15f]\n", $xx[0], $xx[1], $xx[2]);
    printf("  Bias delta:  [%.15e, %.15e, %.15e]\n\n",
           $xx[0]-$xxsv[0], $xx[1]-$xxsv[1], $xx[2]-$xxsv[2]);
}

// Save J2000
$xxsv = $xx;

// Precession
echo "Step 5: Precession (J2000 to date)\n";
printf("  Before prec: xx = [%.15f, %.15f, %.15f]\n", $xx[0], $xx[1], $xx[2]);

if (!($iflag & Constants::SEFLG_J2000)) {
    Precession::precess($xx, $psbdp->teval, $iflag, Constants::J2000_TO_J);
    printf("  After prec:  xx = [%.15f, %.15f, %.15f]\n", $xx[0], $xx[1], $xx[2]);
    printf("  Prec delta:  [%.15e, %.15e, %.15e]\n",
           $xx[0]-$xxsv[0], $xx[1]-$xxsv[1], $xx[2]-$xxsv[2]);
}
echo "\n";

// Epsilon values
echo "Step 6: Epsilon values\n";
printf("  swed.oec.eps  = %.15f deg (date)\n", $swed->oec->eps * 180.0 / M_PI);
printf("  swed.oec.seps = %.15f\n", $swed->oec->seps);
printf("  swed.oec.ceps = %.15f\n", $swed->oec->ceps);
printf("  swed.oec2000.eps  = %.15f deg (J2000)\n", $swed->oec2000->eps * 180.0 / M_PI);
printf("  swed.oec2000.seps = %.15f\n", $swed->oec2000->seps);
printf("  swed.oec2000.ceps = %.15f\n\n", $swed->oec2000->ceps);

// app_pos_rest: equatorial -> ecliptic
echo "Step 7: app_pos_rest will convert equatorial to ecliptic\n";
printf("  Input xx (equatorial of date): [%.15f, %.15f, %.15f]\n", $xx[0], $xx[1], $xx[2]);

// swi_coortrf2(xx, xx, seps, ceps) rotates by angle with sin=seps, cos=ceps
$seps = $swed->oec->seps;
$ceps = $swed->oec->ceps;
$x_ecl = $xx;
// coortrf2: x' = x, y' = y*ceps + z*seps, z' = -y*seps + z*ceps
$y_new = $xx[1] * $ceps + $xx[2] * $seps;
$z_new = -$xx[1] * $seps + $xx[2] * $ceps;
$x_ecl[1] = $y_new;
$x_ecl[2] = $z_new;
printf("  After coortrf2 (ecliptic): [%.15f, %.15f, %.15f]\n\n", $x_ecl[0], $x_ecl[1], $x_ecl[2]);

// Final result via swe_calc
echo "=== Final swe_calc results ===\n";
$xx_final = [];
swe_calc($tjd, Constants::SE_SUN, $iflag, $xx_final, $serr);
echo "Sun BARYCTR XYZ (ecliptic of date):\n";
printf("  x = %.15f\n", $xx_final[0]);
printf("  y = %.15f\n", $xx_final[1]);
printf("  z = %.15f\n", $xx_final[2]);

// With J2000
echo "\n=== Sun BARYCTR with J2000 ===\n";
swe_calc($tjd, Constants::SE_SUN, $iflag | Constants::SEFLG_J2000, $xx_final, $serr);
echo "Sun BARYCTR XYZ (ecliptic J2000):\n";
printf("  x = %.15f\n", $xx_final[0]);
printf("  y = %.15f\n", $xx_final[1]);
printf("  z = %.15f\n", $xx_final[2]);

echo "\n=== Reference from C ===\n";
echo "Sun BARYCTR XYZ (ecliptic of date):\n";
echo "  x = -0.008981057226304\n";
echo "  y = -0.000445411483745\n";
echo "  z =  0.000212362188732\n";
