<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Swe\Eclipses\EclipseCalculator;
use Swisseph\Swe\Eclipses\EclipseUtils;

/**
 * Solar Eclipse Functions
 * Ported from swecl.c (Swiss Ephemeris C library)
 *
 * Public API functions for solar eclipse calculations.
 * These are the functions exposed as global swe_sol_eclipse_*().
 */
class SolarEclipseFunctions
{
    /**
     * Compute attributes of a solar eclipse at a given location
     * Ported from swecl.c:924-965 (swe_sol_eclipse_how)
     *
     * Calculates eclipse parameters for a specific geographic location:
     * - Eclipse type (total, annular, partial)
     * - Magnitude and obscuration
     * - Azimuth and altitude of Sun
     * - Saros series information
     *
     * @param float $tjdUt Julian day in UT
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param array $geopos Geographic position [longitude, latitude, height]
     *   - geopos[0]: longitude in degrees (east positive)
     *   - geopos[1]: latitude in degrees (north positive)
     *   - geopos[2]: height in meters above sea level
     * @param array &$attr Output: eclipse attributes [0-10]
     *   - attr[0]: fraction of solar diameter covered by moon (magnitude IMCCE)
     *   - attr[1]: ratio of lunar diameter to solar one
     *   - attr[2]: fraction of solar disc covered by moon (obscuration)
     *   - attr[3]: diameter of core shadow in km (requires eclipse_where - TBD)
     *   - attr[4]: azimuth of sun at tjd
     *   - attr[5]: true altitude of sun above horizon at tjd
     *   - attr[6]: apparent altitude of sun above horizon at tjd
     *   - attr[7]: elongation of moon in degrees
     *   - attr[8]: magnitude acc. to NASA
     *   - attr[9]: saros series number
     *   - attr[10]: saros series member number
     * @param string|null &$serr Output: error message
     * @return int Eclipse type flags:
     *   - SE_ECL_TOTAL: total eclipse
     *   - SE_ECL_ANNULAR: annular eclipse
     *   - SE_ECL_PARTIAL: partial eclipse
     *   - SE_ECL_VISIBLE: eclipse visible at location
     *   - SE_ECL_CENTRAL: central eclipse (from eclipse_where)
     *   - SE_ECL_NONCENTRAL: non-central eclipse (from eclipse_where)
     *   - 0: no eclipse visible at location
     *   - ERR: error occurred
     */
    public static function how(
        float $tjdUt,
        int $ifl,
        array $geopos,
        array &$attr,
        ?string &$serr = null
    ): int {
        // Initialize attr array
        for ($i = 0; $i <= 10; $i++) {
            $attr[$i] = 0.0;
        }

        // Validate geographic altitude
        if ($geopos[2] < EclipseUtils::SEI_ECL_GEOALT_MIN ||
            $geopos[2] > EclipseUtils::SEI_ECL_GEOALT_MAX) {
            if ($serr !== null) {
                $serr = sprintf(
                    "location for eclipses must be between %.0f and %.0f m above sea",
                    EclipseUtils::SEI_ECL_GEOALT_MIN,
                    EclipseUtils::SEI_ECL_GEOALT_MAX
                );
            }
            return Constants::ERR;
        }

        // Mask to ephemeris flags only
        $ifl &= Constants::SEFLG_EPHMASK;

        // Set tidal acceleration (swi_set_tid_acc in C)
        // In PHP implementation, this is handled internally by swe_deltat_ex
        // So we skip this call

        // Calculate eclipse attributes
        $retflag = EclipseCalculator::eclipseHow(
            $tjdUt,
            Constants::SE_SUN,
            null,  // starname = NULL for Sun
            $ifl,
            $geopos[0],  // longitude
            $geopos[1],  // latitude
            $geopos[2],  // height
            $attr,
            $serr
        );

        if ($retflag === Constants::ERR) {
            return $retflag;
        }

        // TODO: Call eclipse_where() to get:
        // - attr[3]: diameter of core shadow
        // - SE_ECL_CENTRAL or SE_ECL_NONCENTRAL flags
        // For now, we skip this as eclipse_where() is not yet implemented
        // This means attr[3] remains 0 and CENTRAL/NONCENTRAL flags are not set

        // Note: In C code, after eclipse_where(), there's additional calculation
        // to override attr[4-6] with fresh swe_azalt() call.
        // Our eclipseHow() already does this, so no need to repeat.

        // Check if sun is below horizon -> no eclipse visible
        if ($attr[6] <= 0) {
            // Sun below horizon (apparent altitude <= 0)
            $retflag = 0;
        }

        // Clear attributes if no eclipse
        if ($retflag === 0) {
            for ($i = 0; $i <= 3; $i++) {
                $attr[$i] = 0.0;
            }
            for ($i = 8; $i <= 10; $i++) {
                $attr[$i] = 0.0;
            }
        }

        return $retflag;
    }
}
