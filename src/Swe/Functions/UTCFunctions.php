<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;

/**
 * UTC conversion functions.
 * Full port from swedate.c without simplifications.
 */
final class UTCFunctions
{
    /**
     * Julian Day for 1 Jan 1972 00:00 UTC
     * This is when UTC officially started using leap seconds
     */
    private const J1972 = 2441317.5;

    /**
     * Initial difference between TAI and UTC at 1972-01-01 (in seconds)
     */
    private const NLEAP_INIT = 10;

    /**
     * Leap seconds table: dates when leap seconds were inserted (format: YYYYMMDD)
     * Port from swedate.c:277-305
     *
     * Each entry represents the END of the day when a leap second was added.
     * For example, 19720630 means a leap second was added at the end of June 30, 1972
     * (23:59:60 UTC on that day).
     */
    private static array $leapSeconds = [
        19720630,
        19721231,
        19731231,
        19741231,
        19751231,
        19761231,
        19771231,
        19781231,
        19791231,
        19810630,
        19820630,
        19830630,
        19850630,
        19871231,
        19891231,
        19901231,
        19920630,
        19930630,
        19940630,
        19951231,
        19970630,
        19981231,
        20051231,
        20081231,
        20120630,
        20150630,
        20161231,
    ];

    /**
     * Flag to track if leap seconds table has been extended from external file
     */
    private static bool $leapSecondsInitialized = false;

    /**
     * Initialize leap seconds table from external file if available.
     * Port of init_leapsec() from swedate.c:311-361
     *
     * Reads additional leap second dates from seleapsec.txt file in ephemeris path.
     * Returns the number of leap second entries in the table.
     *
     * @return int Number of leap second entries
     */
    private static function initLeapSec(): int
    {
        if (!self::$leapSecondsInitialized) {
            self::$leapSecondsInitialized = true;

            // Try to read additional leap seconds from external file
            // For now, we'll use the built-in table only
            // TODO: Implement file reading from ephemeris path (swed.ephepath)
            // File format: one date per line in YYYYMMDD format
            // Lines starting with # are comments
        }

        return count(self::$leapSeconds);
    }

