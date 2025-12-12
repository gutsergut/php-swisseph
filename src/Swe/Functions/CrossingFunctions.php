<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use function swe_calc;
use function swe_calc_ut;
use function swe_degnorm;
use function swe_difdeg2n;
use function swe_get_planet_name;

/**
 * Crossing Functions
 * Ported from sweph.c:8360-8670
 *
 * Functions to find when celestial bodies cross specific longitudes:
 * - swe_solcross: Sun crossing a longitude (ET)
 * - swe_solcross_ut: Sun crossing a longitude (UT)
 * - swe_mooncross: Moon crossing a longitude (ET)
 * - swe_mooncross_ut: Moon crossing a longitude (UT)
 * - swe_mooncross_node: Moon crossing node (zero latitude, ET)
 * - swe_mooncross_node_ut: Moon crossing node (zero latitude, UT)
 * - swe_helio_cross: Planet heliocentric crossing (ET)
 * - swe_helio_cross_ut: Planet heliocentric crossing (UT)
 *
 * WITHOUT SIMPLIFICATIONS - complete C port
 */
class CrossingFunctions
{
    /** Precision for crossing detection: 1 milliarcsecond */
    private const CROSS_PRECISION = 1.0 / 3600000.0;

    /**
     * Compute Sun's crossing over some longitude (Ephemeris Time)
     *
     * Ported from sweph.c:8375-8405
     *
     * Finds the next time when the Sun crosses a specified ecliptic longitude.
     * Uses iterative refinement with Newton's method.
     *
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jdEt Starting Julian day (Ephemeris Time)
     * @param int $flag Calculation flags:
     *   - SEFLG_HELCTR: 0=geocentric Sun, 1=heliocentric Earth
     *   - SEFLG_TRUEPOS: 0=apparent, 1=true positions
     *   - SEFLG_NONUT: 0=with nutation, 1=without nutation
     * @param string|null &$serr Error message
     * @return float Julian day of crossing (ET), or < $jdEt on error
     */
    public static function solcross(
        float $x2cross,
        float $jdEt,
        int $flag,
        ?string &$serr = null
    ): float {
        $x = array_fill(0, 6, 0.0);
        $ipl = Constants::SE_SUN;

        // Add speed flag for derivatives
        $flag |= Constants::SEFLG_SPEED;

        // Compute Sun at start date
        if (swe_calc($jdEt, $ipl, $flag, $x, $serr) < 0) {
            return $jdEt - 1.0;
        }

        // Estimate crossing date using mean solar speed
        $xlp = 360.0 / 365.24;  // mean solar speed in deg/day
        $dist = swe_degnorm($x2cross - $x[0]);
        $jd = $jdEt + $dist / $xlp;

        // Iterative refinement using Newton's method
        while (true) {
            if (swe_calc($jd, $ipl, $flag, $x, $serr) < 0) {
                return $jdEt - 1.0;
            }

            $dist = swe_difdeg2n($x2cross, $x[0]);
            $jd += $dist / $x[3];  // x[3] = longitude speed

            if (abs($dist) < self::CROSS_PRECISION) {
                break;
            }
        }

        return $jd;
    }

    /**
     * Compute Sun's crossing over some longitude (Universal Time)
     *
     * Ported from sweph.c:8409-8439
     *
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jdUt Starting Julian day (Universal Time)
     * @param int $flag Calculation flags (same as solcross)
     * @param string|null &$serr Error message
     * @return float Julian day of crossing (UT), or < $jdUt on error
     */
    public static function solcrossUt(
        float $x2cross,
        float $jdUt,
        int $flag,
        ?string &$serr = null
    ): float {
        $x = array_fill(0, 6, 0.0);
        $ipl = Constants::SE_SUN;

        $flag |= Constants::SEFLG_SPEED;

        if (swe_calc_ut($jdUt, $ipl, $flag, $x, $serr) < 0) {
            return $jdUt - 1.0;
        }

        $xlp = 360.0 / 365.24;
        $dist = swe_degnorm($x2cross - $x[0]);
        $jd = $jdUt + $dist / $xlp;

        while (true) {
            if (swe_calc_ut($jd, $ipl, $flag, $x, $serr) < 0) {
                return $jdUt - 1.0;
            }

            $dist = swe_difdeg2n($x2cross, $x[0]);
            $jd += $dist / $x[3];

            if (abs($dist) < self::CROSS_PRECISION) {
                break;
            }
        }

        return $jd;
    }

