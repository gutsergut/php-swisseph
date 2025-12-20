<?php

namespace Swisseph\SwephFile;

use Swisseph\Math;

/**
 * Rotation and transformation of Chebyshev series
 *
 * Port of rot_back() from sweph.c:4961
 *
 * Adds reference orbit to Chebyshev series (if SEI_FLG_ELLIPSE)
 * and rotates series to mean equinox of J2000
 */
final class SeriesRotation
{
    /** sin(eps2000) - sine of obliquity at J2000 */
    private const SEPS2000 = 0.39777715572793088;

    /** cos(eps2000) - cosine of obliquity at J2000 */
    private const CEPS2000 = 0.91748206215761929;

    /** TWOPI constant */
    private const TWOPI = 6.283185307179586476925287;

    /**
     * Rotate Chebyshev series back to J2000 mean equinox
     *
     * Port of rot_back() from sweph.c:4961
     *
     * @param int $ipli Planet index
     */
    public static function rotateBack(int $ipli): void
    {
        $swed = SwedState::getInstance();
        $pdp = &$swed->pldat[$ipli];
        $nco = $pdp->ncoe;

        // Get middle time of segment
        $t = $pdp->tseg0 + $pdp->dseg / 2.0;

        // Get pointers to coefficient arrays for x, y, z
        $chcfx = &$pdp->segp; // First ncoe elements
        $chcfy_offset = $nco;
        $chcfz_offset = 2 * $nco;

        $tdiff = ($t - $pdp->telem) / 365250.0;

        // Calculate average perihelion longitude components
        if ($ipli == SwephConstants::SEI_MOON) {
            $dn = $pdp->prot + $tdiff * $pdp->dprot;
            $i = (int)($dn / self::TWOPI);
            $dn -= $i * self::TWOPI;
            $qav = ($pdp->qrot + $tdiff * $pdp->dqrot) * cos($dn);
            $pav = ($pdp->qrot + $tdiff * $pdp->dqrot) * sin($dn);
        } else {
            $qav = $pdp->qrot + $tdiff * $pdp->dqrot;
            $pav = $pdp->prot + $tdiff * $pdp->dprot;
        }

        if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER) {
            error_log(sprintf("DEBUG rotateBack START: ipli=%d, nco=%d", $ipli, $nco));
            error_log(sprintf("  t=%.10f, telem=%.10f, tdiff=%.10f", $t, $pdp->telem, $tdiff));
            error_log(sprintf("  qav=%.15f, pav=%.15f", $qav, $pav));
        }

        // Copy coefficients to working array
        $x = [];
        for ($i = 0; $i < $nco; $i++) {
            $x[$i] = [
                $pdp->segp[$i],                    // x coefficient
                $pdp->segp[$i + $chcfy_offset],    // y coefficient
                $pdp->segp[$i + $chcfz_offset]     // z coefficient
            ];
        }

        if (getenv('DEBUG_MOON_ROTBACK') && $ipli == SwephConstants::SEI_MOON) {
            error_log(sprintf("DEBUG rotateBack MOON: raw x[0]=[%.15e, %.15e, %.15e]", $x[0][0], $x[0][1], $x[0][2]));
            error_log(sprintf("  iflg=0x%X, SEI_FLG_ELLIPSE=%d", $pdp->iflg, ($pdp->iflg & SwephConstants::SEI_FLG_ELLIPSE) ? 1 : 0));
        }

