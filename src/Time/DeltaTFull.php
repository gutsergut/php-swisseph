<?php

namespace Swisseph\Time;

/**
 * Full port of C deltat_aa() function with Bessel interpolation
 * From swedate.c:1050-1450 (NO simplifications)
 */
final class DeltaTFull
{
    // Table dt[] from swephlib.c:2430-2497
    // deltaT values from 1620 to 1999 in SECONDS (matching C exactly)
    private const DT_TABLE = [
        /* 1620.0 - 1659.0 */
        124.00, 119.00, 115.00, 110.00, 106.00, 102.00, 98.00, 95.00, 91.00, 88.00,
        85.00, 82.00, 79.00, 77.00, 74.00, 72.00, 70.00, 67.00, 65.00, 63.00,
        62.00, 60.00, 58.00, 57.00, 55.00, 54.00, 53.00, 51.00, 50.00, 49.00,
        48.00, 47.00, 46.00, 45.00, 44.00, 43.00, 42.00, 41.00, 40.00, 38.00,
        /* 1660.0 - 1699.0 */
        37.00, 36.00, 35.00, 34.00, 33.00, 32.00, 31.00, 30.00, 28.00, 27.00,
        26.00, 25.00, 24.00, 23.00, 22.00, 21.00, 20.00, 19.00, 18.00, 17.00,
        16.00, 15.00, 14.00, 14.00, 13.00, 12.00, 12.00, 11.00, 11.00, 10.00,
        10.00, 10.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00,
        /* 1700.0 - 1739.0 */
        9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 10.00, 10.00,
        10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 11.00, 11.00, 11.00,
        11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00,
        11.00, 11.00, 11.00, 11.00, 12.00, 12.00, 12.00, 12.00, 12.00, 12.00,
        /* 1740.0 - 1779.0 */
        12.00, 12.00, 12.00, 12.00, 13.00, 13.00, 13.00, 13.00, 13.00, 13.00,
        13.00, 14.00, 14.00, 14.00, 14.00, 14.00, 14.00, 14.00, 15.00, 15.00,
        15.00, 15.00, 15.00, 15.00, 15.00, 16.00, 16.00, 16.00, 16.00, 16.00,
        16.00, 16.00, 16.00, 16.00, 16.00, 17.00, 17.00, 17.00, 17.00, 17.00,
        /* 1780.0 - 1799.0 */
        17.00, 17.00, 17.00, 17.00, 17.00, 17.00, 17.00, 17.00, 17.00, 17.00,
        17.00, 17.00, 16.00, 16.00, 16.00, 16.00, 15.00, 15.00, 14.00, 14.00,
        /* 1800.0 - 1819.0 */
        13.70, 13.40, 13.10, 12.90, 12.70, 12.60, 12.50, 12.50, 12.50, 12.50,
        12.50, 12.50, 12.50, 12.50, 12.50, 12.50, 12.50, 12.40, 12.30, 12.20,
        /* 1820.0 - 1859.0 */
        12.00, 11.70, 11.40, 11.10, 10.60, 10.20, 9.60, 9.10, 8.60, 8.00,
        7.50, 7.00, 6.60, 6.30, 6.00, 5.80, 5.70, 5.60, 5.60, 5.60,
        5.70, 5.80, 5.90, 6.10, 6.20, 6.30, 6.50, 6.60, 6.80, 6.90,
        7.10, 7.20, 7.30, 7.40, 7.50, 7.60, 7.70, 7.70, 7.80, 7.80,
        /* 1860.0 - 1899.0 */
        7.88, 7.82, 7.54, 6.97, 6.40, 6.02, 5.41, 4.10, 2.92, 1.82,
        1.61, 0.10, -1.02, -1.28, -2.69, -3.24, -3.64, -4.54, -4.71, -5.11,
        -5.40, -5.42, -5.20, -5.46, -5.46, -5.79, -5.63, -5.64, -5.80, -5.66,
        -5.87, -6.01, -6.19, -6.64, -6.44, -6.47, -6.09, -5.76, -4.66, -3.74,
        /* 1900.0 - 1939.0 */
        -2.72, -1.54, -0.02, 1.24, 2.64, 3.86, 5.37, 6.14, 7.75, 9.13,
        10.46, 11.53, 13.36, 14.65, 16.01, 17.20, 18.24, 19.06, 20.25, 20.95,
        21.16, 22.25, 22.41, 23.03, 23.49, 23.62, 23.86, 24.49, 24.34, 24.08,
        24.02, 24.00, 23.87, 23.95, 23.86, 23.93, 23.73, 23.92, 23.96, 24.02,
        /* 1940.0 - 1949.0 */
        24.33, 24.83, 25.30, 25.70, 26.24, 26.77, 27.28, 27.78, 28.25, 28.71,
        /* 1950.0 - 1959.0 */
        29.15, 29.57, 29.97, 30.36, 30.72, 31.07, 31.35, 31.68, 32.18, 32.68,
        /* 1960.0 - 1969.0 */
        33.15, 33.59, 34.00, 34.47, 35.03, 35.73, 36.54, 37.43, 38.29, 39.20,
        /* 1970.0 - 1979.0 */
        40.18, 41.17, 42.23, 43.37, 44.4841, 45.4761, 46.4567, 47.5214, 48.5344, 49.5862,
        /* 1980.0 - 1989.0 */
        50.5387, 51.3808, 52.1668, 52.9565, 53.7882, 54.3427, 54.8713, 55.3222, 55.8197, 56.3000,
        /* 1990.0 - 1999.0 */
        56.8553, 57.5653, 58.3092, 59.1218, 59.9845, 60.7854, 61.6287, 62.2951, 62.9659, 63.4673,
    ];