    /**
     * Compute Moon's crossing over some longitude (Ephemeris Time)
     *
     * Ported from sweph.c:8443-8463
     *
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jdEt Starting Julian day (Ephemeris Time)
     * @param int $flag Calculation flags:
     *   - SEFLG_TRUEPOS: 0=apparent, 1=true positions
     *   - SEFLG_NONUT: 0=with nutation, 1=without nutation
     * @param string|null &$serr Error message
     * @return float Julian day of crossing (ET), or < $jdEt on error
     */
    public static function mooncross(
        float $x2cross,
        float $jdEt,
        int $flag,
        ?string &$serr = null
    ): float {
        $x = array_fill(0, 6, 0.0);
        $ipl = Constants::SE_MOON;

        $flag |= Constants::SEFLG_SPEED;

        if (swe_calc($jdEt, $ipl, $flag, $x, $serr) < 0) {
            return $jdEt - 1.0;
        }

        // Estimate crossing date using mean lunar speed
        $xlp = 360.0 / 27.32;  // mean lunar speed in deg/day
        $dist = swe_degnorm($x2cross - $x[0]);
        $jd = $jdEt + $dist / $xlp;

        while (true) {
            if (swe_calc($jd, $ipl, $flag, $x, $serr) < 0) {
                return $jdEt - 1.0;
            }

            $dist = swe_difdeg2n($x2cross, $x[0]);
            $jd += $dist / $x[3];

            if (abs($dist) < self::CROSS_PRECISION) {
                break;
            }
        }

        return $jd;
    }

    /**
     * Compute Moon's crossing over some longitude (Universal Time)
     *
     * Ported from sweph.c:8467-8500
     *
     * If sidereal is chosen, default mode is Fagan/Bradley.
     * For different ayanamshas, call swe_set_sid_mode() first.
     *
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jdUt Starting Julian day (Universal Time)
     * @param int $flag Calculation flags:
     *   - SEFLG_TRUEPOS: 0=apparent, 1=true positions
     *   - SEFLG_NONUT: 0=with nutation, 1=without nutation
     *   - SEFLG_SIDEREAL: 0=tropical, 1=sidereal
     * @param string|null &$serr Error message
     * @return float Julian day of crossing (UT), or < $jdUt on error
     */
    public static function mooncrossUt(
        float $x2cross,
        float $jdUt,
        int $flag,
        ?string &$serr = null
    ): float {
        $x = array_fill(0, 6, 0.0);
        $ipl = Constants::SE_MOON;

        $flag |= Constants::SEFLG_SPEED;

        if (swe_calc_ut($jdUt, $ipl, $flag, $x, $serr) < 0) {
            return $jdUt - 1.0;
        }

        $xlp = 360.0 / 27.32;
        $dist = swe_degnorm($x2cross - $x[0]);
        $jd = $jdUt + $dist / $xlp;

        while (true) {
            if (swe_calc_ut($jd, $ipl, $flag, $x, $serr) < 0) {
                return $jdUt - 1.0;
            }

            $dist = swe_difdeg2n($x2cross, $x[0]);
            $jd += $dist / $x[3];

            if (abs($dist) < self::CROSS_PRECISION) {
                break;
            }
        }

        return $jd;
    }

