<?php

namespace Swisseph\Time;

/**
 * Stephenson/Morrison/Hohenkerk 2016 Delta T spline calculation
 *
 * Port of deltat_stephenson_etc_2016() from swephlib.c:3004-3042
 *
 * These coefficients represent the spline approximation discussed in the
 * paper "Measurement of the Earth's Rotation: 720 BC to AD 2015",
 * Stephenson, F.R., Morrison, L.V., and Hohenkerk, C.Y., published by
 * Royal Society Proceedings A.
 */
final class DeltaTStephenson2016
{
    // Spline coefficients from swephlib.c:2952-3003
    // Format: [jd_start, jd_end, a, b, c, d] for polynomial dt = a + b*t + c*t^2 + d*t^3
    // where t = (tjd - jd_start) / (jd_end - jd_start)
    private const DTCF16 = [
        /* 00 */ [1458085.5, 1867156.5, 20550.593, -21268.478, 11863.418, -4541.129], /* ybeg=-720, yend= 400 */
        /* 01 */ [1867156.5, 2086302.5,  6604.404,  -5981.266,  -505.093,  1349.609], /* ybeg= 400, yend=1000 */
        /* 02 */ [2086302.5, 2268923.5,  1467.654,  -2452.187,  2460.927, -1183.759], /* ybeg=1000, yend=1500 */
        /* 03 */ [2268923.5, 2305447.5,   292.635,   -216.322,   -43.614,    56.681], /* ybeg=1500, yend=1600 */
        /* 04 */ [2305447.5, 2323710.5,    89.380,    -66.754,    31.607,   -10.497], /* ybeg=1600, yend=1650 */
        /* 05 */ [2323710.5, 2349276.5,    43.736,    -49.043,     0.227,    15.811], /* ybeg=1650, yend=1720 */
        /* 06 */ [2349276.5, 2378496.5,    10.730,     -1.321,    62.250,   -52.946], /* ybeg=1720, yend=1800 */
        /* 07 */ [2378496.5, 2382148.5,    18.714,     -4.457,    -1.509,     2.507], /* ybeg=1800, yend=1810 */
        /* 08 */ [2382148.5, 2385800.5,    15.255,      0.046,     6.012,    -4.634], /* ybeg=1810, yend=1820 */
        /* 09 */ [2385800.5, 2389453.5,    16.679,     -1.831,    -7.889,     3.799], /* ybeg=1820, yend=1830 */
        /* 10 */ [2389453.5, 2393105.5,    10.758,     -6.211,     3.509,    -0.388], /* ybeg=1830, yend=1840 */
        /* 11 */ [2393105.5, 2396758.5,     7.668,     -0.357,     2.345,    -0.338], /* ybeg=1840, yend=1850 */
        /* 12 */ [2396758.5, 2398584.5,     9.317,      1.659,     0.332,    -0.932], /* ybeg=1850, yend=1855 */
        /* 13 */ [2398584.5, 2400410.5,    10.376,     -0.472,    -2.463,     1.596], /* ybeg=1855, yend=1860 */
        /* 14 */ [2400410.5, 2402237.5,     9.038,     -0.610,     2.325,    -2.497], /* ybeg=1860, yend=1865 */
        /* 15 */ [2402237.5, 2404063.5,     8.256,     -3.450,    -5.166,     2.729], /* ybeg=1865, yend=1870 */
        /* 16 */ [2404063.5, 2405889.5,     2.369,     -5.596,     3.020,    -0.919], /* ybeg=1870, yend=1875 */
        /* 17 */ [2405889.5, 2407715.5,    -1.126,     -2.312,     0.264,    -0.037], /* ybeg=1875, yend=1880 */
        /* 18 */ [2407715.5, 2409542.5,    -3.211,     -1.894,     0.154,     0.562], /* ybeg=1880, yend=1885 */
        /* 19 */ [2409542.5, 2411368.5,    -4.388,      0.101,     1.841,    -1.438], /* ybeg=1885, yend=1890 */
        /* 20 */ [2411368.5, 2413194.5,    -3.884,     -0.531,    -2.473,     1.870], /* ybeg=1890, yend=1895 */
        /* 21 */ [2413194.5, 2415020.5,    -5.017,      0.134,     3.138,    -0.232], /* ybeg=1895, yend=1900 */
        /* 22 */ [2415020.5, 2416846.5,    -1.977,      5.715,     2.443,    -1.257], /* ybeg=1900, yend=1905 */
        /* 23 */ [2416846.5, 2418672.5,     4.923,      6.828,    -1.329,     0.720], /* ybeg=1905, yend=1910 */
        /* 24 */ [2418672.5, 2420498.5,    11.142,      6.330,     0.831,    -0.825], /* ybeg=1910, yend=1915 */
        /* 25 */ [2420498.5, 2422324.5,    17.479,      5.518,    -1.643,     0.262], /* ybeg=1915, yend=1920 */
        /* 26 */ [2422324.5, 2424151.5,    21.617,      3.020,    -0.856,     0.008], /* ybeg=1920, yend=1925 */
        /* 27 */ [2424151.5, 2425977.5,    23.789,      1.333,    -0.831,     0.127], /* ybeg=1925, yend=1930 */
        /* 28 */ [2425977.5, 2427803.5,    24.418,      0.052,    -0.449,     0.142], /* ybeg=1930, yend=1935 */
        /* 29 */ [2427803.5, 2429629.5,    24.164,     -0.419,    -0.022,     0.702], /* ybeg=1935, yend=1940 */
        /* 30 */ [2429629.5, 2431456.5,    24.426,      1.645,     2.086,    -1.106], /* ybeg=1940, yend=1945 */
        /* 31 */ [2431456.5, 2433282.5,    27.050,      2.499,    -1.232,     0.614], /* ybeg=1945, yend=1950 */
        /* 32 */ [2433282.5, 2434378.5,    28.932,      1.127,     0.220,    -0.277], /* ybeg=1950, yend=1953 */
        /* 33 */ [2434378.5, 2435473.5,    30.002,      0.737,    -0.610,     0.631], /* ybeg=1953, yend=1956 */
        /* 34 */ [2435473.5, 2436569.5,    30.760,      1.409,     1.282,    -0.799], /* ybeg=1956, yend=1959 */
        /* 35 */ [2436569.5, 2437665.5,    32.652,      1.577,    -1.115,     0.507], /* ybeg=1959, yend=1962 */
        /* 36 */ [2437665.5, 2438761.5,    33.621,      0.868,     0.406,     0.199], /* ybeg=1962, yend=1965 */
        /* 37 */ [2438761.5, 2439856.5,    35.093,      2.275,     1.002,    -0.414], /* ybeg=1965, yend=1968 */
        /* 38 */ [2439856.5, 2440952.5,    37.956,      3.035,    -0.242,     0.202], /* ybeg=1968, yend=1971 */
        /* 39 */ [2440952.5, 2442048.5,    40.951,      3.157,     0.364,    -0.229], /* ybeg=1971, yend=1974 */
        /* 40 */ [2442048.5, 2443144.5,    44.244,      3.198,    -0.323,     0.172], /* ybeg=1974, yend=1977 */
        /* 41 */ [2443144.5, 2444239.5,    47.291,      3.069,     0.193,    -0.192], /* ybeg=1977, yend=1980 */
        /* 42 */ [2444239.5, 2445335.5,    50.361,      2.878,    -0.384,     0.081], /* ybeg=1980, yend=1983 */
        /* 43 */ [2445335.5, 2446431.5,    52.936,      2.354,    -0.140,    -0.166], /* ybeg=1983, yend=1986 */
        /* 44 */ [2446431.5, 2447527.5,    54.984,      1.577,    -0.637,     0.448], /* ybeg=1986, yend=1989 */
        /* 45 */ [2447527.5, 2448622.5,    56.373,      1.649,     0.709,    -0.277], /* ybeg=1989, yend=1992 */
        /* 46 */ [2448622.5, 2449718.5,    58.453,      2.235,    -0.122,     0.111], /* ybeg=1992, yend=1995 */
        /* 47 */ [2449718.5, 2450814.5,    60.677,      2.324,     0.212,    -0.315], /* ybeg=1995, yend=1998 */
        /* 48 */ [2450814.5, 2451910.5,    62.899,      1.804,    -0.732,     0.112], /* ybeg=1998, yend=2001 */
        /* 49 */ [2451910.5, 2453005.5,    64.082,      0.675,    -0.396,     0.193], /* ybeg=2001, yend=2004 */
        /* 50 */ [2453005.5, 2454101.5,    64.555,      0.463,     0.184,    -0.008], /* ybeg=2004, yend=2007 */
        /* 51 */ [2454101.5, 2455197.5,    65.194,      0.809,     0.161,    -0.101], /* ybeg=2007, yend=2010 */
        /* 52 */ [2455197.5, 2456293.5,    66.063,      0.828,    -0.142,     0.168], /* ybeg=2010, yend=2013 */
        /* 53 */ [2456293.5, 2457388.5,    66.917,      1.046,     0.360,    -0.282], /* ybeg=2013, yend=2016 */
    ];

