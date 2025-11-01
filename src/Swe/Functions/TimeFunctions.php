<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Sidereal;
use Swisseph\DeltaT;
use Swisseph\Constants;

final class TimeFunctions
{
    /**
     * Swiss Ephemeris-like swe_time_equ: returns equation of time E (in days).
     * Minimal approximation via Sidereal::equationOfTimeDays.
     * @return int 0 on success, SE_ERR on failure
     */
    public static function timeEqu(float $jd_ut, ?float &$E = null, ?string &$serr = null): int
    {
        $serr = null;
        $E = Sidereal::equationOfTimeDays($jd_ut);
        return 0;
    }

    /**
     * Convert Local Mean Time to Local Apparent Time.
     * Port of swe_lmt_to_lat() from sweph.c:7469
     * LAT = LMT + equation_of_time
     *
     * @param float $tjd_lmt Julian Day in Local Mean Time
     * @param float $geolon Geographic longitude (degrees, positive East)
     * @param float &$tjd_lat Output: Julian Day in Local Apparent Time
     * @param string|null &$serr Error message
     * @return int Constants::SE_OK on success, Constants::SE_ERR on error
     */
    public static function lmtToLat(
        float $tjd_lmt,
        float $geolon,
        ?float &$tjd_lat = null,
        ?string &$serr = null
    ): int {
        $serr = null;

        // Convert LMT to UT: UT = LMT - (geolon/360) days
        $tjd_lmt0 = $tjd_lmt - ($geolon / 360.0);

        // Get equation of time in days
        $E = 0.0;
        $retval = self::timeEqu($tjd_lmt0, $E, $serr);
        if ($retval !== Constants::SE_OK) {
            return Constants::SE_ERR;
        }

        // LAT = LMT + E
        $tjd_lat = $tjd_lmt + $E;

        return $retval;
    }

    /**
     * Convert Local Apparent Time to Local Mean Time.
     * Port of swe_lat_to_lmt() from sweph.c:7478
     * LMT = LAT - equation_of_time (with iteration for precision)
     *
     * @param float $tjd_lat Julian Day in Local Apparent Time
     * @param float $geolon Geographic longitude (degrees, positive East)
     * @param float &$tjd_lmt Output: Julian Day in Local Mean Time
     * @param string|null &$serr Error message
     * @return int Constants::SE_OK on success, Constants::SE_ERR on error
     */
    public static function latToLmt(
        float $tjd_lat,
        float $geolon,
        ?float &$tjd_lmt = null,
        ?string &$serr = null
    ): int {
        $serr = null;

        // Initial approximation: convert LAT to UT estimate
        $tjd_lmt0 = $tjd_lat - ($geolon / 360.0);

        // Get equation of time in days
        $E = 0.0;
        $retval = self::timeEqu($tjd_lmt0, $E, $serr);
        if ($retval !== Constants::SE_OK) {
            return Constants::SE_ERR;
        }

        // Iteration: refine equation of time calculation
        // C code does 2 more iterations for precision
        $retval = self::timeEqu($tjd_lmt0 - $E, $E, $serr);
        if ($retval !== Constants::SE_OK) {
            return Constants::SE_ERR;
        }

        $retval = self::timeEqu($tjd_lmt0 - $E, $E, $serr);
        if ($retval !== Constants::SE_OK) {
            return Constants::SE_ERR;
        }

        // LMT = LAT - E
        $tjd_lmt = $tjd_lat - $E;

        return $retval;
    }

    /**
     * Calculate Delta T (TT-UT1) in days for a given Julian Day (UT).
     * Port of swe_deltat_ex() from swephlib.c:2701-2710
     *
     * @param float $tjd Julian Day Number (UT)
     * @param int $iflag Calculation flags (currently unused, for future ephemeris-specific adjustments)
     * @param string|null &$serr Error message
     * @return float Delta T in days
     */
    public static function deltatEx(float $tjd, int $iflag, ?string &$serr): float
    {
        // TODO: Check for user-defined delta T override (swed.delta_t_userdef_is_set)
        // TODO: Adjust for ephemeris-specific tidal acceleration

        if ($serr !== null) {
            $serr = '';
        }

        // Use existing DeltaT class to calculate delta T in seconds
        $deltatSeconds = DeltaT::deltaTSecondsFromJd($tjd);

        // Convert seconds to days
        $deltatDays = $deltatSeconds / 86400.0;

        return $deltatDays;
    }

    /**
     * Calculate Delta T (TT-UT1) in days for a given Julian Day (UT).
     * Simplified version without iflag parameter.
     * Port of swe_deltat() from swephlib.c:2713-2716
     *
     * @param float $tjd Julian Day Number (UT)
     * @return float Delta T in days
     */
    public static function deltat(float $tjd): float
    {
        // Use SEFLG_SWIEPH as default
        $serr = null;
        return self::deltatEx($tjd, Constants::SEFLG_SWIEPH, $serr);
    }
}
