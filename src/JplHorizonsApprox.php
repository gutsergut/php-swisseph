<?php

declare(strict_types=1);

namespace Swisseph;

use Swisseph\Data\JplHorizonsCorrections;

/**
 * JPL Horizons approximate corrections
 *
 * Applies empirical right ascension corrections to match JPL Horizons ephemeris output.
 * These corrections are specific to JPL ephemerides and activated by SEFLG_JPLHOR_APPROX flag.
 *
 * Reference: swephlib.c swi_approx_jplhor()
 *
 * The correction is applied in two steps:
 * 1. Convert position from rectangular to polar coordinates
 * 2. Add/subtract correction to longitude (RA)
 * 3. Convert back to rectangular coordinates
 *
 * Correction magnitude: ~50 milliarcseconds
 */
class JplHorizonsApprox
{
    // JPL Horizons mode constants (from swephexp.h)
    public const SEMOD_JPLHORA_1 = 1;
    public const SEMOD_JPLHORA_2 = 2;
    public const SEMOD_JPLHORA_3 = 3;
    public const SEMOD_JPLHORA_DEFAULT = self::SEMOD_JPLHORA_3;

    /**
     * Apply JPL Horizons approximate correction to position vector
     *
     * Port of swi_approx_jplhor() from swephlib.c
     *
     * @param array $x Position vector [x, y, z, dx, dy, dz] - modified in place
     * @param float $tjd Julian day (TT)
     * @param int $iflag Calculation flags
     * @param bool $backward If true, remove correction (J2000 → ICRS); if false, add correction (ICRS → J2000)
     * @param int $jplhoraModel JPL Horizons mode (SEMOD_JPLHORA_1/2/3)
     * @return void
     */
    public static function apply(
        array &$x,
        float $tjd,
        int $iflag,
        bool $backward,
        int $jplhoraModel = self::SEMOD_JPLHORA_DEFAULT
    ): void {
        // Check if JPL Horizons approximation is requested
        if (!($iflag & Constants::SEFLG_JPLHOR_APPROX)) {
            return;
        }

        // Mode 2: no correction
        if ($jplhoraModel === self::SEMOD_JPLHORA_2) {
            return;
        }

        // Calculate time offset in years from reference epoch
        $t = ($tjd - JplHorizonsCorrections::DCOR_RA_JPL_TJD0) / 365.25;

        // Get correction value via linear interpolation
        $dofs = self::getCorrection($t);

        // Convert from milliarcseconds to radians
        // Formula: mas / (1000 * 3600) → arcsec, then * DEGTORAD
        $dofs /= (1000.0 * 3600.0);
        $dofs *= Math::DEG_TO_RAD;

        // Convert position to polar coordinates (in place)
        $pol = [];
        Coordinates::cartPol($x, $pol);

        // Apply correction to longitude (right ascension)
        if ($backward) {
            $pol[0] -= $dofs;  // Remove correction
        } else {
            $pol[0] += $dofs;  // Add correction
        }

        // Convert back to rectangular coordinates
        Coordinates::polCart($pol, $x);
    }

    /**
     * Get JPL Horizons correction for given time offset
     *
     * Uses linear interpolation between table values.
     * Clamps to first/last value for dates outside table range.
     *
     * @param float $t Time offset in years from DCOR_RA_JPL_TJD0
     * @return float Correction in milliarcseconds
     */
    private static function getCorrection(float $t): float
    {
        $table = JplHorizonsCorrections::DCOR_RA_JPL;
        $nPoints = JplHorizonsCorrections::NDCOR_RA_JPL;

        // Before table range: use first value
        if ($t < 0.0) {
            return $table[0];
        }

        // After table range: use last value
        if ($t >= $nPoints - 1) {
            return $table[$nPoints - 1];
        }

        // Within table range: linear interpolation
        $t0 = (int)$t;  // Lower index
        $t1 = $t0 + 1;  // Upper index
        $fraction = $t - $t0;  // Fractional part

        // Linear interpolation: val = val0 + fraction * (val1 - val0)
        return $table[$t0] + $fraction * ($table[$t1] - $table[$t0]);
    }
}
