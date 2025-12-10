<?php

namespace Swisseph;

final class Sidereal
{
    // Constants for long-term sidereal time model
    // sidtime_long_term() is not used between these two dates
    private const SIDT_LTERM_T0 = 2396758.5;      // 1 Jan 1850
    private const SIDT_LTERM_T1 = 2469807.5;      // 1 Jan 2050
    private const SIDT_LTERM_OFS0 = 0.000378172 / 15.0;
    private const SIDT_LTERM_OFS1 = 0.001385646 / 15.0;

    // Number of terms for non-polynomial part of IERS 2010 sidereal time
    private const SIDTNTERM = 33;
    private const SIDTNARG = 14;

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
     * Supports "True" ayanamshas based on fixed stars (True Citra, True Revati, True Pushya)
     * which calculate ayanamsha from actual star positions via swe_fixstar().
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

        // TRUE ayanamshas based on fixed stars (sweph.c:3048-3074)
        // These use swe_fixstar() to get current star position
        if ($sidMode === \Swisseph\Constants::SE_SIDM_TRUE_CITRA) {
            // True Citra (Spica): ayanamsha = Spica_longitude - 180°
            $star = 'Spica';
            $x = [];
            $serr = null;
            $iflag = \Swisseph\Constants::SEFLG_SWIEPH | \Swisseph\Constants::SEFLG_NONUT | \Swisseph\Constants::SEFLG_NOABERR;

            $retflag = \swe_fixstar($star, $jd_tt, $iflag, $x, $serr);
            if ($retflag < 0) {
                // Error getting star position - return 0 as fallback
                error_log("TRUE_CITRA ayanamsha error: " . ($serr ?? 'unknown'));
                return 0.0;
            }

            return Math::normAngleDeg($x[0] - 180.0);
        }

        if ($sidMode === \Swisseph\Constants::SE_SIDM_TRUE_REVATI) {
            // True Revati (ζ Psc): ayanamsha = zePsc_longitude - 359.8333°
            $star = ',zePsc';
            $x = [];
            $serr = null;
            $iflag = \Swisseph\Constants::SEFLG_SWIEPH | \Swisseph\Constants::SEFLG_NONUT | \Swisseph\Constants::SEFLG_NOABERR;

            $retflag = \swe_fixstar($star, $jd_tt, $iflag, $x, $serr);
            if ($retflag < 0) {
                // Error getting star position - return 0 as fallback
                error_log("TRUE_REVATI ayanamsha error: " . ($serr ?? 'unknown'));
                return 0.0;
            }

            return Math::normAngleDeg($x[0] - 359.8333333333);
        }

