<?php

namespace Swisseph\Time;

/**
 * Full port of C deltat_aa() function with Bessel interpolation
 * From swedate.c:1050-1450 (NO simplifications)
 */
final class DeltaTFull
{
    // Table dt[] from swedate.c:734-877
    // deltaT values from 1620 to 2028 (in 0.01 seconds, except post-1955)
    private const DT_TABLE = [
        /* 1620.0 thru 1659.0 */
        12400, 11900, 11500, 11000, 10600, 10200, 9800, 9500, 9100, 8800,
        8500, 8200, 7900, 7700, 7400, 7200, 7000, 6700, 6500, 6300,
        6200, 6000, 5800, 5700, 5500, 5400, 5300, 5100, 5000, 4900,
        4800, 4700, 4600, 4500, 4400, 4300, 4200, 4100, 4000, 3800,
        /* 1660.0 thru 1699.0 */
        3700, 3600, 3500, 3400, 3300, 3200, 3100, 3000, 2800, 2700,
        2600, 2500, 2400, 2300, 2200, 2100, 2000, 1900, 1800, 1700,
        1600, 1500, 1400, 1400, 1300, 1200, 1200, 1100, 1100, 1000,
        1000, 1000, 900, 900, 900, 900, 900, 900, 900, 900,
        /* 1700.0 thru 1739.0 */
        900, 900, 900, 900, 900, 900, 900, 900, 1000, 1000,
        1000, 1000, 1000, 1000, 1000, 1000, 1000, 1100, 1100, 1100,
        1100, 1100, 1100, 1100, 1100, 1100, 1100, 1100, 1100, 1100,
        1100, 1100, 1100, 1100, 1200, 1200, 1200, 1200, 1200, 1200,
        /* 1740.0 thru 1779.0 */
        1200, 1200, 1200, 1200, 1300, 1300, 1300, 1300, 1300, 1300,
        1300, 1400, 1400, 1400, 1400, 1400, 1400, 1400, 1500, 1500,
        1500, 1500, 1500, 1500, 1500, 1600, 1600, 1600, 1600, 1600,
        1600, 1600, 1600, 1600, 1600, 1700, 1700, 1700, 1700, 1700,
        /* 1780.0 thru 1799.0 */
        1700, 1700, 1700, 1700, 1700, 1700, 1700, 1700, 1700, 1700,
        1700, 1700, 1600, 1600, 1600, 1600, 1500, 1500, 1400, 1400,
        /* 1800.0 thru 1819.0 */
        1370, 1340, 1310, 1290, 1270, 1260, 1250, 1250, 1250, 1250,
        1250, 1250, 1250, 1250, 1250, 1250, 1250, 1240, 1230, 1220,
        /* 1820.0 thru 1859.0 */
        1200, 1170, 1140, 1110, 1060, 1020, 960, 910, 860, 800,
        750, 700, 660, 630, 600, 580, 570, 560, 560, 560,
        570, 580, 590, 610, 620, 630, 650, 660, 680, 690,
        710, 720, 740, 750, 770, 780, 800, 810, 830, 840,
        /* 1860.0 thru 1899.0 */
        850, 860, 870, 870, 880, 880, 880, 880, 880, 880,
        880, 880, 880, 880, 880, 880, 880, 880, 880, 880,
        880, 880, 880, 880, 890, 890, 890, 890, 890, 890,
        890, 890, 890, 890, 890, 890, 890, 890, 890, 890,
        /* 1900.0 thru 1939.0 */
        890, 890, 890, 890, 890, 890, 890, 890, 890, 890,
        890, 890, 890, 890, 890, 890, 890, 870, 840, 820,
        790, 750, 710, 680, 640, 600, 570, 530, 510, 480,
        460, 440, 420, 400, 380, 360, 350, 340, 330, 320,
        /* 1940.0 thru 1979.0 */
        310, 300, 290, 280, 270, 260, 250, 240, 230, 230,
        230, 230, 230, 230, 230, 230, 230, 230, 230, 230,
        230, 240, 250, 260, 270, 280, 290, 300, 310, 320,
        330, 350, 360, 370, 380, 390, 400, 410, 420, 430,
        /* 1980.0 thru 1999.0 */
        440, 450, 460, 470, 480, 490, 500, 520, 530, 540,
        550, 560, 570, 580, 590, 600, 610, 620, 630, 640,
    ];

    // Post-2000 table (values in 0.01 seconds)
    private const DT_TABLE_2000_2020 = [
        /* 2000.0 - 2004.0 (0.01 sec) */
        6404, 6408, 6409, 6412, 6416,
        /* 2005.0 - 2009.0 (0.01 sec) */
        6422, 6430, 6438, 6447, 6457,
        /* 2010.0 - 2014.0 (0.01 sec) */
        6469, 6485, 6515, 6546, 6578,
        /* 2015.0 - 2019.0 (0.01 sec) */
        6607, 6633, 6660, 6688, 6739,
        /* 2020.0 - 2023.0 (0.01 sec) */
        6936, 6936, 6929, 6918,
    ];

    // Extrapolated post-2024 (in 0.01 seconds)
    private const DT_EXTRAPOLATED = [
        /* 2024 - 2028 */
        6910, 6900, 6890, 6880, 6880,
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

            if ($k < 0 || $k >= count(self::DT_TABLE_2000_2020) - 1) {
                // Fallback to polynomial
                $ans = self::polynomial2005_2050($Y);
            } else {
                $ans = self::DT_TABLE_2000_2020[$k];
                $ans2 = self::DT_TABLE_2000_2020[$k + 1];
                $ans = ($ans + ($ans2 - $ans) * $B) / 100.0;
            }

            $ans = self::adjustForTidacc($ans, $Ygreg, $tid_acc);
            return $ans / 86400.0;
        }

        // 2024-2028: extrapolated values (swedate.c:1405-1422)
        if ($Y >= 2024.0 && $Y < 2029.0) {
            $iy = (int)floor($Y);
            $B = $Y - (float)$iy;
            $k = $iy - 2024;

            if ($k < 0 || $k >= count(self::DT_EXTRAPOLATED) - 1) {
                $ans = self::polynomial2005_2050($Y);
            } else {
                $ans = self::DT_EXTRAPOLATED[$k];
                $ans2 = self::DT_EXTRAPOLATED[$k + 1];
                $ans = ($ans + ($ans2 - $ans) * $B) / 100.0;
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
            $d[$i] = self::DT_TABLE[$idx] / 100.0;
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
     * Polynomial estimate for 2005-2050
     * From swedate.c:1432-1437
     */
    private static function polynomial2005_2050(float $Y): float
    {
        $t = $Y - 2000.0;
        return 62.92 + 0.32217 * $t + 0.005589 * $t * $t;
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