    /**
     * Compute next Moon crossing over node (Ephemeris Time)
     *
     * Ported from sweph.c:8504-8542
     *
     * Finds when Moon crosses its orbital node (zero ecliptic latitude).
     * Returns the longitude and latitude at crossing.
     *
     * @param float $jdEt Starting Julian day (Ephemeris Time)
     * @param int $flag Calculation flags
     * @param float &$xlon Output: longitude at node crossing (degrees)
     * @param float &$xlat Output: latitude at node crossing (degrees, ~0)
     * @param string|null &$serr Error message
     * @return float Julian day of node crossing (ET), or < $jdEt on error
     */
    public static function mooncrossNode(
        float $jdEt,
        int $flag,
        float &$xlon,
        float &$xlat,
        ?string &$serr = null
    ): float {
        $x = array_fill(0, 6, 0.0);
        $ipl = Constants::SE_MOON;

        $flag |= Constants::SEFLG_SPEED;

        if (swe_calc($jdEt, $ipl, $flag, $x, $serr) < 0) {
            return $jdEt - 1.0;
        }

        $xlat_start = $x[1];
        $jd = $jdEt + 1.0;

        // Find sign change in latitude
        while (true) {
            if (swe_calc($jd, $ipl, $flag, $x, $serr) < 0) {
                return $jdEt - 1.0;
            }

            // Check for latitude sign change (node crossing)
            if (($x[1] >= 0.0 && $xlat_start < 0.0) || ($x[1] < 0.0 && $xlat_start >= 0.0)) {
                break;
            }

            $jd += 1.0;
        }

        // Refine to exact crossing using Newton's method
        $dist = $x[1];
        while (true) {
            $jd -= $dist / $x[4];  // x[4] = latitude speed

            if (swe_calc($jd, $ipl, $flag, $x, $serr) < 0) {
                return $jdEt - 1.0;
            }

            $dist = $x[1];

            if (abs($dist) < self::CROSS_PRECISION) {
                $xlon = $x[0];
                $xlat = $x[1];
                break;
            }
        }

        return $jd;
    }

    /**
     * Compute next Moon crossing over node (Universal Time)
     *
     * Ported from sweph.c:8546-8584
     *
     * @param float $jdUt Starting Julian day (Universal Time)
     * @param int $flag Calculation flags
     * @param float &$xlon Output: longitude at node crossing (degrees)
     * @param float &$xlat Output: latitude at node crossing (degrees, ~0)
     * @param string|null &$serr Error message
     * @return float Julian day of node crossing (UT), or < $jdUt on error
     */
    public static function mooncrossNodeUt(
        float $jdUt,
        int $flag,
        float &$xlon,
        float &$xlat,
        ?string &$serr = null
    ): float {
        $x = array_fill(0, 6, 0.0);
        $ipl = Constants::SE_MOON;

        $flag |= Constants::SEFLG_SPEED;

        if (swe_calc_ut($jdUt, $ipl, $flag, $x, $serr) < 0) {
            return $jdUt - 1.0;
        }

        $xlat_start = $x[1];
        $jd = $jdUt + 1.0;

        while (true) {
            if (swe_calc_ut($jd, $ipl, $flag, $x, $serr) < 0) {
                return $jdUt - 1.0;
            }

            if (($x[1] >= 0.0 && $xlat_start < 0.0) || ($x[1] < 0.0 && $xlat_start >= 0.0)) {
                break;
            }

            $jd += 1.0;
        }

        $dist = $x[1];
        while (true) {
            $jd -= $dist / $x[4];

            if (swe_calc_ut($jd, $ipl, $flag, $x, $serr) < 0) {
                return $jdUt - 1.0;
            }

            $dist = $x[1];

            if (abs($dist) < self::CROSS_PRECISION) {
                $xlon = $x[0];
                $xlat = $x[1];
                break;
            }
        }

        return $jd;
    }

