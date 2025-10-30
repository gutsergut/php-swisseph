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
        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("DEBUG appPosRest INPUT: xx=[%.10f, %.10f, %.10f, %.10f, %.10f, %.10f]",
                $xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]));
            error_log(sprintf("  seps=%.10f, ceps=%.10f", $seps, $ceps));
        }

        $swed = SwedState::getInstance();

        // CRITICAL: In C code, on INPUT xx[] is in EQUATORIAL J2000 XYZ (NOT ecliptic!)
        // C code app_pos_rest() at sweph.c:2776 expects equatorial coordinates from sweplan/sweph
        // The rot_back() function rotates coefficients into equatorial system (except for Moon which has extra rotation)

        // ===== Save equatorial cartesian coordinates FIRST =====
        // C code sweph.c:2788-2789: save BEFORE transformation to ecliptic
        // "now we have equatorial cartesian coordinates; save them"
        for ($i = 0; $i <= 5; $i++) {
            $pdp->xreturn[18 + $i] = $xx[$i];
        }

        // ===== Transform equatorial → ecliptic =====
        // C code sweph.c:2797: swi_coortrf2(xx, xx, oe->seps, oe->ceps);
        // This rotates FROM equatorial TO ecliptic using obliquity
        $xxTemp = [$xx[0], $xx[1], $xx[2]];
        $xxEq = [];
        Coordinates::coortrf2($xxTemp, $xxEq, $seps, $ceps);
        $xx[0] = $xxEq[0];
        $xx[1] = $xxEq[1];
        $xx[2] = $xxEq[2];

        if ($iflag & Constants::SEFLG_SPEED) {
            $xxTemp = [$xx[3], $xx[4], $xx[5]];
            $xxEq = [];
            Coordinates::coortrf2($xxTemp, $xxEq, $seps, $ceps);
            $xx[3] = $xxEq[0];
            $xx[4] = $xxEq[1];
            $xx[5] = $xxEq[2];
        }

        // Apply nutation if requested
        // C code sweph.c:2803-2806
        if (!($iflag & Constants::SEFLG_NONUT)) {
            // Check if nutation matrix is initialized
            $nutationAvailable = false;
            foreach ($swed->nutMatrix as $val) {
                if ($val != 0.0) {
                    $nutationAvailable = true;
                    break;
                }
            }

            if ($nutationAvailable) {
                $xxTemp = [$xx[0], $xx[1], $xx[2]];
                $xxEq = [];
                Coordinates::coortrf2($xxTemp, $xxEq, $swed->snut, $swed->cnut);
                $xx[0] = $xxEq[0];
                $xx[1] = $xxEq[1];
                $xx[2] = $xxEq[2];

                if ($iflag & Constants::SEFLG_SPEED) {
                    $xxTemp = [$xx[3], $xx[4], $xx[5]];
                    $xxEq = [];
                    Coordinates::coortrf2($xxTemp, $xxEq, $swed->snut, $swed->cnut);
                    $xx[3] = $xxEq[0];
                    $xx[4] = $xxEq[1];
                    $xx[5] = $xxEq[2];
                }
            }
        }

        // ===== Save ecliptic cartesian coordinates =====
        // C code sweph.c:2807-2808: "now we have ecliptic cartesian coordinates"
        for ($i = 0; $i <= 5; $i++) {
            $pdp->xreturn[6 + $i] = $xx[$i];
        }

        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("DEBUG appPosRest OUTPUT: equatorial xx=[%.10f, %.10f, %.10f, %.10f, %.10f, %.10f]",
                $pdp->xreturn[18], $pdp->xreturn[19], $pdp->xreturn[20], $pdp->xreturn[21], $pdp->xreturn[22], $pdp->xreturn[23]));
            error_log(sprintf("  ecliptic xx=[%.10f, %.10f, %.10f, %.10f, %.10f, %.10f]",
                $pdp->xreturn[6], $pdp->xreturn[7], $pdp->xreturn[8], $pdp->xreturn[9], $pdp->xreturn[10], $pdp->xreturn[11]));
        }

        // ===== Transformation to polar coordinates =====
        // Convert equatorial XYZ → RA/Dec/R (xreturn[18..23] → xreturn[12..17])
        $xEqCart = array_slice($pdp->xreturn, 18, 6);
        $xEqPol = [];
        Coordinates::cartPolSp($xEqCart, $xEqPol);
        for ($i = 0; $i <= 5; $i++) {
            $pdp->xreturn[12 + $i] = $xEqPol[$i];
        }

        // Convert ecliptic XYZ → Lon/Lat/R (xreturn[6..11] → xreturn[0..5])
        $xEclCart = array_slice($pdp->xreturn, 6, 6);
        $xEclPol = [];
        Coordinates::cartPolSp($xEclCart, $xEclPol);
        for ($i = 0; $i <= 5; $i++) {
            $pdp->xreturn[0 + $i] = $xEclPol[$i];
        }

        // ===== Radians to degrees =====
        // Convert angles (lon, lat, RA, Dec and their speeds) from radians to degrees
        for ($i = 0; $i < 2; $i++) {
            $pdp->xreturn[$i] *= Constants::RADTODEG;          // Ecliptic lon, lat
            $pdp->xreturn[$i + 3] *= Constants::RADTODEG;      // Ecliptic lon/lat speeds
            $pdp->xreturn[$i + 12] *= Constants::RADTODEG;     // Equatorial RA, Dec
            $pdp->xreturn[$i + 15] *= Constants::RADTODEG;     // Equatorial RA/Dec speeds
        }

        // Save flags
        $pdp->xflgs = $iflag;
        $pdp->iephe = $iflag & Constants::SEFLG_EPHMASK;
    }
}
