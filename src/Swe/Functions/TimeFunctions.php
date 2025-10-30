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
        $tjd_ut = $tjd_lmt - ($geolon / 360.0);

        // Get equation of time in days
        $E = 0.0;
        $result = self::timeEqu($tjd_ut, $E, $serr);
        if ($result !== Constants::SE_OK) {
            return Constants::SE_ERR;
        }

        // LAT = LMT + E
        $tjd_lat = $tjd_lmt + $E;

        return Constants::SE_OK;
    }

    /**
     * Convert Local Apparent Time to Local Mean Time.
     * LMT = LAT - equation_of_time
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

        // For approximation: use LAT as UT estimate
        // More precise: iterate, but for now simple version
        $tjd_ut = $tjd_lat - ($geolon / 360.0);

        // Get equation of time in days
        $E = 0.0;
        $result = self::timeEqu($tjd_ut, $E, $serr);
        if ($result !== Constants::SE_OK) {
            return Constants::SE_ERR;
        }

        // LMT = LAT - E
        $tjd_lmt = $tjd_lat - $E;

        return Constants::SE_OK;
    }
}
