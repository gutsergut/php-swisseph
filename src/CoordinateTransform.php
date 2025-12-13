<?php

declare(strict_types=1);

namespace Swisseph;

use Swisseph\SwephFile\PlanData;
use Swisseph\SwephFile\SwedState;

/**
 * Coordinate transformation functions
 * Port of app_pos_rest() and related functions from sweph.c
 */
final class CoordinateTransform
{
    /**
     * Apply final coordinate transformations and populate xreturn array
     * Port of app_pos_rest() from sweph.c:2776
     *
     * @param PlanData $pdp Planet data with xreturn array to populate
     * @param int $iflag Calculation flags
     * @param array $xx Input coordinates (XYZ + velocities) [x, y, z, dx, dy, dz]
     * @param float $seps Sin of mean obliquity
     * @param float $ceps Cos of mean obliquity
     * @return void
     */
    public static function appPosRest(
        PlanData $pdp,
        int $iflag,
        array &$xx,
        float $seps,
        float $ceps
    ): void {
        $swed = SwedState::getInstance();

        // Ensure obliquity and nutation for current date are available
        $tjd = $pdp->teval ?: \Swisseph\Constants::J2000;

        // If seps/ceps passed are zeros (shouldn't), derive from oec or compute from date
        if (($seps === 0.0 && $ceps === 0.0)) {
            // fallback to oec for date
            if (isset($swed->oec) && method_exists($swed->oec, 'needsUpdate') && $swed->oec->needsUpdate($tjd)) {
                $swed->oec->calculate($tjd, $iflag);
            }
            $seps = $swed->oec->seps ?? $seps;
            $ceps = $swed->oec->ceps ?? $ceps;
        }

        // Build/cached nutation state (ensures nutMatrix and snut/cnut are set)
        $swed->ensureNutation($tjd, $iflag, $seps, $ceps);

        // 1. Nutation in equatorial (swi_nutate) - apply matrix nutation if not NONUT
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $hasMatrix = false;
            foreach ($swed->nutMatrix as $v) { if ($v != 0.0) { $hasMatrix = true; break; } }
            if ($hasMatrix) {
                Coordinates::nutate($xx, $swed->nutMatrix, $swed->nutMatrixVelocity, $iflag, false);
            } else {
                // fallback: simple rotation (less accurate)
                $pos = [$xx[0], $xx[1], $xx[2]]; $out=[];
                Coordinates::coortrf2($pos, $out, $swed->snut, $swed->cnut);
                $xx[0]=$out[0]; $xx[1]=$out[1]; $xx[2]=$out[2];
                if ($iflag & Constants::SEFLG_SPEED) {
                    $vel = [$xx[3], $xx[4], $xx[5]]; $outv=[];
                    Coordinates::coortrf2($vel, $outv, $swed->snut, $swed->cnut);
                    $xx[3]=$outv[0]; $xx[4]=$outv[1]; $xx[5]=$outv[2];
                }
            }
        }

        // 2. Save equatorial cartesian (now we have equatorial cartesian coordinates)
        for ($i=0;$i<=5;$i++) { $pdp->xreturn[18+$i] = $xx[$i]; }

        // 3. Rotate equatorial -> ecliptic using mean obliquity (oe->seps, oe->ceps)
        $pos = [$xx[0], $xx[1], $xx[2]]; $out=[];
        Coordinates::coortrf2($pos, $out, $seps, $ceps);
        $xx[0]=$out[0]; $xx[1]=$out[1]; $xx[2]=$out[2];
        if ($iflag & Constants::SEFLG_SPEED) {
            $vel=[$xx[3],$xx[4],$xx[5]]; $outv=[];
            Coordinates::coortrf2($vel,$outv,$seps,$ceps);
            $xx[3]=$outv[0]; $xx[4]=$outv[1]; $xx[5]=$outv[2];
        }

        // 4. Nutation in ecliptic (swi_coortrf2 with snut/cnut) - NOT full nutate!
        // This rotates longitude by nutation angle, not a full matrix operation
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $pos = [$xx[0], $xx[1], $xx[2]]; $out=[];
            Coordinates::coortrf2($pos,$out,$swed->snut,$swed->cnut);
            $xx[0]=$out[0]; $xx[1]=$out[1]; $xx[2]=$out[2];
            if ($iflag & Constants::SEFLG_SPEED) {
                $vel=[$xx[3],$xx[4],$xx[5]]; $outv=[];
                Coordinates::coortrf2($vel,$outv,$swed->snut,$swed->cnut);
                $xx[3]=$outv[0]; $xx[4]=$outv[1]; $xx[5]=$outv[2];
            }
        }

        // 5. Save ecliptic cartesian (now we have ecliptic cartesian coordinates)
        for ($i=0;$i<=5;$i++) { $pdp->xreturn[6+$i] = $xx[$i]; }

        // 6. Polar conversions
        $eqCart = array_slice($pdp->xreturn, 18, 6); $eqPol=[];
        Coordinates::cartPolSp($eqCart,$eqPol);
        for($i=0;$i<=5;$i++){ $pdp->xreturn[12+$i]=$eqPol[$i]; }

        $eclCart = array_slice($pdp->xreturn, 6, 6); $eclPol=[];
        Coordinates::cartPolSp($eclCart,$eclPol);
        for($i=0;$i<=5;$i++){ $pdp->xreturn[$i]=$eclPol[$i]; }

        // 7. Convert rad->deg for angular elements (lon, lat, RA, Dec + speeds), unless SEFLG_RADIANS
        if (!($iflag & Constants::SEFLG_RADIANS)) {
            for ($i=0;$i<2;$i++) {
                $pdp->xreturn[$i]*=Constants::RADTODEG;
                $pdp->xreturn[$i+3]*=Constants::RADTODEG;
                $pdp->xreturn[$i+12]*=Constants::RADTODEG;
                $pdp->xreturn[$i+15]*=Constants::RADTODEG;
            }
        }

        $pdp->xflgs = $iflag;
        $pdp->iephe = $iflag & Constants::SEFLG_EPHMASK;
    }
}