    /**
     * Compute planet's heliocentric crossing over longitude (Ephemeris Time)
     *
     * Ported from sweph.c:8588-8628
     *
     * Finds when a planet crosses a specified heliocentric ecliptic longitude.
     * Can search forward (dir >= 0) or backward (dir < 0).
     *
     * Note: Only for rough calculations (e.g., house entry/exit times).
     * Not valid for Sun, Moon, nodes, or apsides.
     *
     * @param int $ipl Planet number (SE_MERCURY through SE_PLUTO, SE_CHIRON, etc.)
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jdEt Starting Julian day (Ephemeris Time)
     * @param int $iflag Calculation flags (HELCTR automatically added)
     * @param int $dir Direction: >=0 for forward, <0 for backward
     * @param float &$jdCross Output: Julian day of crossing (ET)
     * @param string|null &$serr Error message
     * @return int OK (0) on success, ERR (-1) on error
     */
    public static function helioCross(
        int $ipl,
        float $x2cross,
        float $jdEt,
        int $iflag,
        int $dir,
        float &$jdCross,
        ?string &$serr = null
    ): int {
        $x = array_fill(0, 6, 0.0);
        $flag = $iflag | Constants::SEFLG_SPEED | Constants::SEFLG_HELCTR;

        // Validate planet (heliocentric not valid for Sun, Moon, nodes, apsides)
        if ($ipl === Constants::SE_SUN
            || $ipl === Constants::SE_MOON
            || ($ipl >= Constants::SE_MEAN_NODE && $ipl <= Constants::SE_OSCU_APOG)
            || ($ipl >= Constants::SE_INTP_APOG && $ipl < Constants::SE_NPLANETS)
        ) {
            $snam = swe_get_planet_name($ipl);
            if ($serr !== null) {
                $serr = sprintf("swe_helio_cross: not possible for object %d = %s", $ipl, $snam);
            }
            return Constants::SE_ERR;
        }

        if (swe_calc($jdEt, $ipl, $flag, $x, $serr) < 0) {
            return Constants::SE_ERR;
        }

        $xlp = $x[3];  // longitude speed

        // Use mean speed for Chiron (orbit is chaotic)
        if ($ipl === Constants::SE_CHIRON) {
            $xlp = 0.01971;  // mean speed in deg/day
        }

        $dist = swe_degnorm($x2cross - $x[0]);

        if ($dir >= 0) {
            // Forward search
            $jd = $jdEt + $dist / $xlp;
        } else {
            // Backward search
            $dist = 360.0 - $dist;
            $jd = $jdEt - $dist / $xlp;
        }

        // Iterative refinement
        while (true) {
            if (swe_calc($jd, $ipl, $flag, $x, $serr) < 0) {
                return Constants::SE_ERR;
            }

            $dist = swe_difdeg2n($x2cross, $x[0]);
            $jd += $dist / $x[3];

            if (abs($dist) < self::CROSS_PRECISION) {
                break;
            }
        }

        $jdCross = $jd;
        return Constants::SE_OK;
    }

    /**
     * Compute planet's heliocentric crossing over longitude (Universal Time)
     *
     * Ported from sweph.c:8632-8670
     *
     * @param int $ipl Planet number
     * @param float $x2cross Longitude to cross (degrees, 0-360)
     * @param float $jdUt Starting Julian day (Universal Time)
     * @param int $iflag Calculation flags
     * @param int $dir Direction: >=0 for forward, <0 for backward
     * @param float &$jdCross Output: Julian day of crossing (UT)
     * @param string|null &$serr Error message
     * @return int OK (0) on success, ERR (-1) on error
     */
    public static function helioCrossUt(
        int $ipl,
        float $x2cross,
        float $jdUt,
        int $iflag,
        int $dir,
        float &$jdCross,
        ?string &$serr = null
    ): int {
        $x = array_fill(0, 6, 0.0);
        $flag = $iflag | Constants::SEFLG_SPEED | Constants::SEFLG_HELCTR;

        if ($ipl === Constants::SE_SUN
            || $ipl === Constants::SE_MOON
            || ($ipl >= Constants::SE_MEAN_NODE && $ipl <= Constants::SE_OSCU_APOG)
            || ($ipl >= Constants::SE_INTP_APOG && $ipl < Constants::SE_NPLANETS)
        ) {
            $snam = swe_get_planet_name($ipl);
            if ($serr !== null) {
                $serr = sprintf("swe_helio_cross: not possible for object %d = %s", $ipl, $snam);
            }
            return Constants::SE_ERR;
        }

        if (swe_calc_ut($jdUt, $ipl, $flag, $x, $serr) < 0) {
            return Constants::SE_ERR;
        }

        $xlp = $x[3];

        if ($ipl === Constants::SE_CHIRON) {
            $xlp = 0.01971;
        }

        $dist = swe_degnorm($x2cross - $x[0]);

        if ($dir >= 0) {
            $jd = $jdUt + $dist / $xlp;
        } else {
            $dist = 360.0 - $dist;
            $jd = $jdUt - $dist / $xlp;
        }

        while (true) {
            if (swe_calc_ut($jd, $ipl, $flag, $x, $serr) < 0) {
                return Constants::SE_ERR;
            }

            $dist = swe_difdeg2n($x2cross, $x[0]);
            $jd += $dist / $x[3];

            if (abs($dist) < self::CROSS_PRECISION) {
                break;
            }
        }

        $jdCross = $jd;
        return Constants::SE_OK;
    }
}
