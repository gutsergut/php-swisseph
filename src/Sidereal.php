<?php

namespace Swisseph;

final class Sidereal
{
    /**
     * Greenwich Mean Sidereal Time (hours) from JD (UT1).
     * Meeus formula: theta = 280.46061837 + 360.98564736629*(JD-2451545)
     *                + 0.000387933*T^2 - T^3/38710000 (deg), T in Julian centuries from J2000.
     */
    public static function gmstHoursFromJdUt(float $jd_ut): float
    {
        $T = ($jd_ut - 2451545.0) / 36525.0;
        $theta = 280.46061837 + 360.98564736629 * ($jd_ut - 2451545.0)
            + 0.000387933 * ($T * $T) - ($T * $T * $T) / 38710000.0; // degrees
        $theta = Math::normAngleDeg($theta);
        return $theta / 15.0; // hours
    }

    /**
     * Approximate equation of time E (in days) for a given JD(UT).
     * Simple approximation: E = apparent solar time - mean solar time.
     * Here we use Meeus-ish formula via sun's ecliptic elements; coarse but sufficient as scaffold.
     */
    public static function equationOfTimeDays(float $jd_ut): float
    {
        // Convert to TT for solar longitude computation
        $dt = \Swisseph\DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
        $t = ($jd_ut + $dt - 2451545.0) / 36525.0;
        $L0 = 280.46646 + 36000.76983 * $t + 0.0003032 * $t * $t; // mean longitude (deg)
        $L0 = Math::normAngleDeg($L0);
        $e = 0.016708634 - 0.000042037 * $t - 0.0000001267 * $t * $t; // eccentricity
        $eps = Math::radToDeg(\Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_ut + $dt)); // mean obliquity
        // Sun's mean anomaly
        $M = 357.52911 + 35999.05029 * $t - 0.0001537 * $t * $t; // deg
        $Mr = Math::degToRad(Math::normAngleDeg($M));
        $L0r = Math::degToRad($L0);
        $epsr = Math::degToRad($eps);
        $y = pow(tan($epsr / 2.0), 2);
        $E = $y * sin(2 * $L0r) - 2 * $e * sin($Mr) + 4 * $e * $y * sin($Mr) * cos(2 * $L0r)
            - 0.5 * $y * $y * sin(4 * $L0r) - 1.25 * $e * $e * sin(2 * $Mr);
        // E is in radians of right ascension; convert to minutes of time: 4 min/deg
        $E_minutes = Math::radToDeg($E) * 4.0;
        return $E_minutes / (24.0 * 60.0); // days
    }

    /**
     * Ayanamsha computation (degrees) for given JD(TT) and current sidereal mode.
     * Implements the full precession-based method from Swiss Ephemeris with SE_SIDBIT_* options.
     *
     * Port of swi_get_ayanamsa_ex() from sweph.c
     *
     * Note: "True" ayanamshas based on fixed stars (True Citra, True Revati, etc.)
     * are not yet implemented and will fall back to simple model.
     *
     * @param float $jd_tt Julian Day in TT
     * @return float Ayanamsha in degrees (can be negative or > 360°)
     */
    public static function ayanamshaDegFromJdTT(float $jd_tt): float
    {
        [$sidMode, $sidOpts, $t0User, $ayan0User] = \Swisseph\State::getSidMode();

        // User-defined mode: return constant offset
        if ($sidMode === \Swisseph\Constants::SE_SIDM_USER) {
            return $ayan0User;
        }

        // Get ayanamsha data from table
        $data = \Swisseph\Domain\Sidereal\AyanamsaData::get($sidMode);
        if ($data === null) {
            // Fallback to Fagan/Bradley if mode not found
            $data = \Swisseph\Domain\Sidereal\AyanamsaData::get(\Swisseph\Constants::SE_SIDM_FAGAN_BRADLEY);
        }

        [$t0, $ayan_t0, $t0_is_UT, $prec_offset] = $data;

        // Convert t0 to TT if it's UT
        if ($t0_is_UT) {
            $dt = \Swisseph\DeltaT::deltaTSecondsFromJd($t0) / 86400.0;
            $t0 += $dt;
        }

        // Determine precession model to use
        // If prec_offset is 0 or -1, use default model (null = auto-select in Precession class)
        $precModel = ($prec_offset > 0) ? $prec_offset : null;

        // Check if SE_SIDBIT_ECL_DATE is set (alternative method, more consistent)
        // This method measures ayanamsha on ecliptic of date instead of ecliptic t0
        if ($sidOpts & \Swisseph\Constants::SE_SIDBIT_ECL_DATE) {
            // Alternative method (programmed 15 May 2020 in C code)
            // at t0, we have ayanamsha ayan_t0
            $lon_rad = Math::degToRad(Math::normAngleDeg($ayan_t0));
            $x = [cos($lon_rad), sin($lon_rad), 0.0];

            // Get epsilon for t0
            $eps_t0 = \Swisseph\Obliquity::meanObliquityRadFromJdTT($t0);

            // Convert ecliptic to equatorial: use coortrf2 with negative epsilon
            // This is equivalent to swi_coortrf(x, x, -eps) in C
            $x_tmp = [0.0, 0.0, 0.0];
            \Swisseph\Coordinates::coortrf2($x, $x_tmp, -sin($eps_t0), cos($eps_t0));
            $x = $x_tmp;            // Precess to J2000
            if (abs($t0 - 2451545.0) > 0.001) {
                \Swisseph\Precession::precess($x, $t0, 0, 1, $precModel); // J_TO_J2000
            }

            // Precess to date
            if (abs($jd_tt - 2451545.0) > 0.001) {
                \Swisseph\Precession::precess($x, $jd_tt, 0, -1, $precModel); // J2000_TO_J
            }

            // Epsilon of date
            $eps_date = \Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_tt);

            // Convert to ecliptic of date
            $x_ecl = \Swisseph\Coordinates::equatorialToEcliptic($x[0], $x[1], $x[2], $eps_date);

            // To polar
            $lon = atan2($x_ecl[1], $x_ecl[0]);
            $ayanamsha = Math::normAngleDeg(Math::radToDeg($lon));

        } else {
            // Original method (default, implemented 1999)
            // Precession is measured on the ecliptic of the start epoch t0 (ayan_t0)
            // This method is not really consistent because later this ayanamsha,
            // which is based on the ecliptic t0, will be applied to planetary
            // positions relative to the ecliptic of date.

            // Vernal point (tjd_et), cartesian equatorial J2000
            $x = [1.0, 0.0, 0.0];

            // Precess from jd_tt to J2000 (use specified precession model)
            if (abs($jd_tt - 2451545.0) > 0.001) {
                \Swisseph\Precession::precess($x, $jd_tt, 0, 1, $precModel); // direction = 1: J to J2000
            }

            // Precess from J2000 to t0 (use specified precession model)
            if (abs($t0 - 2451545.0) > 0.001) {
                \Swisseph\Precession::precess($x, $t0, 0, -1, $precModel); // direction = -1: J2000 to J
            }

            // Convert to ecliptic at t0
            $eps_t0 = \Swisseph\Obliquity::meanObliquityRadFromJdTT($t0);
            $x_ecl = \Swisseph\Coordinates::equatorialToEcliptic($x[0], $x[1], $x[2], $eps_t0);

            // Convert to polar (longitude)
            $lon_t0 = atan2($x_ecl[1], $x_ecl[0]);

            // Ayanamsha = -longitude + initial offset
            // Note: Do NOT normalize! Ayanamsha can be negative or > 360°
            $ayanamsha = -Math::radToDeg($lon_t0) + $ayan_t0;
        }

        // Apply correction for precession model differences
        // This accounts for SE_SIDBIT_NO_PREC_OFFSET flag
        $corr = self::getAyanamshaCorrection($t0, $prec_offset, $sidOpts, $precModel);

        // Get ayanamsa
        $ayanamsha = Math::normAngleDeg($ayanamsha - $corr);

        return $ayanamsha;
    }

    /**
     * Calculate ayanamsha correction based on precession model differences.
     * Port of get_aya_correction() from sweph.c
     *
     * @param float $t0 Reference epoch in TT
     * @param int $prec_offset Precession offset from ayanamsha data
     * @param int $sidOpts Sidereal options (SE_SIDBIT_*)
     * @param int|null $currentPrecModel Current precession model in use
     * @return float Correction in degrees
     */
    private static function getAyanamshaCorrection(
        float $t0,
        int $prec_offset,
        int $sidOpts,
        ?int $currentPrecModel
    ): float {
        // No correction needed if t0 is J2000
        if (abs($t0 - 2451545.0) < 0.001) {
            return 0.0;
        }

        // No correction if SE_SIDBIT_NO_PREC_OFFSET is set
        if ($sidOpts & \Swisseph\Constants::SE_SIDBIT_NO_PREC_OFFSET) {
            return 0.0;
        }

        // No correction if prec_offset is 0 or negative
        if ($prec_offset <= 0) {
            return 0.0;
        }

        // No correction if current model matches prec_offset
        if ($currentPrecModel === $prec_offset) {
            return 0.0;
        }

        // Calculate correction: difference between current and offset model
        // Vernal point at t0, cartesian equatorial
        $x = [1.0, 0.0, 0.0];

        // Precess to J2000 using current model
        \Swisseph\Precession::precess($x, $t0, 0, 1, $currentPrecModel); // J_TO_J2000

        // Precess back to t0 using prec_offset model
        \Swisseph\Precession::precess($x, $t0, 0, -1, $prec_offset); // J2000_TO_J

        // Convert to ecliptic at t0
        $eps_t0 = \Swisseph\Obliquity::meanObliquityRadFromJdTT($t0);
        $x_ecl = \Swisseph\Coordinates::equatorialToEcliptic($x[0], $x[1], $x[2], $eps_t0);

        // To polar
        $lon = atan2($x_ecl[1], $x_ecl[0]);
        $corr = Math::radToDeg($lon);

        // Signed value near 0
        if ($corr > 350.0) {
            $corr -= 360.0;
        }

        return $corr;
    }
}
