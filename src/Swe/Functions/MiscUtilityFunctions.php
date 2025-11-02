<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

/**
 * Miscellaneous utility functions
 *
 * Port from swephlib.c and swedate.c
 */
final class MiscUtilityFunctions
{
    /**
     * Convert double to int32 with rounding (no overflow check)
     * Port from swephlib.c:3848-3855
     *
     * Used internally in Swiss Ephemeris for converting degree values
     * to integer centiseconds.
     *
     * @param float $x Value to convert
     * @return int Rounded integer value
     */
    public static function d2l(float $x): int
    {
        if ($x >= 0) {
            return (int)($x + 0.5);
        } else {
            return -(int)(0.5 - $x);
        }
    }

    /**
     * Get day of week for Julian Day number
     * Port from swephlib.c:3859-3865
     *
     * Returns: 0 = Monday, 1 = Tuesday, ..., 6 = Sunday
     *
     * @param float $jd Julian Day number
     * @return int Day of week (0-6, Monday = 0)
     */
    public static function dayOfWeek(float $jd): int
    {
        return ((((int)floor($jd - 2433282.0 - 1.5)) % 7) + 7) % 7;
    }

    /**
     * Convert calendar date to Julian Day and validate date
     * Port from swedate.c:90-108
     *
     * This function converts date+time input {y,m,d,uttime} into Julian day number.
     * It checks that the input is a legal combination of dates.
     * For illegal dates like 32 January 1993, it returns ERR but still converts
     * the date correctly (like 1 Feb 1993).
     *
     * Be aware: we always use astronomical year numbering for years before Christ,
     * not historical year numbering.
     * - Year 0 (astronomical) = 1 BC historical
     * - Year -1 (astronomical) = 2 BC historical
     * - etc.
     *
     * @param int $year Year (astronomical numbering)
     * @param int $month Month (1-12)
     * @param int $day Day (1-31)
     * @param float $uttime Universal Time in hours (decimal)
     * @param string $calendar 'g' = Gregorian, 'j' = Julian
     * @param float &$tjd Output: Julian Day number
     * @return int OK (0) if date is valid, ERR (-1) if invalid
     */
    public static function dateConversion(
        int $year,
        int $month,
        int $day,
        float $uttime,
        string $calendar,
        float &$tjd
    ): int {
        $gregflag = Constants::SE_JUL_CAL;
        if ($calendar === 'g') {
            $gregflag = Constants::SE_GREG_CAL;
        }

        // Convert to Julian Day
        $jd = TimeFunctions::julday($year, $month, $day, $uttime, $gregflag);

        // Reverse conversion to validate
        $ryear = 0;
        $rmon = 0;
        $rday = 0;
        $rut = 0.0;
        TimeFunctions::revjul($jd, $gregflag, $ryear, $rmon, $rday, $rut);

        $tjd = $jd;

        // Check if the reversed date matches the input
        if ($rmon === $month && $rday === $day && $ryear === $year) {
            return Constants::OK;
        } else {
            return Constants::ERR;
        }
    }
}