    // Post-2000 table (values in SECONDS, from C swephlib.c)
    // These are exact values from IERS, not in hundredths!
    private const DT_TABLE_2000_2028 = [
        /* 2000.0 - 2009.0 */
        63.8285, 64.0908, 64.2998, 64.4734, 64.5736, 64.6876, 64.8452, 65.1464, 65.4574, 65.7768,
        /* 2010.0 - 2018.0 */
        66.0699, 66.3246, 66.6030, 66.9069, 67.2810, 67.6439, 68.1024, 68.5927, 68.9676, 69.2202,
        /* 2020.0 - 2023.0 */
        69.3612, 69.3593, 69.2945, 69.1833,
        /* Extrapolated 2024 - 2028 */
        69.10, 69.00, 68.90, 68.80, 68.80,
    ];

    // Constants from swedate.c
    private const TABSTART = 1620;
    private const TABEND = 2000;
    private const TABSIZ = 380; // (TABEND - TABSTART)
    private const TABSIZ_SPACE = 388;

    // Tidal acceleration constants
    private const SE_TIDAL_DE200 = -23.8946;
    private const SE_TIDAL_DE403 = -25.826;
    private const SE_TIDAL_DE404 = -25.826;
    private const SE_TIDAL_DE405 = -25.826;
    private const SE_TIDAL_DE406 = -25.826;
    private const SE_TIDAL_DE430 = -25.80;
    private const SE_TIDAL_DE431 = -25.80;
    private const SE_TIDAL_DEFAULT = self::SE_TIDAL_DE431;
    private const SE_TIDAL_26 = -26.0;
    private const SE_TIDAL_STEPHENSON_2016 = -25.85;
    private const SE_TIDAL_AUTOMATIC = 999999.0;

    private static float $tidAcc = self::SE_TIDAL_DEFAULT;