        if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER) {
            error_log(sprintf("  AFTER copy from segp: x[0]=[%.10f, %.10f, %.10f]", $x[0][0], $x[0][1], $x[0][2]));
            error_log(sprintf("  iflg=0x%X, SEI_FLG_ELLIPSE=0x%X, check=%d", $pdp->iflg, SwephConstants::SEI_FLG_ELLIPSE, $pdp->iflg & SwephConstants::SEI_FLG_ELLIPSE));
        }

        // If reference ellipse is used, add it
        if ($pdp->iflg & SwephConstants::SEI_FLG_ELLIPSE) {
            $refepx = $pdp->refep; // First nco elements

            $omtild = $pdp->peri + $tdiff * $pdp->dperi;
            $i = (int)($omtild / self::TWOPI);
            $omtild -= $i * self::TWOPI;
            $com = cos($omtild);
            $som = sin($omtild);

            if (getenv('DEBUG_MOON_ROTBACK') && $ipli == SwephConstants::SEI_MOON) {
                error_log(sprintf("DEBUG ELLIPSE: omtild=%.15f, com=%.15f, som=%.15f", $omtild, $com, $som));
                error_log(sprintf("  refepx[0]=%.15e, refepy[0]=%.15e", $refepx[0], $refepx[$nco]));
            }

            // Add reference orbit
            for ($i = 0; $i < $nco; $i++) {
                $refepy_i = $refepx[$i + $nco]; // refepy starts at offset nco
                $x[$i][0] = $pdp->segp[$i] + $com * $refepx[$i] - $som * $refepy_i;
                $x[$i][1] = $pdp->segp[$i + $chcfy_offset] + $com * $refepy_i + $som * $refepx[$i];
            }

            if (getenv('DEBUG_MOON_ROTBACK') && $ipli == SwephConstants::SEI_MOON) {
                error_log(sprintf("DEBUG ELLIPSE: AFTER x[0]=[%.15e, %.15e, %.15e]", $x[0][0], $x[0][1], $x[0][2]));
            }
        }

        // Construct right-handed orthonormal system
        $cosih2 = 1.0 / (1.0 + $qav * $qav + $pav * $pav);

        if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER) {
            error_log(sprintf("  cosih2=%.15f", $cosih2));
        }

        // Calculate orbit pole
        $uiz = [
            2.0 * $pav * $cosih2,
            -2.0 * $qav * $cosih2,
            (1.0 - $qav * $qav - $pav * $pav) * $cosih2
        ];

        // Calculate origin of longitudes vector
        $uix = [
            (1.0 + $qav * $qav - $pav * $pav) * $cosih2,
            2.0 * $qav * $pav * $cosih2,
            -2.0 * $pav * $cosih2
        ];

        // Calculate vector in orbital plane orthogonal to origin of longitudes
        $uiy = [
            2.0 * $qav * $pav * $cosih2,
            (1.0 - $qav * $qav + $pav * $pav) * $cosih2,
            2.0 * $qav * $cosih2
        ];

        if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER) {
            error_log(sprintf("  uix=[%.15f, %.15f, %.15f]", $uix[0], $uix[1], $uix[2]));
            error_log(sprintf("  uiy=[%.15f, %.15f, %.15f]", $uiy[0], $uiy[1], $uiy[2]));
            error_log(sprintf("  uiz=[%.15f, %.15f, %.15f]", $uiz[0], $uiz[1], $uiz[2]));
        }

        // Rotate to actual orientation in space
        for ($i = 0; $i < $nco; $i++) {
            $xrot = $x[$i][0] * $uix[0] + $x[$i][1] * $uiy[0] + $x[$i][2] * $uiz[0];
            $yrot = $x[$i][0] * $uix[1] + $x[$i][1] * $uiy[1] + $x[$i][2] * $uiz[1];
            $zrot = $x[$i][0] * $uix[2] + $x[$i][1] * $uiy[2] + $x[$i][2] * $uiz[2];

            if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER && $i == 0) {
                error_log(sprintf("DEBUG rotateBack i=%d: input x=[%.10f, %.10f, %.10f], after rotation xrot=[%.10f, %.10f, %.10f]",
                    $i, $x[$i][0], $x[$i][1], $x[$i][2], $xrot, $yrot, $zrot));
                error_log(sprintf("  uix=[%.10f, %.10f, %.10f]", $uix[0], $uix[1], $uix[2]));
                error_log(sprintf("  uiy=[%.10f, %.10f, %.10f]", $uiy[0], $uiy[1], $uiy[2]));
                error_log(sprintf("  uiz=[%.10f, %.10f, %.10f]", $uiz[0], $uiz[1], $uiz[2]));
            }

            if (abs($xrot) + abs($yrot) + abs($zrot) >= 1e-14) {
                $pdp->neval = $i;
            }

            $x[$i][0] = $xrot;
            $x[$i][1] = $yrot;
            $x[$i][2] = $zrot;

            // For Moon, rotate to J2000 equator
            if ($ipli == SwephConstants::SEI_MOON) {
                $x[$i][1] = self::CEPS2000 * $yrot - self::SEPS2000 * $zrot;
                $x[$i][2] = self::SEPS2000 * $yrot + self::CEPS2000 * $zrot;

                if (getenv('DEBUG_MOON_ROTBACK') && $i == 0) {
                    error_log(sprintf("DEBUG MOON i=0: after ecliptic rot xrot=[%.15e, %.15e, %.15e]", $xrot, $yrot, $zrot));
                    error_log(sprintf("DEBUG MOON i=0: after J2000 eq rot x[0]=[%.15e, %.15e, %.15e]", $x[$i][0], $x[$i][1], $x[$i][2]));
                }
            }
        }

        // Copy back to coefficient arrays
        for ($i = 0; $i < $nco; $i++) {
            $pdp->segp[$i] = $x[$i][0];
            $pdp->segp[$i + $chcfy_offset] = $x[$i][1];
            $pdp->segp[$i + $chcfz_offset] = $x[$i][2];
        }

        if (getenv('DEBUG_MOON_ROTBACK') && $ipli == SwephConstants::SEI_MOON) {
            error_log(sprintf("DEBUG MOON FINAL segp[0]=[%.15e, %.15e, %.15e]",
                $pdp->segp[0], $pdp->segp[$nco], $pdp->segp[2*$nco]));
        }

        // DEBUG: Output ALL coefficients for Jupiter
        if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER) {
            error_log("DEBUG rotateBack ALL X coefficients:");
            for ($i = 0; $i < $nco; $i++) {
                error_log(sprintf("  X[%2d] = %.15f", $i, $pdp->segp[$i]));
            }
        }
    }
}