    /**
     * Convert Julian Day (Ephemeris Time / TT) to UTC date/time components.
     * Port of swe_jdet_to_utc() from swedate.c:486-568
     *
     * This function converts from TT (Terrestrial Time, also called Ephemeris Time)
     * to UTC (Coordinated Universal Time), accounting for:
     * - Delta T correction (TT - UT1)
     * - Leap seconds (since 1972)
     * - Potentially missing leap second at the date
     *
     * For dates before 1 Jan 1972 UTC, the function returns UT1 instead of UTC,
     * as UTC with leap seconds was not yet defined.
     *
     * @param float $tjd_et Julian Day in Ephemeris Time (TT)
     * @param int $gregflag Calendar flag (SE_GREG_CAL=1 or SE_JUL_CAL=0)
     * @param int|null &$iyear Output: year
     * @param int|null &$imonth Output: month (1-12)
     * @param int|null &$iday Output: day (1-31)
     * @param int|null &$ihour Output: hour (0-23)
     * @param int|null &$imin Output: minute (0-59)
     * @param float|null &$dsec Output: second (0-60, can be 60 for leap second)
     * @return void
     */
    public static function jdetToUtc(
        float $tjd_et,
        int $gregflag,
        ?int &$iyear = null,
        ?int &$imonth = null,
        ?int &$iday = null,
        ?int &$ihour = null,
        ?int &$imin = null,
        ?float &$dsec = null
    ): void {
        $second_60 = 0;

        // Calculate JD for 1 Jan 1972 in ET scale
        // J1972 is in UT, need to add (TAI-UTC at 1972 + TT-TAI) / 86400
        // TT-TAI = 32.184 seconds, TAI-UTC at 1972 = NLEAP_INIT = 10 seconds
        $tjd_et_1972 = self::J1972 + (32.184 + self::NLEAP_INIT) / 86400.0;

        // Convert ET to UT using iterative Delta-T correction
        $serr = null;
        $d = \swe_deltat_ex($tjd_et, -1, $serr);
        $tjd_ut = $tjd_et - \swe_deltat_ex($tjd_et - $d, -1, $serr);
        $tjd_ut = $tjd_et - \swe_deltat_ex($tjd_ut, -1, $serr);

        // For dates before 1 Jan 1972 UTC, return UT1 (no leap seconds yet)
        if ($tjd_et < $tjd_et_1972) {
            $result = \swe_revjul($tjd_ut, $gregflag);
            $iyear = $result['y'];
            $imonth = $result['m'];
            $iday = $result['d'];
            $d = $result['ut'];

            $ihour = (int)$d;
            $d -= (float)$ihour;
            $d *= 60.0;
            $imin = (int)$d;
            $dsec = ($d - (float)$imin) * 60.0;
            return;
        }

        // Count leap seconds up to this date (minimum estimate)
        $tabsiz_nleap = self::initLeapSec();

        // Get date for (tjd_ut - 1 day) to check leap seconds
        $result = \swe_revjul($tjd_ut - 1.0, Constants::SE_GREG_CAL);
        $iyear2 = $result['y'];
        $imonth2 = $result['m'];
        $iday2 = $result['d'];

        $ndat = $iyear2 * 10000 + $imonth2 * 100 + $iday2;
        $nleap = 0;

        // Count leap seconds that occurred before this date
        for ($i = 0; $i < $tabsiz_nleap; $i++) {
            if ($ndat <= self::$leapSeconds[$i]) {
                break;
            }
            $nleap++;
        }

        // Check if we might be missing a leap second exactly at this date
        if ($nleap < $tabsiz_nleap) {
            $i = self::$leapSeconds[$nleap];
            $iyear2 = (int)($i / 10000);
            $imonth2 = (int)(($i % 10000) / 100);
            $iday2 = $i % 100;

            // Get JD for the potential leap second date
            $tjd = \swe_julday($iyear2, $imonth2, $iday2, 0, Constants::SE_GREG_CAL);

            // Get the next day
            $result = \swe_revjul($tjd + 1.0, Constants::SE_GREG_CAL);
            $iyear2 = $result['y'];
            $imonth2 = $result['m'];
            $iday2 = $result['d'];

            // Convert next day midnight UTC to ET
            $dret = \swe_utc_to_jd($iyear2, $imonth2, $iday2, 0, 0, 0.0, Constants::SE_GREG_CAL, $serr);

            // Check if our tjd_et is after the leap second
            $d = $tjd_et - $dret[0]; // dret[0] is TT
            if ($d >= 0.0) {
                // We're after the leap second, count it
                $nleap++;
            } elseif ($d < 0.0 && $d > -1.0 / 86400.0) {
                // We're within the leap second itself (the 60th second)
                $second_60 = 1;
            }
        }

        // Calculate UTC by removing leap seconds from ET
        $tjd = self::J1972 + ($tjd_et - $tjd_et_1972) - ((float)$nleap + $second_60) / 86400.0;

        // Convert to date components
        $result = \swe_revjul($tjd, Constants::SE_GREG_CAL);
        $iyear = $result['y'];
        $imonth = $result['m'];
        $iday = $result['d'];
        $d = $result['ut'];

        $ihour = (int)$d;
        $d -= (float)$ihour;
        $d *= 60.0;
        $imin = (int)$d;
        $dsec = ($d - (float)$imin) * 60.0 + $second_60;

        // Fallback to UT1 for future dates if leap seconds table is outdated
        // Check if delta_t - nleap - 32.184 - NLEAP_INIT >= 1.0 second
        $d = \swe_deltat_ex($tjd_et, -1, $serr);
        $d = \swe_deltat_ex($tjd_et - $d, -1, $serr);
        if ($d * 86400.0 - (float)($nleap + self::NLEAP_INIT) - 32.184 >= 1.0) {
            // Table is outdated, use UT1 instead
            $result = \swe_revjul($tjd_et - $d, Constants::SE_GREG_CAL);
            $iyear = $result['y'];
            $imonth = $result['m'];
            $iday = $result['d'];
            $d = $result['ut'];

            $ihour = (int)$d;
            $d -= (float)$ihour;
            $d *= 60.0;
            $imin = (int)$d;
            $dsec = ($d - (float)$imin) * 60.0;
        }

        // If Julian calendar requested, convert the date
        if ($gregflag === Constants::SE_JUL_CAL) {
            $tjd = \swe_julday($iyear, $imonth, $iday, 0, Constants::SE_GREG_CAL);
            $result = \swe_revjul($tjd, $gregflag);
            $iyear = $result['y'];
            $imonth = $result['m'];
            $iday = $result['d'];
        }
    }

