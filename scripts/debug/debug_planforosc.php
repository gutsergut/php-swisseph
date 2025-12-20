<?php
/**
 * Step-by-step debug of planForOscElem for Jupiter
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants;
use Swisseph\Bias;
use Swisseph\Precession;
use Swisseph\Obliquity;
use Swisseph\Nutation;
use Swisseph\NutationMatrix;
use Swisseph\Coordinates;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

$tjd = 2451545.0; // J2000.0
$ipl = Constants::SE_JUPITER;

// Get heliocentric J2000 equatorial position
$iflJ2000 = Constants::SEFLG_SWIEPH |
            Constants::SEFLG_J2000 |
            Constants::SEFLG_EQUATORIAL |
            Constants::SEFLG_XYZ |
            Constants::SEFLG_TRUEPOS |
            Constants::SEFLG_NONUT |
            Constants::SEFLG_SPEED |
            Constants::SEFLG_HELCTR;

$xx = [];
$serr = '';
$ret = PlanetsFunctions::calc($tjd, $ipl, $iflJ2000, $xx, $serr);

printf("=== STEP BY STEP planForOscElem for Jupiter ===\n\n");

printf("INPUT (Heliocentric J2000 Equatorial XYZ):\n");
printf("  xx = [%.10f, %.10f, %.10f, %.10f, %.10f, %.10f]\n\n",
    $xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]);

// C reference INPUT:
printf("C REF INPUT:\n");
printf("  xx = [4.0011997068, 2.7365499715, 1.0754986017, -0.0045682676, 0.0058814949, 0.0026323167]\n\n");

// Step 1: Frame bias ICRS -> J2000
$iflg0 = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS | Constants::SEFLG_HELCTR;
$xx = Bias::apply($xx, $tjd, $iflg0, Bias::MODEL_IAU_2006, false, 0);

printf("After BIAS:\n");
printf("  xx = [%.10f, %.10f, %.10f]\n\n", $xx[0], $xx[1], $xx[2]);

// Step 2: Precession J2000 -> date (since iflg0 does NOT have SEFLG_J2000)
Precession::precess($xx, $tjd, $iflg0, -1, null);
$vel = [$xx[3], $xx[4], $xx[5]];
Precession::precess($vel, $tjd, $iflg0, -1, null);
$xx[3] = $vel[0]; $xx[4] = $vel[1]; $xx[5] = $vel[2];

printf("After PRECESSION (J2000->date):\n");
printf("  xx = [%.10f, %.10f, %.10f]\n\n", $xx[0], $xx[1], $xx[2]);

// Get obliquity of date
$eps = Obliquity::calc($tjd, $iflg0, 0, null);
$seps = sin($eps);
$ceps = cos($eps);

printf("OBLIQUITY:\n");
printf("  eps = %.10f rad, seps = %.10f, ceps = %.10f\n\n", $eps, $seps, $ceps);

// Step 3: Nutation (mean->true equator of date)
$nutModel = Nutation::selectModelFromFlags($iflg0);
[$dpsi, $deps] = Nutation::calc($tjd, $nutModel, false);
$nutMatrix = NutationMatrix::build($dpsi, $deps, $eps, $seps, $ceps);

$xTemp = NutationMatrix::apply($nutMatrix, $xx);
$xx[0] = $xTemp[0]; $xx[1] = $xTemp[1]; $xx[2] = $xTemp[2];
$velTemp = NutationMatrix::apply($nutMatrix, [$xx[3], $xx[4], $xx[5]]);
$xx[3] = $velTemp[0]; $xx[4] = $velTemp[1]; $xx[5] = $velTemp[2];

printf("After NUTATION (Step 3):\n");
printf("  xx = [%.10f, %.10f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
printf("C REF (AFTER nutation Step 3):\n");
printf("  xx = [4.0013980759, 2.7363324029, 1.0753141407]\n\n");

// Step 4: Transform EQUATORIAL -> ECLIPTIC
$xOut = [];
Coordinates::coortrf2([$xx[0], $xx[1], $xx[2]], $xOut, $seps, $ceps);
$xx[0] = $xOut[0]; $xx[1] = $xOut[1]; $xx[2] = $xOut[2];
$velOut = [];
Coordinates::coortrf2([$xx[3], $xx[4], $xx[5]], $velOut, $seps, $ceps);
$xx[3] = $velOut[0]; $xx[4] = $velOut[1]; $xx[5] = $velOut[2];

printf("After ECLIPTIC TRANSFORM:\n");
printf("  xx = [%.10f, %.10f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
printf("C REF (AFTER ecliptic transform):\n");
printf("  xx = [4.0013980759, 2.9382713169, -0.1018684875]\n\n");

// Step 5: Nutation to ecliptic
$snut = sin($deps);
$cnut = cos($deps);
$xOut = [];
Coordinates::coortrf2([$xx[0], $xx[1], $xx[2]], $xOut, $snut, $cnut);
$xx[0] = $xOut[0]; $xx[1] = $xOut[1]; $xx[2] = $xOut[2];

printf("After NUTATION TO ECLIPTIC (Step 5):\n");
printf("  xx = [%.10f, %.10f, %.10f]\n", $xx[0], $xx[1], $xx[2]);
printf("C REF OUTPUT:\n");
printf("  xx = [4.0013980759, 2.9382741652, -0.1017862972]\n\n");

// Calculate longitude
$lon = rad2deg(atan2($xx[1], $xx[0]));
if ($lon < 0) $lon += 360.0;
printf("PHP longitude: %.10f°\n", $lon);
printf("C REF longitude: 36.2902804173°\n");