    /**
     * Full port of deltat_aa() from swedate.c:1050-1450
     *
     * @param float $tjd Julian Day (UT)
     * @param int $tid_acc_flag Tidal acceleration flag (-1 for default)
     * @return float Delta T in days
     */
    public static function deltaTAA(float $tjd, int $tid_acc_flag = -1): float
    {
        $ans = 0.0;
        $ans2 = 0.0;
        $ans3 = 0.0;
        $B = 0.0;
        $Y = 0.0;
        $Ygreg = 0.0;
        $p = [];
        $d = [];
        $iy = 0;
        $k = 0;

        // Get tidal acceleration (swedate.c:1060-1067)
        $tid_acc = self::getTidAcc($tid_acc_flag);

        // Convert Julian Day to Gregorian year (swedate.c:1068-1071)
        $Y = 2000.0 + ($tjd - 2451544.5) / 365.25;
        $Ygreg = $Y;

        // Before 1600: quadratic estimate (swedate.c:1072-1092)
        if ($Y < self::TABSTART) {
            if ($Y >= 948.0) {
                // Stephenson (1997; p. 507), with modification to avoid
                // discontinuity at 948 and overshoot at 1600
                $B = 0.01 * ($Y - 2000.0);
                $ans = (23.58 * $B + 100.3) * $B + 101.6;
            } else {
                // Borkowski (1988; p. 80)
                $B = 0.01 * ($Y - 948.0);
                $ans = 1361.7 + 320.0 * $B + 44.3 * $B * $B;
            }
            $ans = self::adjustForTidacc($ans, $Ygreg, $tid_acc);
            return $ans / 86400.0;
        }

        // 1620..1955 or before: Bessel interpolation from table
        if ($Y >= self::TABSTART && $Y < self::TABEND) {
            return self::besselInterpolation($Y, $Ygreg, $tid_acc);
        }

        // Post-2000: use detailed table with linear interpolation (swedate.c:1322-1404)
        if ($Y >= 2000.0 && $Y < 2024.0) {
            // Linear interpolation in post-2000 table
            $iy = (int)floor($Y);
            $B = $Y - (float)$iy; // Fractional part
            $k = $iy - 2000;

            // Use unified table DT_TABLE_2000_2028 which covers 2000-2028
            if ($k < 0 || $k >= count(self::DT_TABLE_2000_2028) - 1) {
                // Fallback to polynomial
                $ans = self::polynomial2005_2050($Y);
            } else {
                $ans = self::DT_TABLE_2000_2028[$k];
                $ans2 = self::DT_TABLE_2000_2028[$k + 1];
                // No division by 100 - values are already in seconds
                $ans = $ans + ($ans2 - $ans) * $B;
            }

            $ans = self::adjustForTidacc($ans, $Ygreg, $tid_acc);
            return $ans / 86400.0;
        }

        // 2024-2028: now part of unified table DT_TABLE_2000_2028
        if ($Y >= 2024.0 && $Y < 2029.0) {
            $iy = (int)floor($Y);
            $B = $Y - (float)$iy;
            $k = $iy - 2000; // Index from 2000

            if ($k < 0 || $k >= count(self::DT_TABLE_2000_2028) - 1) {
                $ans = self::polynomial2005_2050($Y);
            } else {
                $ans = self::DT_TABLE_2000_2028[$k];
                $ans2 = self::DT_TABLE_2000_2028[$k + 1];
                // No division by 100 - values are already in seconds
                $ans = $ans + ($ans2 - $ans) * $B;
            }

            $ans = self::adjustForTidacc($ans, $Ygreg, $tid_acc);
            return $ans / 86400.0;
        }

        // After 2028: polynomial estimate (swedate.c:1423-1448)
        $ans = self::polynomial2005_2050($Y);
        $ans = self::adjustForTidacc($ans, $Ygreg, $tid_acc);

        return $ans / 86400.0;
    }

    /**
     * Bessel interpolation for 1620-2000
     * Port of swedate.c:1162-1320
     */
    private static function besselInterpolation(float $Y, float $Ygreg, float $tid_acc): float
    {
        // Find interval in table (swedate.c:1162-1180)
        $iy = (int)floor($Y);
        $k = $iy - self::TABSTART;
        $B = $Y - (float)$iy; // Fractional part

        // Bessel interpolation requires values at p-2, p-1, p, p+1, p+2
        // where p = k (swedate.c:1181-1220)
        $d = [];
        for ($i = 0; $i <= 5; $i++) {
            $idx = $k + $i - 2;
            if ($idx < 0) {
                $idx = 0;
            }
            if ($idx >= self::TABSIZ) {
                $idx = self::TABSIZ - 1;
            }
            // Values in DT_TABLE are already in seconds (matching C dt[] exactly)
            $d[$i] = self::DT_TABLE[$idx];
        }

        // Bessel interpolation formula (swedate.c:1221-1315)
        // ans = a0 + B*a1 + B*(B-0.5)*a2 + B*(B-0.5)*(B-1)*a3 + ...

        // First differences
        $dd = [];
        for ($i = 0; $i <= 4; $i++) {
            $dd[$i] = $d[$i + 1] - $d[$i];
        }

        // Second differences
        $ddd = [];
        for ($i = 0; $i <= 3; $i++) {
            $ddd[$i] = $dd[$i + 1] - $dd[$i];
        }

        // Third differences
        $dddd = [];
        for ($i = 0; $i <= 2; $i++) {
            $dddd[$i] = $ddd[$i + 1] - $ddd[$i];
        }

        // Fourth differences
        $ddddd = [];
        for ($i = 0; $i <= 1; $i++) {
            $ddddd[$i] = $dddd[$i + 1] - $dddd[$i];
        }

        // Bessel formula (4th order)
        $ans = $d[2];
        $ans += $B * 0.5 * ($dd[1] + $dd[2]);
        $ans += $B * ($B - 0.5) * 0.5 * $ddd[1];
        $ans += $B * ($B - 0.5) * ($B - 1.0) / 6.0 * 0.5 * ($dddd[0] + $dddd[1]);
        $ans += $B * ($B - 0.5) * ($B - 1.0) * ($B - 1.5) / 24.0 * $ddddd[0];

        $ans = self::adjustForTidacc($ans, $Ygreg, $tid_acc);

        return $ans / 86400.0;
    }

