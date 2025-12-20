<?php

namespace Swisseph;

final class DeltaT
{
    /**
     * Estimate Delta T (TT-UT) in seconds for a decimal year.
     * Based on NASA polynomial approximations (public domain).
     */
    public static function estimateSecondsByYear(float $year): float
    {
        if ($year >= 2005 && $year <= 2050) {
            $t = $year - 2000.0;
            return 62.92 + 0.32217*$t + 0.005589*$t*$t;
        }
        if ($year >= 1986 && $year < 2005) {
            $t = $year - 2000.0;
            return 63.86 + 0.3345*$t - 0.060374*$t*$t + 0.0017275*$t*$t*$t
                + 0.000651814*$t**4 + 0.00002373599*$t**5;
        }
        if ($year >= 1961 && $year < 1986) {
            $t = $year - 1975.0;
            return 45.45 + 1.067*$t - ($t*$t)/260.0 - ($t*$t*$t)/718.0;
        }
        if ($year >= 1900 && $year < 1961) {
            $t = $year - 1900.0;
            return -2.79 + 1.494119*$t - 0.0598939*$t*$t + 0.0061966*$t**3 - 0.000197*$t**4;
        }
        if ($year >= 1860 && $year < 1900) {
            $t = $year - 1860.0;
            return 7.62 + 0.5737*$t - 0.251754*$t*$t + 0.01680668*$t**3 - 0.0004473624*$t**4 + ($t**5)/233174.0;
        }
        if ($year >= 1800 && $year < 1860) {
            $t = $year - 1800.0;
            return 13.72 - 0.332447*$t + 0.0068612*$t*$t + 0.0041116*$t**3 - 0.00037436*$t**4
                + 0.0000121272*$t**5 - 0.0000001699*$t**6 + 0.000000000875*$t**7;
        }
        if ($year >= 1700 && $year < 1800) {
            $t = $year - 1700.0;
            return 8.83 + 0.1603*$t - 0.0059285*$t*$t + 0.00013336*$t**3 - ($t**4)/1174000.0;
        }
        if ($year >= 1620 && $year < 1700) {
            $t = $year - 1650.0;
            return 120.0 - 0.9808*$t - 0.01532*$t*$t + ($t**3)/7129.0;
        }
        // Fallback crude approximation for other epochs
        // Use Morrison & Stephenson quadratic growth far from 2000.
        $t = ($year - 2000.0)/100.0; // centuries
        return 64.0 + 59.0*$t*$t; // very rough
    }

    /**
     * Estimate Delta T (seconds) from JD (UT), using full Bessel interpolation.
     *
     * This method now uses the complete C algorithm port:
     * - For dates before 1 Jan 1955: Stephenson/Morrison/Hohenkerk 2016 spline
     * - For dates 1955+: Table + Bessel interpolation (DeltaTFull)
     *
     * Previous polynomial approximation is kept in estimateSecondsByYear() for reference only.
     */
    public static function deltaTSecondsFromJd(float $jd): float
    {
        // Use Stephenson 2016 spline for dates before 1955
        // This is the default model in Swiss Ephemeris 2.06+
        if (\Swisseph\Time\DeltaTStephenson2016::shouldUse($jd)) {
            $dt_sec = \Swisseph\Time\DeltaTStephenson2016::deltaTSeconds($jd);
            // Add transition factor for smooth transition to table data
            $dt_sec += \Swisseph\Time\DeltaTStephenson2016::getTransitionFactor($jd);
            return $dt_sec;
        }

        // For 1955+: Use table + Bessel interpolation
        $dt_days = \Swisseph\Time\DeltaTFull::deltaTAA($jd, -1);
        return $dt_days * 86400.0; // Convert days to seconds
    }
}
