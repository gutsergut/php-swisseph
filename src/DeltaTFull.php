<?php

namespace Swisseph;

/**
 * Full Delta T (TT-UT) calculation using table and Bessel interpolation
 * Port of swephlib.c:deltat_aa() function
 *
 * Uses tabulated values from 1620-2025 with Bessel 4th-order interpolation
 * Extrapolates before 1620 and after 2025 using Morrison & Stephenson formulas
 * Adjusts for tidal acceleration (SE_TIDAL_DE431 = -25.80 arcsec/cy²)
 */
final class DeltaTFull
{
    /**
     * Delta T table from swephlib.c:2431 (1620-2028)
     * Values in seconds
     * 1620-1799: 1-year intervals
     * 1800-2028: 1-year intervals
     * NOTE: Table has variable intervals - need special handling
     */
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
        /* 2000.0 - 2009.0 */
        63.8285, 64.0908, 64.2998, 64.4734, 64.5736, 64.6876, 64.8452, 65.1464, 65.4574, 65.7768,
        /* 2010.0 - 2018.0 */
        66.0699, 66.3246, 66.6030, 66.9069, 67.2810, 67.6439, 68.1024, 68.5927, 68.9676, 69.2202,
        /* 2020.0 - 2023.0 */
        69.3612, 69.3593, 69.2945, 69.1833,
        /* 2024.0 - 2028.0 (extrapolated) */
        69.10, 69.00, 68.90, 68.80, 68.80,
    ];

    private const TABLE_START_YEAR = 1620.0;
    private const TABLE_INTERVAL = 1.0; // years between table entries    private const SE_TIDAL_DE431 = -25.80; // arcsec/cy² (DE431 ephemeris)
    private const SE_TIDAL_DEFAULT = -25.80;

    /**
     * Calculate Delta T for given Julian Day
     * Port of swephlib.c:deltat_aa()
     *
     * @param float $tjd Julian Day (TT scale)
     * @param float $tidAcc Tidal acceleration (default -25.80)
     * @return float Delta T in days
     */
    public static function calculate(float $tjd, float $tidAcc = self::SE_TIDAL_DEFAULT): float
    {
        // Convert JD to decimal year
        $year = 2000.0 + ($tjd - 2451544.5) / 365.25;

        // Calculate base Delta T from table or formula
        $dt = self::getDeltaTBase($year);

        // Adjust for tidal acceleration
        $dt = self::adjustForTidAcc($dt, $year, $tidAcc);

        // Convert seconds to days
        return $dt / 86400.0;
    }

    /**
     * Get base Delta T value (before tidal acceleration adjustment)
     * Port of swephlib.c:deltat_aa() main switch
     */
    private static function getDeltaTBase(float $year): float
    {
        // After 2025: extrapolate using Morrison & Stephenson
        if ($year > 2025.0) {
            $t = ($year - 2000.0) / 100.0; // centuries from 2000
            return 64.0 + 59.0 * $t * $t;
        }

        // 1620-2025: use table with Bessel interpolation
        if ($year >= self::TABLE_START_YEAR) {
            return self::interpolateTable($year);
        }

        // Before 1620: use Morrison & Stephenson formula
        $t = ($year - 2000.0) / 100.0; // centuries from 2000
        return 64.0 + 59.0 * $t * $t;
    }

    /**
     * Interpolate Delta T from table using Bessel 4th-order interpolation
     * Port of swephlib.c:deltat_aa() Bessel interpolation section
     */
    private static function interpolateTable(float $year): float
    {
        // Find position in table
        $yearsFromStart = $year - self::TABLE_START_YEAR;
        $tableIndex = $yearsFromStart / self::TABLE_INTERVAL;

        $i = (int)floor($tableIndex);
        $p = $tableIndex - $i; // fractional part

        // Boundary check
        $tableSize = count(self::DT_TABLE);
        if ($i < 0) {
            $i = 0;
            $p = 0.0;
        }
        if ($i >= $tableSize - 1) {
            $i = $tableSize - 2;
            $p = 1.0;
        }

        // Bessel interpolation (4th order)
        // Requires 6 points: i-2, i-1, i, i+1, i+2, i+3

        $y0 = self::getTableValue($i);
        $y1 = self::getTableValue($i + 1);

        // Simple linear interpolation if at boundaries
        if ($i < 2 || $i >= $tableSize - 3) {
            return $y0 + $p * ($y1 - $y0);
        }

        // Full Bessel interpolation
        $ym2 = self::getTableValue($i - 2);
        $ym1 = self::getTableValue($i - 1);
        $y2 = self::getTableValue($i + 2);
        $y3 = self::getTableValue($i + 3);

        // Bessel differences
        $d1 = $y1 - $y0;
        $d2 = $y2 - $y1;
        $dm1 = $y0 - $ym1;
        $dm2 = $ym1 - $ym2;
        $d3 = $y3 - $y2;

        // Second differences
        $dd1 = $d1 - $dm1;
        $dd2 = $d2 - $d1;
        $dd3 = $d3 - $d2;

        // Third differences
        $ddd1 = $dd2 - $dd1;
        $ddd2 = $dd3 - $dd2;

        // Fourth difference
        $dddd = $ddd2 - $ddd1;

        // Bessel formula
        $p2 = $p * $p;
        $p3 = $p2 * $p;
        $p4 = $p3 * $p;

        $result = $y0 + $p * $d1
            + ($p2 - 0.25) * $dd1 / 2.0
            + ($p3 - $p) * $ddd1 / 6.0
            + (($p4 - $p2) + 0.0625) * $dddd / 24.0;

        return $result;
    }

    /**
     * Get table value with boundary check
     */
    private static function getTableValue(int $index): float
    {
        if ($index < 0) {
            return self::DT_TABLE[0];
        }
        if ($index >= count(self::DT_TABLE)) {
            return self::DT_TABLE[count(self::DT_TABLE) - 1];
        }
        return self::DT_TABLE[$index];
    }

    /**
     * Adjust Delta T for tidal acceleration
     * Port of swephlib.c:adjust_for_tidacc()
     *
     * @param float $dt Base Delta T in seconds
     * @param float $year Decimal year
     * @param float $tidAcc Tidal acceleration in arcsec/cy²
     * @return float Adjusted Delta T in seconds
     */
    private static function adjustForTidAcc(float $dt, float $year, float $tidAcc): float
    {
        // Standard tidal acceleration values
        $SE_TIDAL_DE200 = -23.8946;
        $SE_TIDAL_DE403 = -25.826;
        $SE_TIDAL_DE404 = -25.826;
        $SE_TIDAL_DE405 = -25.826;
        $SE_TIDAL_DE406 = -25.826;
        $SE_TIDAL_26 = -26.0;
        $SE_TIDAL_DEFAULT = -25.80;
        $SE_TIDAL_STEPHENSON_2016 = -25.85;
        $SE_TIDAL_LECODE_1952 = -26.4;

        // No adjustment for default or DE431
        if (abs($tidAcc - $SE_TIDAL_DEFAULT) < 0.01) {
            return $dt;
        }

        // Adjustment formula (simplified version)
        // Full formula requires numerical integration, this is approximation
        $centuries = ($year - 2000.0) / 100.0;
        $adjustment = ($tidAcc - $SE_TIDAL_DEFAULT) * $centuries * $centuries * 0.91072;

        return $dt + $adjustment;
    }
}