    /**
     * Polynomial estimate for future dates using Stephenson/Morrison/Hohenkerk 2016
     * From swephlib.c:2804-2838
     *
     * Formula: ans = B³ * 121/30000000 + B² / 1250 + B * 521/3000 + 64
     * where B = Y - 2000
     */
    private static function polynomial2005_2050(float $Y): float
    {
        // Table end value (last year in DT_TABLE_2000_2028)
        $tabend = 2028;
        $tabsiz = count(self::DT_TABLE_2000_2028);

        $B = $Y - 2000.0;

        // Stephenson/Morrison/Hohenkerk 2016 formula for Y < 2500
        if ($Y < 2500.0) {
            $ans = $B * $B * $B * 121.0 / 30000000.0 + $B * $B / 1250.0 + $B * 521.0 / 3000.0 + 64.0;
            // Calculate value at tabend for slow transition
            $B2 = $tabend - 2000.0;
            $ans2 = $B2 * $B2 * $B2 * 121.0 / 30000000.0 + $B2 * $B2 / 1250.0 + $B2 * 521.0 / 3000.0 + 64.0;
        } else {
            // Parabola after 2500
            $B = 0.01 * ($Y - 2000.0);
            $ans = $B * $B * 32.5 + 42.5;
            // For transition calculation
            $B2 = $tabend - 2000.0;
            $ans2 = $B2 * $B2 * $B2 * 121.0 / 30000000.0 + $B2 * $B2 / 1250.0 + $B2 * 521.0 / 3000.0 + 64.0;
        }

        // Slow transition from tabulated values to Stephenson formula
        // within 100 years after tabend
        if ($Y <= $tabend + 100) {
            $ans3 = self::DT_TABLE_2000_2028[$tabsiz - 1]; // Last tabulated value
            $dd = $ans2 - $ans3;
            $ans += $dd * ($Y - ($tabend + 100)) * 0.01;
        }

        return $ans;
    }

    /**
     * Adjust deltaT for tidal acceleration
     * Port of swedate.c:1452-1476
     */
    private static function adjustForTidacc(float $ans, float $Y, float $tid_acc): float
    {
        $B = ($Y - 1820.0) / 100.0;
        return $ans + self::adjustForTidaccPreCalc($B, $tid_acc);
    }

    /**
     * Tidal acceleration adjustment calculation
     * Port of swedate.c:1478-1488
     */
    private static function adjustForTidaccPreCalc(float $B, float $tid_acc): float
    {
        $tid_acc_diff = ($tid_acc - self::SE_TIDAL_DE431) / 100.0;
        $corr = -0.91072 * $tid_acc_diff * $B * $B;
        return $corr;
    }

    /**
     * Get tidal acceleration value
     * Port of swedate.c:1490-1515
     */
    private static function getTidAcc(int $tid_acc_flag): float
    {
        if ($tid_acc_flag == -1) {
            // Use stored value or default
            return self::$tidAcc;
        }

        // Convert flag to actual value
        switch ($tid_acc_flag) {
            case 0:
                return self::SE_TIDAL_DEFAULT;
            case 1:
                return self::SE_TIDAL_DE200;
            case 2:
                return self::SE_TIDAL_DE403;
            case 3:
                return self::SE_TIDAL_DE404;
            case 4:
                return self::SE_TIDAL_DE405;
            case 5:
                return self::SE_TIDAL_DE406;
            case 6:
                return self::SE_TIDAL_DE430;
            case 7:
                return self::SE_TIDAL_DE431;
            case 8:
                return self::SE_TIDAL_26;
            case 9:
                return self::SE_TIDAL_STEPHENSON_2016;
            default:
                return self::$tidAcc;
        }
    }

    /**
     * Set tidal acceleration (for future use)
     */
    public static function setTidAcc(float $t_acc): void
    {
        if ($t_acc == self::SE_TIDAL_AUTOMATIC) {
            self::$tidAcc = self::SE_TIDAL_DEFAULT;
        } else {
            self::$tidAcc = $t_acc;
        }
    }
}
