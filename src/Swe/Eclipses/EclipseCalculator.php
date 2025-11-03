<?php

declare(strict_types=1);

namespace Swisseph\Swe\Eclipses;

use Swisseph\Constants;

/**
 * Eclipse calculation functions
 * Ported from swecl.c (Swiss Ephemeris C library)
 *
 * Internal helper functions for eclipse computations.
 * NOT for direct public use - wrapped by SolarEclipseFunctions/LunarEclipseFunctions.
 */
class EclipseCalculator
{
    /**
     * Calculate position of planet or fixed star
     * Ported from swecl.c:888-897 (calc_planet_star)
     *
     * Simple wrapper that calls either swe_calc() or swe_fixstar()
     * depending on whether a star name is provided.
     *
     * @param float $tjdEt Julian day in ET/TT
     * @param int $ipl Planet number (SE_SUN, SE_MOON, etc.)
     * @param string|null $starname Star name for swe_fixstar() (null for planets)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param array &$x Output: position array [longitude, latitude, distance, ...]
     * @param string|null &$serr Output: error message
     * @return int OK or ERR
     */
    public static function calcPlanetStar(
        float $tjdEt,
        int $ipl,
        ?string $starname,
        int $iflag,
        array &$x,
        ?string &$serr = null
    ): int {
        $retc = Constants::OK;

        // If starname is provided and not empty, calculate fixed star
        if ($starname !== null && $starname !== '') {
            $retc = \swe_fixstar($starname, $tjdEt, $iflag, $x, $serr);
        } else {
            // Otherwise calculate planet
            $retc = \swe_calc($tjdEt, $ipl, $iflag, $x, $serr);
        }

        return $retc;
    }
}