    // JD for 1 Jan 1955 (switch point to table data)
    private const JD_1955 = 2435108.5;
    // JD for 1 Jan 1952 (start of transition)
    private const JD_1955_MINUS_1000 = 2434108.5;

    // Tidal acceleration for Stephenson 2016
    private const SE_TIDAL_STEPHENSON_2016 = -25.85;
    private const SE_TIDAL_DE431 = -25.80;

    /**
     * Calculate Delta T using Stephenson 2016 spline
     * Port of deltat_stephenson_etc_2016() from swephlib.c:3004-3042
     *
     * @param float $tjd Julian day (UT)
     * @param float $tid_acc Tidal acceleration (default: SE_TIDAL_DE431)
     * @return float Delta T in seconds
     */
    public static function deltaTSeconds(float $tjd, float $tid_acc = self::SE_TIDAL_DE431): float
    {
        $Ygreg = 2000.0 + ($tjd - 2451545.0) / 365.2425;

        // Find spline record
        $irec = -1;
        foreach (self::DTCF16 as $i => $rec) {
            if ($tjd < $rec[0]) {
                break;
            }
            if ($tjd < $rec[1]) {
                $irec = $i;
                break;
            }
        }

        if ($irec >= 0) {
            // Spline interpolation
            $rec = self::DTCF16[$irec];
            $t = ($tjd - $rec[0]) / ($rec[1] - $rec[0]);
            $dt = $rec[2] + $rec[3] * $t + $rec[4] * $t * $t + $rec[5] * $t * $t * $t;
        } elseif ($Ygreg < -720) {
            // Before -720: long-term parabola
            $t = ($Ygreg - 1825) / 100.0;
            $dt = -320 + 32.5 * $t * $t;
            $dt -= 179.7337208; // to make curve continuous on 1 Jan -720
        } else {
            // Future (after 2016 in spline)
            $t = ($Ygreg - 1825) / 100.0;
            $dt = -320 + 32.5 * $t * $t;
            $dt += 269.4790417; // to make curve continuous on 1 Jan 2016
        }

        // Adjust for tidal acceleration
        // The parameter adjust_after_1955 must be TRUE for Stephenson 2016
        $dt = self::adjustForTidacc($dt, $Ygreg, $tid_acc, true);

        return $dt;
    }

    /**
     * Check if Stephenson 2016 should be used for this date
     * (applies for dates before 1 Jan 1955)
     */
    public static function shouldUse(float $tjd): bool
    {
        return $tjd < self::JD_1955;
    }

    /**
     * Get transition factor for smooth transition to table data
     * Returns value to add for dates between 1952-1955
     * Port of swephlib.c:2590-2593
     */
    public static function getTransitionFactor(float $tjd): float
    {
        if ($tjd >= self::JD_1955_MINUS_1000) {
            return (1.0 - (self::JD_1955 - $tjd) / 1000.0) * 0.6610218;
        }
        return 0.0;
    }

    /**
     * Adjust for tidal acceleration
     * Port of adjust_for_tidacc() from swephlib.c
     */
    private static function adjustForTidacc(float $ans, float $Y, float $tid_acc, bool $adjust_after_1955): float
    {
        $B = ($Y - 1820.0) / 100.0;

        // Difference from Stephenson 2016 tidal acceleration
        $tid_acc_diff = ($tid_acc - self::SE_TIDAL_STEPHENSON_2016) / 100.0;

        // Adjustment factor
        $corr = -0.91072 * $tid_acc_diff * $B * $B;

        return $ans + $corr;
    }
}
