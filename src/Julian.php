<?php

namespace Swisseph;

/**
 * Basic Julian date conversions based on Swiss Ephemeris conventions.
 * These are deterministic utilities and a safe starting point for porting.
 */
final class Julian
{
    /**
     * Convert calendar date/time to Julian Day (UT) using proleptic Gregorian/Julian rules.
     * Matches Swiss Ephemeris swe_julday/swe_revjul behavior around gregflag.
     *
     * @param int $y year
     * @param int $m month (1-12)
     * @param int $d day (1-31)
     * @param float $ut hours in decimal (e.g., 12.5 = 12:30)
     * @param int $gregflag 1=Gregorian, 0=Julian
     */
    public static function toJulianDay(int $y, int $m, int $d, float $ut, int $gregflag = 1): float
    {
        // Algorithm from Swiss Ephemeris (swephlib.c), adapted to PHP.
        $hour = $ut;
        $A = 0;
        $B = 0;
        if ($m <= 2) {
            $y -= 1;
            $m += 12;
        }
        if ($gregflag === 1) {
            $A = intdiv($y, 100);
            $B = 2 - $A + intdiv($A, 4);
        }
        $jd = (int)(365.25 * ($y + 4716))
            + (int)(30.6001 * ($m + 1))
            + $d + $B - 1524.5 + $hour / 24.0;
        return $jd;
    }

    /**
     * Reverse: Julian Day (UT) to calendar date/time.
     *
     * @return array{y:int,m:int,d:int,ut:float}
     */
    public static function fromJulianDay(float $jd, int $gregflag = 1): array
    {
        $Z = (int) floor($jd + 0.5);
        $F = ($jd + 0.5) - $Z;
        $A = $Z;
        if ($gregflag === 1) {
            $alpha = (int) floor(($Z - 1867216.25) / 36524.25);
            $A = $Z + 1 + $alpha - (int) floor($alpha / 4);
        }
        $B = $A + 1524;
        $C = (int) floor(($B - 122.1) / 365.25);
        $D = (int) floor(365.25 * $C);
        $E = (int) floor(($B - $D) / 30.6001);
        $day = $B - $D - (int) floor(30.6001 * $E) + $F;
        $month = ($E < 14) ? ($E - 1) : ($E - 13);
        $year = ($month > 2) ? ($C - 4716) : ($C - 4715);
        $ut = ($day - floor($day)) * 24.0;
        $d = (int) floor($day);
        return ['y' => $year, 'm' => $month, 'd' => $d, 'ut' => $ut];
    }

    /**
     * Convert calendar date to Julian Day with validation.
     * Returns error code if date is invalid.
     *
     * @param int $y year
     * @param int $m month (1-12)
     * @param int $d day (1-31)
     * @param float $utime universal time in hours (decimal)
     * @param string $c calendar: 'g' or 'j' (gregorian/julian)
     * @param float &$tjd output Julian Day (reference)
     * @return int Constants::SE_OK on success, Constants::SE_ERR on error
     */
    public static function dateConversion(
        int $y,
        int $m,
        int $d,
        float $utime,
        string $c,
        float &$tjd
    ): int {
        // Validate calendar type
        $c = strtolower($c);
        if ($c !== 'g' && $c !== 'j') {
            return Constants::SE_ERR;
        }
        $gregflag = ($c === 'g') ? Constants::SE_GREG_CAL : Constants::SE_JUL_CAL;

        // Basic validation
        if ($m < 1 || $m > 12) {
            return Constants::SE_ERR;
        }
        if ($d < 1) {
            return Constants::SE_ERR;
        }

        // Validate day of month (simple check)
        $daysInMonth = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ($gregflag === Constants::SE_GREG_CAL) {
            // Gregorian leap year
            $isLeap = ($y % 4 === 0 && $y % 100 !== 0) || ($y % 400 === 0);
            if ($isLeap && $m === 2) {
                $daysInMonth[2] = 29;
            }
        } else {
            // Julian leap year (every 4 years)
            $isLeap = ($y % 4 === 0);
            if ($isLeap && $m === 2) {
                $daysInMonth[2] = 29;
            }
        }

        if ($d > $daysInMonth[$m]) {
            return Constants::SE_ERR;
        }

        // Validate time
        if ($utime < 0.0 || $utime >= 24.0) {
            return Constants::SE_ERR;
        }

        $tjd = self::toJulianDay($y, $m, $d, $utime, $gregflag);
        return Constants::SE_OK;
    }

    /**
     * Get day of week for a Julian Day.
     * Monday = 0, Tuesday = 1, ..., Sunday = 6
     *
     * @param float $jd Julian Day
     * @return int Day of week (0-6)
     */
    public static function dayOfWeek(float $jd): int
    {
        // Swiss Ephemeris formula: (int)(JD + 0.5) % 7
        // JD 0.5 (noon of JD epoch) is Monday
        // Adding 0.5 shifts to midnight, then modulo 7

        $dow = ((int)floor($jd + 0.5)) % 7;
        return $dow;
    }
}
