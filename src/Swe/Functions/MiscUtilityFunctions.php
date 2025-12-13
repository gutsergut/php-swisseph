<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Error;
use Swisseph\Julian;
use Swisseph\State;

/**
 * Miscellaneous utility functions
 *
 * Port from swephlib.c and swedate.c
 */
final class MiscUtilityFunctions
{
    /** Whether user-defined Delta-T is set */
    private static bool $deltaTUserDefined = false;

    /** User-defined Delta-T value in days */
    private static float $deltaTValue = 0.0;
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
        // Validate calendar parameter
        if ($calendar !== 'g' && $calendar !== 'j') {
            return Constants::ERR;
        }

        $gregflag = Constants::SE_JUL_CAL;
        if ($calendar === 'g') {
            $gregflag = Constants::SE_GREG_CAL;
        }

        // Convert to Julian Day
        $jd = Julian::toJulianDay($year, $month, $day, $uttime, $gregflag);

        // Reverse conversion to validate
        $result = Julian::fromJulianDay($jd, $gregflag);
        $ryear = $result['y'];
        $rmon = $result['m'];
        $rday = $result['d'];
        $rut = $result['ut'];

        $tjd = $jd;

        // Check if the reversed date matches the input
        if ($rmon === $month && $rday === $day && $ryear === $year) {
            return Constants::OK;
        } else {
            return Constants::ERR;
        }
    }

    /**
     * Get current tidal acceleration value
     * Port from swephlib.c:3154-3158
     *
     * Returns the current tidal acceleration of the Moon in arcsec/cy^2.
     * Default value is SE_TIDAL_DEFAULT (-25.80).
     *
     * @return float Tidal acceleration in arcsec/cy^2
     */
    public static function getTidAcc(): float
    {
        return State::getTidAcc();
    }

    /**
     * Set tidal acceleration of the Moon
     * Port from swephlib.c:3165-3174
     *
     * Sets the tidal acceleration value used in lunar calculations.
     * Can be either:
     * - A specific value in arcsec/cy^2 (e.g., -25.8)
     * - SE_TIDAL_AUTOMATIC (999999) to use automatic ephemeris-specific value
     *
     * @param float $t_acc Tidal acceleration value or SE_TIDAL_AUTOMATIC
     * @return void
     */
    public static function setTidAcc(float $t_acc): void
    {
        if (abs($t_acc - 999999.0) < 0.0001) { // SE_TIDAL_AUTOMATIC
            State::setTidAcc(-25.80); // SE_TIDAL_DEFAULT (DE431)
        } else {
            State::setTidAcc($t_acc);
        }
    }

    /**
     * Set user-defined Delta-T value
     * Port from swephlib.c:3176-3184
     *
     * Overrides automatic Delta-T calculation with a user-defined value.
     * Pass SE_DELTAT_AUTOMATIC (-1e-10) to restore automatic calculation.
     *
     * @param float $dt Delta-T value in days, or SE_DELTAT_AUTOMATIC for automatic
     * @return void
     */
    public static function setDeltaTUserdef(float $dt): void
    {
        if (abs($dt - (-1e-10)) < 1e-11) { // SE_DELTAT_AUTOMATIC
            self::$deltaTUserDefined = false;
            self::$deltaTValue = 0.0;
        } else {
            self::$deltaTUserDefined = true;
            self::$deltaTValue = $dt;
        }
    }
}
