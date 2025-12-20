<?php
/**
 * Debug applyOsculatingNodApsTransformations step by step
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\Nutation;
use Swisseph\NutationMatrix;
use Swisseph\Obliquity;
use Swisseph\Precession;
use Swisseph\BarycentricPositions;
use Swisseph\Swe\Functions\PlanetsFunctions;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

$tjdEt = 2451545.0;
$ipl = Constants::SE_JUPITER;
$iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

// Input from OsculatingCalculator (xn after scaling)
$xp = [-0.9409603214, 5.0832614866, 0.0, 0.0, 0.0, 0.0];

printf("=== DEBUG applyOsculatingNodApsTransformations ===\n\n");
printf("INPUT (ecliptic of date XYZ):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);
$lon = rad2deg(atan2($xp[1], $xp[0]));
printf("  lon = %.10f°\n\n", $lon);

// Initialize by computing planet (to get obliquity/nutation cached)
$x = []; $serr = '';
$iflg0 = ($iflag & Constants::SEFLG_EPHMASK) | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS;
PlanetsFunctions::calc($tjdEt, $ipl, $iflg0, $x, $serr);

// Get barycentric Sun and Earth
$xsun = BarycentricPositions::getBarycentricSun($tjdEt, $iflag);
$earthFlags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED | Constants::SEFLG_J2000 |
              Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR;
$xear = [];
PlanetsFunctions::calc($tjdEt, Constants::SE_EARTH, $earthFlags, $xear, $serr);

printf("xsun = [%.10f, %.10f, %.10f]\n", $xsun[0], $xsun[1], $xsun[2]);
printf("xear = [%.10f, %.10f, %.10f]\n\n", $xear[0], $xear[1], $xear[2]);

// xobs = xear for geocentric
$xobs = $xear;

// Get obliquity
$oe = Obliquity::calc($tjdEt, $iflag, 0, null);
$seps = sin($oe);
$ceps = cos($oe);
printf("OBLIQUITY: oe=%.10f rad, seps=%.10f, ceps=%.10f\n\n", $oe, $seps, $ceps);

// Get nutation
$nutModel = Nutation::selectModelFromFlags($iflag);
[$dpsi, $deps] = Nutation::calc($tjdEt, $nutModel, false);
$snut = sin($deps);
$cnut = cos($deps);
printf("NUTATION: dpsi=%.10e, deps=%.10e\n", $dpsi, $deps);
printf("  snut=%.15f, cnut=%.15f\n\n", $snut, $cnut);

// Step 1: Remove nutation from ecliptic
$xOut = [];
Coordinates::coortrf2($xp, $xOut, -$snut, $cnut);
$xp[0] = $xOut[0]; $xp[1] = $xOut[1]; $xp[2] = $xOut[2];
printf("STEP 1 (remove nutation from ecliptic):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);
$lon = rad2deg(atan2($xp[1], $xp[0]));
printf("  lon = %.10f°\n\n", $lon);

// Step 2: Ecliptic → Equator
$xOut = [];
Coordinates::coortrf2($xp, $xOut, -$seps, $ceps);
$xp[0] = $xOut[0]; $xp[1] = $xOut[1]; $xp[2] = $xOut[2];
printf("STEP 2 (ecliptic → equator):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Step 3: Apply nutation (backward=TRUE for osculating)
$nutMatrix = NutationMatrix::build($dpsi, $deps, $oe, $seps, $ceps);
$xTemp = NutationMatrix::apply($nutMatrix, [$xp[0], $xp[1], $xp[2]], true);
$xp[0] = $xTemp[0]; $xp[1] = $xTemp[1]; $xp[2] = $xTemp[2];
printf("STEP 3 (nutation, backward=TRUE):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Step 4: Precess J → J2000
Precession::precess($xp, $tjdEt, $iflag, 1, null);
printf("STEP 4 (precess J→J2000):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Step 5: Add xsun (helio → bary)
for ($i = 0; $i <= 2; $i++) {
    $xp[$i] += $xsun[$i];
}
printf("STEP 5 (add xsun):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Step 6: Subtract xobs (bary → geocentric)
for ($i = 0; $i <= 2; $i++) {
    $xp[$i] -= $xobs[$i];
}
printf("STEP 6 (subtract xobs):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Step 10: Precess J2000 → J (since not J2000 flag)
Precession::precess($xp, $tjdEt, $iflag, -1, null);
printf("STEP 10 (precess J2000→J):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Step 11: Apply nutation (backward=FALSE)
$nutMatrix = NutationMatrix::build($dpsi, $deps, $oe, $seps, $ceps);
$xTemp = NutationMatrix::apply($nutMatrix, [$xp[0], $xp[1], $xp[2]], false);
$xp[0] = $xTemp[0]; $xp[1] = $xTemp[1]; $xp[2] = $xTemp[2];
printf("STEP 11 (nutation, backward=FALSE):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Step 12: Equator → Ecliptic
$xOut = [];
Coordinates::coortrf2($xp, $xOut, $seps, $ceps);
$xp[0] = $xOut[0]; $xp[1] = $xOut[1]; $xp[2] = $xOut[2];
printf("STEP 12 (equator → ecliptic):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Step 13: Apply nutation to ecliptic
$xOut = [];
Coordinates::coortrf2($xp, $xOut, $snut, $cnut);
$xp[0] = $xOut[0]; $xp[1] = $xOut[1]; $xp[2] = $xOut[2];
printf("STEP 13 (nutation to ecliptic):\n");
printf("  xp = [%.10f, %.10f, %.10f]\n", $xp[0], $xp[1], $xp[2]);

// Convert to spherical
$pol = [];
Coordinates::cartPol($xp, $pol);
$lon_deg = rad2deg($pol[0]);
$lat_deg = rad2deg($pol[1]);
printf("\nFINAL (spherical):\n");
printf("  lon = %.10f°, lat = %.10f°, r = %.10f AU\n", $lon_deg, $lat_deg, $pol[2]);

printf("\nC REFERENCE: lon = 100.5196455351°\n");
printf("DELTA = %.4f arcsec\n", abs($lon_deg - 100.5196455351) * 3600);