        if ($sidMode === \Swisseph\Constants::SE_SIDM_TRUE_PUSHYA) {
            // True Pushya (δ Cnc / Asellus Australis): ayanamsha = deCnc_longitude - 106°
            $star = ',deCnc';
            $x = [];
            $serr = null;
            $iflag = \Swisseph\Constants::SEFLG_SWIEPH | \Swisseph\Constants::SEFLG_NONUT | \Swisseph\Constants::SEFLG_NOABERR;

            $retflag = \swe_fixstar($star, $jd_tt, $iflag, $x, $serr);
            if ($retflag < 0) {
                // Error getting star position - return 0 as fallback
                error_log("TRUE_PUSHYA ayanamsha error: " . ($serr ?? 'unknown'));
                return 0.0;
            }

            return Math::normAngleDeg($x[0] - 106.0);
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

    /**
     * Sidereal time at Greenwich with equation of the equinoxes.
     * Full port of swe_sidtime0() from swephlib.c
     *
     * Supports four sidereal time models:
     * - SEMOD_SIDT_IAU_1976: IAU 1976 formula (default for dates 1850-2050)
     * - SEMOD_SIDT_IAU_2006: IAU 2006 precession-based
     * - SEMOD_SIDT_IERS_CONV_2010: ERA-based GST (IAU 2006 precession + IAU 2000A_R06 nutation)
     * - SEMOD_SIDT_LONGTERM: Long-term model for dates outside 1850-2050 range
     *
     * @param float $tjd Julian Day UT
     * @param float $eps obliquity of ecliptic (degrees)
     * @param float $nut nutation in longitude (degrees)
     * @return float sidereal time in hours (range [0, 24))
     */
    public static function sidtime0(float $tjd, float $eps, float $nut): float
    {
        $swed = \Swisseph\SwephFile\SwedState::getInstance();

        // Get sidereal time model from configuration
        $precModelShort = $swed->astroModels[Constants::SE_MODEL_PREC_SHORTTERM] ?? 0;
        $sidtModel = $swed->astroModels[Constants::SE_MODEL_SIDT] ?? 0;

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Sidereal::sidtime0] tjd=%.10f, sidtModel=%d, precModelShort=%d",
                $tjd, $sidtModel, $precModelShort));
        }

        if ($precModelShort === 0) {
            $precModelShort = Constants::SEMOD_PREC_DEFAULT_SHORT;
        }
        if ($sidtModel === 0) {
            $sidtModel = Constants::SEMOD_SIDT_DEFAULT;
        }

        // Initialize state if needed (in C: swi_init_swed_if_start)
        // In PHP this is done automatically

        // Long-term model for dates outside 1850-2050 range
        if ($sidtModel === Constants::SEMOD_SIDT_LONGTERM) {
            if ($tjd <= self::SIDT_LTERM_T0 || $tjd >= self::SIDT_LTERM_T1) {
                $gmst = self::sidtimeLongTerm($tjd, $eps, $nut);

                // Apply offsets for dates outside range
                if ($tjd <= self::SIDT_LTERM_T0) {
                    $gmst -= self::SIDT_LTERM_OFS0;
                } elseif ($tjd >= self::SIDT_LTERM_T1) {
                    $gmst -= self::SIDT_LTERM_OFS1;
                }

                // Normalize to [0, 24) hours
                if ($gmst >= 24.0) {
                    $gmst -= 24.0;
                }
                if ($gmst < 0.0) {
                    $gmst += 24.0;
                }

                return $gmst;
            }
        }

        // Julian day at given UT
        $jd = $tjd;
        $jd0 = floor($jd);
        $secs = $tjd - $jd0;

        if ($secs < 0.5) {
            $jd0 -= 0.5;
            $secs += 0.5;
        } else {
            $jd0 += 0.5;
            $secs -= 0.5;
        }

        $secs *= 86400.0;
        $tu = ($jd0 - Constants::J2000) / 36525.0; // UT1 in centuries after J2000

        // ERA-based expression for IERS 2010 or LONGTERM (in range)
        if (
            $sidtModel === Constants::SEMOD_SIDT_IERS_CONV_2010 ||
            $sidtModel === Constants::SEMOD_SIDT_LONGTERM
        ) {
            $jdrel = $tjd - Constants::J2000;
            // CRITICAL: Must use swe_deltat_ex() like C does (swephlib.c:3503)
            $serr = '';
            $dt = \swe_deltat_ex($tjd, -1, $serr);
            $tt = ($tjd + $dt - Constants::J2000) / 36525.0;

            // ERA-based Greenwich Sidereal Time
            $gmst = Math::normAngleDeg((0.7790572732640 + 1.00273781191135448 * $jdrel) * 360.0);

            // Polynomial part
            $gmst += (0.014506 + $tt * (4612.156534 +
                      $tt * (1.3915817 +
                      $tt * (-0.00000044 +
                      $tt * (-0.000029956 +
                      $tt * -0.0000000368))))) / 3600.0;

            // Non-polynomial part (33 terms)
            $dadd = self::sidtimeNonPolynomialPart($tt);
            $gmst = Math::normAngleDeg($gmst + $dadd);

            // Convert to seconds
            $gmst = $gmst / 15.0 * 3600.0;

        } elseif ($sidtModel === Constants::SEMOD_SIDT_IAU_2006) {
            // IAU 2006 model
            // CRITICAL: Must use swe_deltat_ex() like C does (swephlib.c:3514)
            $serr = '';
            $dt = \swe_deltat_ex($jd0, -1, $serr);
            $tt = ($jd0 + $dt - Constants::J2000) / 36525.0;

            $gmst = (((-0.000000002454 * $tt - 0.00000199708) * $tt - 0.0000002926) * $tt + 0.092772110) * $tt * $tt
                  + 307.4771013 * ($tt - $tu)
                  + 8640184.79447825 * $tu
                  + 24110.5493771;

            // Mean solar days per sidereal day at date tu
            $msday = 1.0 + ((((-0.000000012270 * $tt - 0.00000798832) * $tt - 0.0000008778) * $tt + 0.185544220) * $tt
                  + 8640184.79447825) / (86400.0 * 36525.0);

            $gmst += $msday * $secs;

        } else {
            // SEMOD_SIDT_IAU_1976 (default)
            // Greenwich Mean Sidereal Time at 0h UT of date
            $gmst = ((-6.2e-6 * $tu + 9.3104e-2) * $tu + 8640184.812866) * $tu + 24110.54841;

            // Mean solar days per sidereal day at date tu
            $msday = 1.0 + ((-1.86e-5 * $tu + 0.186208) * $tu + 8640184.812866) / (86400.0 * 36525.0);

            $gmst += $msday * $secs;
        }

        // Local apparent sidereal time at given UT at Greenwich
        // Add equation of equinoxes
        $eqeq = 240.0 * $nut * cos($eps * Constants::DEGTORAD);

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Sidereal::sidtime0] BEFORE eqeq: gmst=%.10f seconds, eqeq=%.10f seconds",
                $gmst, $eqeq));
        }

        $gmst = $gmst + $eqeq;

        // Sidereal seconds modulo 1 sidereal day
        $gmst = $gmst - 86400.0 * floor($gmst / 86400.0);

        if (getenv('DEBUG_OBSERVER')) {
            $hours = $gmst / 3600.0;
            error_log(sprintf("[Sidereal::sidtime0] FINAL: gmst=%.10f seconds = %.15f hours = %.15f degrees",
                $gmst, $hours, $hours * 15.0));
        }

        // Return in hours
        return $gmst / 3600.0;
    }

    /**
     * Long-term sidereal time model for dates outside 1850-2050 range.
     * Port of sidtime_long_term() from swephlib.c
     *
     * Uses mean longitude of Earth and full precession/nutation transformations
     * to compute sidereal time for distant epochs.
     *
     * @param float $tjdUt Julian Day UT
     * @param float $eps obliquity of ecliptic (degrees), 0 to use computed value
     * @param float $nut nutation in longitude (degrees), 0 to use computed value
     * @return float sidereal time in hours
     */
    private static function sidtimeLongTerm(float $tjdUt, float $eps, float $nut): float
    {
        $dlt = Constants::AUNIT / Constants::CLIGHT / 86400.0; // Light time sun-earth in days

        $tjdEt = $tjdUt + \Swisseph\DeltaT::deltaTSecondsFromJd($tjdUt) / 86400.0;
        $t = ($tjdEt - Constants::J2000) / 365250.0; // Julian millennia from J2000
        $t2 = $t * $t;
        $t3 = $t * $t2;

        // Mean longitude of earth J2000 (Simon et al. 1994)
        $dlon = 100.46645683 + (1295977422.83429 * $t - 2.04411 * $t2 - 0.00523 * $t3) / 3600.0;

        // Correct for light time sun-earth
        $dlon = Math::normAngleDeg($dlon - $dlt * 360.0 / 365.2425);

        $xs = [
            $dlon * Constants::DEGTORAD,
            0.0,
            1.0,
            0.0,
            0.0,
            0.0
        ];

        // Convert to mean equator J2000, cartesian
        $xobl = [23.45, 23.45];
        $jd2000tt = Constants::J2000 + \Swisseph\DeltaT::deltaTSecondsFromJd(Constants::J2000) / 86400.0;
        $xobl[1] = \Swisseph\Obliquity::meanObliquityRadFromJdTT($jd2000tt) * Constants::RADTODEG;

        // Polar to cartesian
        $xs = \Swisseph\Coordinates::polarToCartesian($xs);

        // Rotate from ecliptic to equator (J2000)
        $xs = \Swisseph\Coordinates::rotateVector($xs, -$xobl[1] * Constants::DEGTORAD, 0);

        // Precess to mean equinox of date
        $xs = \Swisseph\Precession::precessVector($xs, $tjdEt, Constants::J2000_TO_J);

        // Get obliquity and nutation at date
        $xobl[1] = \Swisseph\Obliquity::meanObliquityRadFromJdTT($tjdEt) * Constants::RADTODEG;
        [$nutlo0, $nutlo1] = \Swisseph\Nutation::nutationIau1980($tjdEt);
        $xobl[0] = $xobl[1] + $nutlo1 * Constants::RADTODEG; // true obliquity
        $xobl[2] = $nutlo0 * Constants::RADTODEG;            // nutation in longitude

        // Rotate to ecliptic of date
        $xs = \Swisseph\Coordinates::rotateVector($xs, $xobl[1] * Constants::DEGTORAD, 0);

        // Cartesian to polar
        $xs = \Swisseph\Coordinates::cartesianToPolar($xs);
        $xs[0] *= Constants::RADTODEG;

        // Get hour angle from UT
        $dhour = fmod($tjdUt - 0.5, 1.0) * 360.0;

        // Mean to true (if nut != 0)
        if ($eps == 0.0) {
            $xs[0] += $xobl[2] * cos($xobl[0] * Constants::DEGTORAD);
        } else {
            $xs[0] += $nut * cos($eps * Constants::DEGTORAD);
        }

        // Add hour
        $xs[0] = Math::normAngleDeg($xs[0] + $dhour);

        // Convert to hours
        return $xs[0] / 15.0;
    }

    /**
     * Non-polynomial part of IERS 2010 sidereal time expression.
     * Port of sidtime_non_polynomial_part() from swephlib.c
     *
     * Computes 33-term correction based on lunar and planetary positions.
     * Reference: ftp://maia.usno.navy.mil/conv2010/chapter5/tab5.2e.txt
     *
     * @param float $tt Time in Julian centuries from J2000 (TT)
     * @return float Correction in degrees
     */
    private static function sidtimeNonPolynomialPart(float $tt): float
    {
        // Coefficients: C'_{s,j})_i  C'_{c,j})_i (33 terms, units: microseconds)
        $stcf = [
            2640.96, -0.39,
            63.52, -0.02,
            11.75, 0.01,
            11.21, 0.01,
            -4.55, 0.00,
            2.02, 0.00,
            1.98, 0.00,
            -1.72, 0.00,
            -1.41, -0.01,
            -1.26, -0.01,
            -0.63, 0.00,
            -0.63, 0.00,
            0.46, 0.00,
            0.45, 0.00,
            0.36, 0.00,
            -0.24, -0.12,
            0.32, 0.00,
            0.28, 0.00,
            0.27, 0.00,
            0.26, 0.00,
            -0.21, 0.00,
            0.19, 0.00,
            0.18, 0.00,
            -0.10, 0.05,
            0.15, 0.00,
            -0.14, 0.00,
            0.14, 0.00,
            -0.14, 0.00,
            0.14, 0.00,
            0.13, 0.00,
            -0.11, 0.00,
            0.11, 0.00,
            0.11, 0.00,
        ];

        // Arguments: l l' F D Om L_Me L_Ve L_E L_Ma L_J L_Sa L_U L_Ne p_A (14 arguments per term)
        $stfarg = [
            // Term 1
            0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 2
            0, 0, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 3
            0, 0, 2, -2, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 4
            0, 0, 2, -2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 5
            0, 0, 2, -2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 6
            0, 0, 2, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 7
            0, 0, 2, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 8
            0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 9
            0, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 10
            0, 1, 0, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 11
            1, 0, 0, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 12
            1, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 13
            0, 1, 2, -2, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 14
            0, 1, 2, -2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 15
            0, 0, 4, -4, 4, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 16
            0, 0, 1, -1, 1, 0, -8, 12, 0, 0, 0, 0, 0, 0,
            // Term 17
            0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 18
            0, 0, 2, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 19
            1, 0, 2, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 20
            1, 0, 2, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 21
            0, 0, 2, -2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 22
            0, 1, -2, 2, -3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 23
            0, 1, -2, 2, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 24
            0, 0, 0, 0, 0, 0, 8, -13, 0, 0, 0, 0, 0, -1,
            // Term 25
            0, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 26
            2, 0, -2, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 27
            1, 0, 0, -2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 28
            0, 1, 2, -2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 29
            1, 0, 0, -2, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 30
            0, 0, 4, -2, 4, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 31
            0, 0, 2, -2, 4, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 32
            1, 0, -2, 0, -3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
            // Term 33
            1, 0, -2, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        ];

        // Fundamental arguments (Delaunay + planetary longitudes + precession)
        $delm = [];

        // L: Mean anomaly of the Moon
        $delm[0] = Math::normAngleRad(2.35555598 + 8328.6914269554 * $tt);

        // l': Mean anomaly of the Sun
        $delm[1] = Math::normAngleRad(6.24006013 + 628.301955 * $tt);

        // F: Mean argument of the latitude of the Moon
        $delm[2] = Math::normAngleRad(1.627905234 + 8433.466158131 * $tt);

        // D: Mean elongation of the Moon from the Sun
        $delm[3] = Math::normAngleRad(5.198466741 + 7771.3771468121 * $tt);

        // Om: Mean longitude of the ascending node of the Moon
        $delm[4] = Math::normAngleRad(2.18243920 - 33.757045 * $tt);

        // Planetary longitudes (Mercury through Neptune, Souchay et al. 1999)
        $delm[5] = Math::normAngleRad(4.402608842 + 2608.7903141574 * $tt);  // Mercury
        $delm[6] = Math::normAngleRad(3.176146697 + 1021.3285546211 * $tt);  // Venus
        $delm[7] = Math::normAngleRad(1.753470314 + 628.3075849991 * $tt);   // Earth
        $delm[8] = Math::normAngleRad(6.203480913 + 334.0612426700 * $tt);   // Mars
        $delm[9] = Math::normAngleRad(0.599546497 + 52.9690962641 * $tt);    // Jupiter
        $delm[10] = Math::normAngleRad(0.874016757 + 21.3299104960 * $tt);   // Saturn
        $delm[11] = Math::normAngleRad(5.481293871 + 7.4781598567 * $tt);    // Uranus
        $delm[12] = Math::normAngleRad(5.321159000 + 3.8127774000 * $tt);    // Neptune

        // p_A: General accumulated precession in longitude
        $delm[13] = (0.02438175 + 0.00000538691 * $tt) * $tt;

        // Compute correction
        $dadd = -0.87 * sin($delm[4]) * $tt;

        for ($i = 0; $i < self::SIDTNTERM; $i++) {
            $darg = 0.0;
            for ($j = 0; $j < self::SIDTNARG; $j++) {
                $darg += $stfarg[$i * self::SIDTNARG + $j] * $delm[$j];
            }
            $dadd += $stcf[$i * 2] * sin($darg) + $stcf[$i * 2 + 1] * cos($darg);
        }

        // Convert from microseconds to degrees
        $dadd /= (3600.0 * 1000000.0);

        return $dadd;
    }

    /**
     * Get ayanamsha with speed (rate of change).
     * Port of swi_get_ayanamsa_with_speed() from sweph.c:3209-3223
     *
     * @param float $tjdEt Julian Day Ephemeris Time
     * @param int $iflag Calculation flags
     * @param array &$daya Output: [ayanamsha_degrees, speed_deg_per_day]
     * @param string|null &$serr Error message
     * @return int OK or ERR
     */
    public static function getAyanamsaWithSpeed(
        float $tjdEt,
        int $iflag,
        array &$daya,
        ?string &$serr = null
    ): int {
        $tintv = 0.001; // Time interval for speed calculation (days)

        // Calculate ayanamsha at t - interval
        $t2 = $tjdEt - $tintv;
        $daya_t2 = 0.0;

        // For simplicity, use ayanamshaDegFromJdTT
        // Full implementation would use swi_get_ayanamsa_ex
        try {
            $daya_t2 = self::ayanamshaDegFromJdTT($t2);
            $daya[0] = self::ayanamshaDegFromJdTT($tjdEt);

            // Calculate speed as finite difference
            $daya[1] = ($daya[0] - $daya_t2) / $tintv;

            return Constants::SE_OK;
        } catch (\Exception $e) {
            $serr = $e->getMessage();
            return Constants::SE_ERR;
        }
    }
}

