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
            return Constants::SE_ERR;
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

        if ($retflag === Constants::SE_ERR) {
            return $retflag;
        }

        // Call eclipse_where() to get:
        // - attr[3]: diameter of core shadow
        // - SE_ECL_CENTRAL or SE_ECL_NONCENTRAL flags
        $geopos2 = array_fill(0, 20, 0.0);
        $dcoreWhere = array_fill(0, 10, 0.0);
        $retflag2 = EclipseCalculator::eclipseWhere(
            $tjdUt,
            Constants::SE_SUN,
            null,
            $ifl,
            $geopos2,
            $dcoreWhere,
            $serr
        );
        if ($retflag2 === Constants::SE_ERR) {
            return $retflag2;
        }

        // Add CENTRAL/NONCENTRAL flags and set core shadow diameter
        if ($retflag) {
            $retflag |= ($retflag2 & (Constants::SE_ECL_CENTRAL | Constants::SE_ECL_NONCENTRAL));
        }
        $attr[3] = $dcoreWhere[0];

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

    /**
     * Find next solar eclipse at a given geographic location
     * Ported from swecl.c:2019-2039 (swe_sol_eclipse_when_loc)
     *
     * Searches for the next (or previous) solar eclipse visible from a specific location.
     * Returns eclipse type, contact times, and attributes.
     *
     * @param float $tjdStart Start time for search (JD UT)
     * @param int $ifl Ephemeris flags (SEFLG_SWIEPH, SEFLG_JPLEPH, etc.)
     * @param array $geopos Geographic position [longitude, latitude, height]
     *   - geopos[0]: longitude in degrees (east positive)
     *   - geopos[1]: latitude in degrees (north positive)
     *   - geopos[2]: height in meters above sea level
     * @param array &$tret Output: eclipse times [0-6]
     *   - tret[0]: time of maximum eclipse (UT)
     *   - tret[1]: time of first contact (UT)
     *   - tret[2]: time of second contact (totality begins, UT) - 0 if partial
     *   - tret[3]: time of third contact (totality ends, UT) - 0 if partial
     *   - tret[4]: time of fourth contact (UT)
     *   - tret[5]: time of sunrise during eclipse (UT) - 0 if not applicable
     *   - tret[6]: time of sunset during eclipse (UT) - 0 if not applicable
     * @param array &$attr Output: eclipse attributes [0-10] (same as how())
     * @param int $backward 1 for backward search, 0 for forward
     * @param string|null &$serr Output: error message
     * @return int Eclipse type flags + visibility flags:
     *   - SE_ECL_TOTAL: total eclipse
     *   - SE_ECL_ANNULAR: annular eclipse
     *   - SE_ECL_PARTIAL: partial eclipse
     *   - SE_ECL_VISIBLE: at least one phase visible
     *   - SE_ECL_MAX_VISIBLE: maximum phase visible
     *   - SE_ECL_1ST_VISIBLE: first contact visible
     *   - SE_ECL_2ND_VISIBLE: second contact visible
     *   - SE_ECL_3RD_VISIBLE: third contact visible
     *   - SE_ECL_4TH_VISIBLE: fourth contact visible
     *   - SE_ECL_CENTRAL: eclipse is central (requires eclipse_where - TODO)
     *   - SE_ECL_NONCENTRAL: eclipse is non-central (requires eclipse_where - TODO)
     *   Returns 0 if no eclipse found, SE_ERR on error
     */
    public static function whenLoc(
        float $tjdStart,
        int $ifl,
        array $geopos,
        array &$tret,
        array &$attr,
        int $backward = 0,
        ?string &$serr = null
    ): int {
        // Validate altitude (from swecl.c:2024-2028)
        if ($geopos[2] < EclipseUtils::SEI_ECL_GEOALT_MIN || $geopos[2] > EclipseUtils::SEI_ECL_GEOALT_MAX) {
            if ($serr !== null) {
                $serr = sprintf(
                    "location for eclipses must be between %.0f and %.0f m above sea",
                    EclipseUtils::SEI_ECL_GEOALT_MIN,
                    EclipseUtils::SEI_ECL_GEOALT_MAX
                );
            }
            return Constants::SE_ERR;
        }

        $ifl &= Constants::SEFLG_EPHMASK;
        // Note: C code calls swi_set_tid_acc() here - we skip as not ported yet

        // Call internal eclipse_when_loc function (from swecl.c:2032)
        $retflag = EclipseCalculator::eclipseWhenLoc(
            $tjdStart,
            $ifl,
            $geopos,
            $tret,
            $attr,
            $backward,
            $serr
        );

        if ($retflag <= 0) {
            return $retflag;
        }

        // Get diameter of core shadow using eclipse_where()
        // From swecl.c:2033-2036
        $geopos2 = array_fill(0, 20, 0.0);
        $dcoreWhere = array_fill(0, 10, 0.0);
        $retflag2 = EclipseCalculator::eclipseWhere(
            $tret[0],
            Constants::SE_SUN,
            null,
            $ifl,
            $geopos2,
            $dcoreWhere,
            $serr
        );
        if ($retflag2 === Constants::SE_ERR) {
            return $retflag2;
        }
        $retflag |= ($retflag2 & Constants::SE_ECL_NONCENTRAL);
        $attr[3] = $dcoreWhere[0];

        return $retflag;
    }
}