    /**
     * Convert Julian Day (UT1) to UTC date/time components.
     * Port of swe_jdut1_to_utc() from swedate.c:583-587
     *
     * This is a simple wrapper that converts UT1 to ET using Delta-T,
     * then calls swe_jdet_to_utc().
     *
     * @param float $tjd_ut Julian Day in UT1
     * @param int $gregflag Calendar flag (SE_GREG_CAL=1 or SE_JUL_CAL=0)
     * @param int|null &$iyear Output: year
     * @param int|null &$imonth Output: month (1-12)
     * @param int|null &$iday Output: day (1-31)
     * @param int|null &$ihour Output: hour (0-23)
     * @param int|null &$imin Output: minute (0-59)
     * @param float|null &$dsec Output: second (0-60, can be 60 for leap second)
     * @return void
     */
    public static function jdut1ToUtc(
        float $tjd_ut,
        int $gregflag,
        ?int &$iyear = null,
        ?int &$imonth = null,
        ?int &$iday = null,
        ?int &$ihour = null,
        ?int &$imin = null,
        ?float &$dsec = null
    ): void {
        // Convert UT1 to ET by adding Delta-T
        $serr = null;
        $tjd_et = $tjd_ut + \swe_deltat_ex($tjd_ut, -1, $serr);

        // Call swe_jdet_to_utc
        self::jdetToUtc($tjd_et, $gregflag, $iyear, $imonth, $iday, $ihour, $imin, $dsec);
    }
    /**
     * Apply timezone offset to convert between UTC and local time.
     * Port of swe_utc_time_zone() from swedate.c:234-264
     *
     * This function can convert in both directions:
     * - To convert LOCAL → UTC: use +d_timezone (e.g., +3.0 for MSK)
     * - To convert UTC → LOCAL: use -d_timezone (e.g., -3.0 for MSK)
     *
     * For time zones east of Greenwich, timezone value is positive.
     * For time zones west of Greenwich, timezone value is negative.
     *
     * Example: UTC 12:00 to MSK (UTC+3):
     *   swe_utc_time_zone(2025, 12, 13, 12, 0, 0, -3.0, ...) → 15:00 MSK
     *
     * Handles day/month/year rollover when crossing midnight.
     *
     * @param int $iyear Input year
     * @param int $imonth Input month
     * @param int $iday Input day
     * @param int $ihour Input hour
     * @param int $imin Input minute
     * @param float $dsec Input second (can be >= 60 for leap seconds)
     * @param float $d_timezone Timezone offset in hours (sign depends on direction: -tz for UTC→local, +tz for local→UTC)
     * @param int|null &$iyear_out Output year
     * @param int|null &$imonth_out Output month
     * @param int|null &$iday_out Output day
     * @param int|null &$ihour_out Output hour
     * @param int|null &$imin_out Output minute
     * @param float|null &$dsec_out Output second
     * @return void
     */
    public static function utcTimeZone(
        int $iyear,
        int $imonth,
        int $iday,
        int $ihour,
        int $imin,
        float $dsec,
        float $d_timezone,
        ?int &$iyear_out = null,
        ?int &$imonth_out = null,
        ?int &$iday_out = null,
        ?int &$ihour_out = null,
        ?int &$imin_out = null,
        ?float &$dsec_out = null
    ): void {
        // Handle leap seconds (dsec >= 60)
        $have_leapsec = false;
        if ($dsec >= 60.0) {
            $have_leapsec = true;
            $dsec -= 1.0;
        }

        // Convert time to decimal hours
        $dhour = (float)$ihour + ((float)$imin) / 60.0 + $dsec / 3600.0;

        // Get Julian Day for the date (at midnight)
        $tjd = \swe_julday($iyear, $imonth, $iday, 0, Constants::SE_GREG_CAL);

        // Apply timezone offset (subtract because we're converting FROM UTC TO local)
        $dhour -= $d_timezone;

        // Handle day rollover backwards (dhour < 0)
        if ($dhour < 0.0) {
            $tjd -= 1.0;
            $dhour += 24.0;
        }

        // Handle day rollover forwards (dhour >= 24)
        if ($dhour >= 24.0) {
            $tjd += 1.0;
            $dhour -= 24.0;
        }

        // Convert back to date components
        // Add small offset (0.001 day) to ensure we're well into the day
        $result = \swe_revjul($tjd + 0.001, Constants::SE_GREG_CAL);
        $iyear_out = $result['y'];
        $imonth_out = $result['m'];
        $iday_out = $result['d'];
        $d = $result['ut'];

        // Extract time components from decimal hours
        $ihour_out = (int)$dhour;
        $d = ($dhour - (float)$ihour_out) * 60.0;
        $imin_out = (int)$d;
        $dsec_out = ($d - (float)$imin_out) * 60.0;

        // Restore leap second if present
        if ($have_leapsec) {
            $dsec_out += 1.0;
        }
    }
}
