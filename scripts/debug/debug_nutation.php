<?php
/**
 * Get nutation values from PHP
 */
require __DIR__ . '/vendor/autoload.php';

use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Nutation;
use Swisseph\Obliquity;
use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../eph/ephe');
State::setEphePath($ephePath);
SwedState::getInstance()->setEphePath($ephePath);

$tjd = 2451545.0; // J2000

// Get nutation
$iflag = Constants::SEFLG_SWIEPH;
$nutModel = Nutation::selectModelFromFlags($iflag);
[$dpsi, $deps] = Nutation::calc($tjd, $nutModel, false);

echo "=== PHP Nutation for J2000 ===\n";
printf("dpsi (rad) = %.15e\n", $dpsi);
printf("deps (rad) = %.15e\n", $deps);
printf("dpsi (deg) = %.15e\n", rad2deg($dpsi));
printf("deps (deg) = %.15e\n", rad2deg($deps));

$snut = sin($deps);
$cnut = cos($deps);

printf("\n=== Derived values ===\n");
printf("snut = sin(deps) = %.15f\n", $snut);
printf("cnut = cos(deps) = %.15f\n", $cnut);

// Get obliquity
$eps = Obliquity::calc($tjd, $iflag, 0, null);
$seps = sin($eps);
$ceps = cos($eps);

printf("eps (rad) = %.15e\n", $eps);
printf("seps = sin(eps) = %.15f\n", $seps);
printf("ceps = cos(eps) = %.15f\n", $ceps);

echo "\n=== C REFERENCE ===\n";
echo "deps (rad) = -2.797280438806837e-05\n";
echo "dpsi (rad) = -6.754195804145283e-05\n";
echo "snut = sin(deps) = -0.000027972804384\n";
echo "cnut = cos(deps) = 0.999999999608761\n";
echo "seps = sin(eps) = 0.397751304404018\n";
echo "ceps = cos(eps) = 0.917493269645561\n";
