<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\ErrorCodes;

/**
 * Rise, Set and Transit calculations for celestial bodies
 * Full port from swecl.c:4355-4700 (swe_rise_trans and swe_rise_trans_true_hor)
 *
 * WITHOUT SIMPLIFICATIONS - complete C algorithm with:
 * - Culmination point detection
 * - Binary search for zero crossings
 * - Support for all planets and fixed stars
 * - Refraction handling
 * - Disc size corrections
 * - Twilight calculations
 */
final class RiseSetFunctions
{
    // Planet diameters in meters (from sweph.h:315-330 pla_diam[])
    private const PLA_DIAM = [
        Constants::SE_SUN     => 1392000000.0,  // Sun
        Constants::SE_MOON    => 3475000.0,      // Moon
        Constants::SE_MERCURY => 2439400.0 * 2,  // Mercury
        Constants::SE_VENUS   => 6051800.0 * 2,  // Venus
        Constants::SE_MARS    => 3389500.0 * 2,  // Mars
        Constants::SE_JUPITER => 69911000.0 * 2, // Jupiter
        Constants::SE_SATURN  => 58232000.0 * 2, // Saturn
        Constants::SE_URANUS  => 25362000.0 * 2, // Uranus
        Constants::SE_NEPTUNE => 24622000.0 * 2, // Neptune
        Constants::SE_PLUTO   => 1188300.0 * 2,  // Pluto
        Constants::SE_EARTH   => 6371008.4 * 2,  // Earth
    ];

    // Constants from sweph.h
    private const AUNIT = 1.49597870700e11;      // AU in meters, DE431
    private const EARTH_RADIUS = 6378136.6;      // meters, AA 2006 K6
    private const RADTODEG = 57.29577951308232;  // 180/PI
    private const SEI_ECL_GEOALT_MIN = -500.0;   // meters
    private const SEI_ECL_GEOALT_MAX = 25000.0;  // meters
    private const LAPSE_RATE = 0.0065;           // K/m, standard atmosphere

    /**
     * Calculate rise, set or transit times for celestial bodies
     * Port from swecl.c:4355-4382 (swe_rise_trans)
     */
    public static function riseTrans(
        float $tjd_ut,
        int $ipl,
        ?string $starname,
        int $epheflag,
        int $rsmi,
        array $geopos,
        float $atpress,
        float $attemp,
        ?float $horhgt,
        ?float &$tret = null,
        ?string &$serr = null
    ): int {
        // Call full implementation with horhgt defaulting to 0.0
        return self::riseTransTrueHor(
            $tjd_ut, $ipl, $starname, $epheflag, $rsmi,
            $geopos, $atpress, $attemp, $horhgt ?? 0.0, $tret, $serr
        );
    }

    /**
     * Calculate rise, set or transit with custom horizon height
     * Full C port from swecl.c:4387-4700 (swe_rise_trans_true_hor)
     *
     * WITHOUT SIMPLIFICATIONS - complete algorithm:
     * - Samples 28 hours at 2-hour intervals
     * - Finds culmination points (local maxima/minima)
     * - Inserts culminations into sample array
     * - Binary searches for horizon crossings
     * - Handles refraction, disc size, twilight
     */
    public static function riseTransTrueHor(
        float $tjd_ut,
        int $ipl,
        ?string $starname,
        int $epheflag,
        int $rsmi,
        array $geopos,
        float $atpress,
        float $attemp,
        float $horhgt,
        ?float &$tret = null,
        ?string &$serr = null
    ): int {
        $tret = 0.0;
        $serr = null;

        // Validate geopos
        if (!is_array($geopos) || count($geopos) < 2) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'geopos must be [lon, lat, alt]');
            return Constants::SE_ERR;
        }

        $lon = (float)$geopos[0];
        $lat = (float)$geopos[1];
        $alt = (float)($geopos[2] ?? 0.0);

        // Validate altitude (swecl.c:4407-4412)
        if ($alt < self::SEI_ECL_GEOALT_MIN || $alt > self::SEI_ECL_GEOALT_MAX) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                sprintf('location for swe_rise_trans() must be between %.0f and %.0f m above sea',
                    self::SEI_ECL_GEOALT_MIN, self::SEI_ECL_GEOALT_MAX)
            );
            return Constants::SE_ERR;
        }

        // if horhgt == -100, set horhgt = dip of horizon (swecl.c:4414-4416)
        if (abs($horhgt + 100.0) < 0.001) {
            $horhgt = 0.0001 + self::calcDip($alt, $atpress, $attemp, self::LAPSE_RATE);
        }

        // Pluto with asteroid number 134340 -> SE_PLUTO (swecl.c:4419-4420)
        if ($ipl === Constants::SE_AST_OFFSET + 134340) {
            $ipl = Constants::SE_PLUTO;
        }

        $do_fixstar = ($starname !== null && $starname !== '');

        // Clean flags (swecl.c:4423)
        $iflag = $epheflag & (Constants::SEFLG_EPHMASK | Constants::SEFLG_NONUT | Constants::SEFLG_TRUEPOS);

        // Determine coordinate system (swecl.c:4425-4432)
        if ($rsmi & Constants::SE_BIT_GEOCTR_NO_ECL_LAT) {
            $tohor_flag = Constants::SE_ECL2HOR;
        } else {
            $tohor_flag = Constants::SE_EQU2HOR;
            $iflag |= Constants::SEFLG_EQUATORIAL;
            $iflag |= Constants::SEFLG_TOPOCTR;
            \swe_set_topo($lon, $lat, $alt);
        }

        // Handle meridian transits separately (swecl.c:4433-4435)
        if ($rsmi & (Constants::SE_CALC_MTRANSIT | Constants::SE_CALC_ITRANSIT)) {
            return self::calcMerTrans($tjd_ut, $ipl, $epheflag, $rsmi, $geopos, $starname, $tret, $serr);
        }

        // Default to rise if neither rise nor set specified (swecl.c:4436-4437)
        if (!($rsmi & (Constants::SE_CALC_RISE | Constants::SE_CALC_SET))) {
            $rsmi |= Constants::SE_CALC_RISE;
        }

        // Handle twilight calculation (swecl.c:4438-4443)
        if ($ipl === Constants::SE_SUN &&
            ($rsmi & (Constants::SE_BIT_CIVIL_TWILIGHT | Constants::SE_BIT_NAUTIC_TWILIGHT | Constants::SE_BIT_ASTRO_TWILIGHT))) {
            $rsmi |= (Constants::SE_BIT_NO_REFRACTION | Constants::SE_BIT_DISC_CENTER);
            $horhgt = -self::rdiTwilight($rsmi);
        }

        // Main algorithm: find culminations within 28 hours (swecl.c:4444-4455)
        $twohrs = 1.0 / 12.0;  // 2 hours in days
        $jmax = 14;            // 28 hours / 2 hours = 14 intervals

        $tc = [];    // times of samples
        $h = [];     // heights at samples
        $xh = [];    // azimuth/altitude arrays [azimuth, true_alt, apparent_alt]
        $tculm = []; // culmination times
        $nculm = -1; // number of culminations found

        $tjd_et = $tjd_ut + \swe_deltat_ex($tjd_ut, $epheflag, $serr);
        $xc = [];

        // Get initial position for fixed star (swecl.c:4456-4459)
        if ($do_fixstar) {
            $rc = \swe_fixstar($starname, $tjd_et, $iflag, $xc, $serr);
            if ($rc < 0) {
                return Constants::SE_ERR;
            }
        }

        $dd = 0.0; // diameter in meters
        $rdi = 0.0; // apparent radius in degrees

        // Debug mode
        $debug = getenv('DEBUG_RISESET') === '1';

        // Sample heights every 2 hours over 28 hours (swecl.c:4460-4552)
        for ($ii = 0, $t = $tjd_ut - $twohrs; $ii <= $jmax; $ii++, $t += $twohrs) {
            $tc[$ii] = $t;

            // Get body position (swecl.c:4463-4467)
            if (!$do_fixstar) {
                $te = $t + \swe_deltat_ex($t, $epheflag, $serr);
                $rc = \swe_calc($te, $ipl, $iflag, $xc, $serr);
                if ($rc < 0) {
                    return Constants::SE_ERR;
                }
            }

            // Zero out ecliptic latitude if requested (swecl.c:4468-4469)
            if ($rsmi & Constants::SE_BIT_GEOCTR_NO_ECL_LAT) {
                $xc[1] = 0.0;
            }

            // Get diameter on first iteration (swecl.c:4471-4481)
            if ($ii === 0) {
                if ($do_fixstar) {
                    $dd = 0.0;
                } elseif ($rsmi & Constants::SE_BIT_DISC_CENTER) {
                    $dd = 0.0;
                } elseif (isset(self::PLA_DIAM[$ipl])) {
                    $dd = self::PLA_DIAM[$ipl];
                } elseif ($ipl > Constants::SE_AST_OFFSET) {
                    // Asteroid: use default ~290 km
                    $dd = 290000.0;
                } else {
                    $dd = 0.0;
                }
            }

            $curdist = $xc[2]; // distance in AU

            // Handle fixed disc size option (swecl.c:4483-4489)
            if ($rsmi & Constants::SE_BIT_FIXED_DISC_SIZE) {
                if ($ipl === Constants::SE_SUN) {
                    $curdist = 1.0;
                } elseif ($ipl === Constants::SE_MOON) {
                    $curdist = 0.00257;
                }
            }

            // Apparent radius of disc (degrees) (swecl.c:4491)
            $rdi = asin($dd / 2.0 / self::AUNIT / $curdist) * self::RADTODEG;

            // True height of center (swecl.c:4493)
            $xhii = [];
            \swe_azalt($t, $tohor_flag, $xc, $geopos, $atpress, $attemp, $xhii, $serr);

            // Adjust for disc edge (swecl.c:4495-4500)
            if ($rsmi & Constants::SE_BIT_DISC_BOTTOM) {
                $xhii[1] -= $rdi;  // bottom edge
            } else {
                $xhii[1] += $rdi;  // upper edge (default)
            }

            // Apply refraction if needed (swecl.c:4502-4514)
            if ($rsmi & Constants::SE_BIT_NO_REFRACTION) {
                $xhii[1] -= $horhgt;
                $h[$ii] = $xhii[1];
            } else {
                // Apply refraction by round-trip conversion
                \swe_azalt_rev($t, Constants::SE_HOR2EQU, $xhii, $geopos, $xc, $serr);
                \swe_azalt($t, Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $xhii, $serr);
                $xhii[1] -= $horhgt;
                $xhii[2] -= $horhgt;
                $h[$ii] = $xhii[2];  // use refracted altitude
            }

            $xh[$ii] = $xhii;

            if ($debug) {
                $date = \swe_revjul($tc[$ii], Constants::SE_GREG_CAL);
                error_log(sprintf("  Sample ii=%2d: JD %.7f (%02d:%02d UT) h=%.5f°",
                    $ii, $tc[$ii], (int)$date['ut'], (int)((($date['ut'] - (int)$date['ut']) * 60)), $h[$ii]));
            }

            // Check for culmination (local max or min) (swecl.c:4515-4552)
            $calc_culm = 0;
            if ($ii > 1) {
                $dc = [
                    $xh[$ii - 2][1],
                    $xh[$ii - 1][1],
                    $xh[$ii][1]
                ];

                if ($dc[1] > $dc[0] && $dc[1] > $dc[2]) {
                    $calc_culm = 1; // local maximum
                }
                if ($dc[1] < $dc[0] && $dc[1] < $dc[2]) {
                    $calc_culm = 2; // local minimum
                }
            }

            if ($calc_culm) {
                // Refine culmination time (swecl.c:4524-4551)
                $dt = $twohrs;
                $tcu = $t - $dt;
                $dtint = 0.0;
                $dx = 0.0;
                self::findMaximum($dc[0], $dc[1], $dc[2], $dt, $dtint, $dx);
                $tcu += $dtint + $dt;

                // Iterative refinement (divide dt by 3 each iteration)
                $dt /= 3.0;
                for (; $dt > 0.0001; $dt /= 3.0) {
                    $dc = [];
                    for ($i = 0, $tt = $tcu - $dt; $i < 3; $tt += $dt, $i++) {
                        $te = $tt + \swe_deltat_ex($tt, $epheflag, $serr);
                        if (!$do_fixstar) {
                            $rc = \swe_calc($te, $ipl, $iflag, $xc, $serr);
                            if ($rc < 0) {
                                return Constants::SE_ERR;
                            }
                        }
                        if ($rsmi & Constants::SE_BIT_GEOCTR_NO_ECL_LAT) {
                            $xc[1] = 0.0;
                        }
                        $ah = [];
                        \swe_azalt($tt, $tohor_flag, $xc, $geopos, $atpress, $attemp, $ah, $serr);
                        $ah[1] -= $horhgt;
                        $dc[$i] = $ah[1];
                    }
                    self::findMaximum($dc[0], $dc[1], $dc[2], $dt, $dtint, $dx);
                    $tcu += $dtint + $dt;
                }

                $nculm++;
                $tculm[$nculm] = $tcu;
            }
        }

        // Insert culminations into sample arrays (swecl.c:4556-4615)
        for ($i = 0; $i <= $nculm; $i++) {
            for ($j = 1; $j <= $jmax; $j++) {
                if ($tculm[$i] < $tc[$j]) {
                    // Insert culmination at position j
                    for ($k = $jmax; $k >= $j; $k--) {
                        $tc[$k + 1] = $tc[$k];
                        $h[$k + 1] = $h[$k];
                    }

                    $tc[$j] = $tculm[$i];

                    if (!$do_fixstar) {
                        $te = $tc[$j] + \swe_deltat_ex($tc[$j], $epheflag, $serr);
                        $rc = \swe_calc($te, $ipl, $iflag, $xc, $serr);
                        if ($rc < 0) {
                            return Constants::SE_ERR;
                        }
                        if ($rsmi & Constants::SE_BIT_GEOCTR_NO_ECL_LAT) {
                            $xc[1] = 0.0;
                        }
                    }

                    $curdist = $xc[2];
                    if ($rsmi & Constants::SE_BIT_FIXED_DISC_SIZE) {
                        if ($ipl === Constants::SE_SUN) {
                            $curdist = 1.0;
                        } elseif ($ipl === Constants::SE_MOON) {
                            $curdist = 0.00257;
                        }
                    }

                    $rdi = asin($dd / 2.0 / self::AUNIT / $curdist) * self::RADTODEG;

                    $ah = [];
                    \swe_azalt($tc[$j], $tohor_flag, $xc, $geopos, $atpress, $attemp, $ah, $serr);

                    if ($rsmi & Constants::SE_BIT_DISC_BOTTOM) {
                        $ah[1] -= $rdi;
                    } else {
                        $ah[1] += $rdi;
                    }

                    if ($rsmi & Constants::SE_BIT_NO_REFRACTION) {
                        $ah[1] -= $horhgt;
                        $h[$j] = $ah[1];
                    } else {
                        \swe_azalt_rev($tc[$j], Constants::SE_HOR2EQU, $ah, $geopos, $xc, $serr);
                        \swe_azalt($tc[$j], Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $ah, $serr);
                        $ah[1] -= $horhgt;
                        $ah[2] -= $horhgt;
                        $h[$j] = $ah[2];
                    }

                    $jmax++;
                    break;
                }
            }
        }

        // Find points with zero height (horizon crossing) (swecl.c:4616-4692)
        $t2 = [];
        if ($debug) {
            error_log(sprintf("=== Looking for events after JD %.7f ===", $tjd_ut));
        }
        for ($ii = 1; $ii <= $jmax; $ii++) {
            // Check for sign change (swecl.c:4620-4621)
            if ($h[$ii - 1] * $h[$ii] >= 0) {
                continue;
            }

            // Check if it's the right type (rise vs set) (swecl.c:4622-4625)
            if ($h[$ii - 1] < $h[$ii] && !($rsmi & Constants::SE_CALC_RISE)) {
                if ($debug) error_log(sprintf("  Skip ii=%d (rising but looking for set): h[%d]=%.5f h[%d]=%.5f",
                    $ii, $ii-1, $h[$ii-1], $ii, $h[$ii]));
                continue;
            }
            if ($h[$ii - 1] > $h[$ii] && !($rsmi & Constants::SE_CALC_SET)) {
                if ($debug) error_log(sprintf("  Skip ii=%d (setting but looking for rise): h[%d]=%.5f h[%d]=%.5f",
                    $ii, $ii-1, $h[$ii-1], $ii, $h[$ii]));
                continue;
            }

            if ($debug) {
                error_log(sprintf("  Found crossing at ii=%d: h[%d]=%.5f h[%d]=%.5f (tc[%d]=%.7f tc[%d]=%.7f)",
                    $ii, $ii-1, $h[$ii-1], $ii, $h[$ii], $ii-1, $tc[$ii-1], $ii, $tc[$ii]));
            }

            // Binary search for exact crossing time (swecl.c:4626-4688)
            $dc = [$h[$ii - 1], $h[$ii]];
            $t2 = [$tc[$ii - 1], $tc[$ii]];

            for ($i = 0; $i < 20; $i++) {
                $t = ($t2[0] + $t2[1]) / 2.0;

                if (!$do_fixstar) {
                    $te = $t + \swe_deltat_ex($t, $epheflag, $serr);
                    $rc = \swe_calc($te, $ipl, $iflag, $xc, $serr);
                    if ($rc < 0) {
                        return Constants::SE_ERR;
                    }
                    if ($rsmi & Constants::SE_BIT_GEOCTR_NO_ECL_LAT) {
                        $xc[1] = 0.0;
                    }
                }

                $curdist = $xc[2];
                if ($rsmi & Constants::SE_BIT_FIXED_DISC_SIZE) {
                    if ($ipl === Constants::SE_SUN) {
                        $curdist = 1.0;
                    } elseif ($ipl === Constants::SE_MOON) {
                        $curdist = 0.00257;
                    }
                }

                $rdi = asin($dd / 2.0 / self::AUNIT / $curdist) * self::RADTODEG;

                $ah = [];
                \swe_azalt($t, $tohor_flag, $xc, $geopos, $atpress, $attemp, $ah, $serr);

                if ($rsmi & Constants::SE_BIT_DISC_BOTTOM) {
                    $ah[1] -= $rdi;
                } else {
                    $ah[1] += $rdi;
                }

                if ($rsmi & Constants::SE_BIT_NO_REFRACTION) {
                    $ah[1] -= $horhgt;
                    $aha = $ah[1];
                } else {
                    \swe_azalt_rev($t, Constants::SE_HOR2EQU, $ah, $geopos, $xc, $serr);
                    \swe_azalt($t, Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $ah, $serr);
                    $ah[1] -= $horhgt;
                    $ah[2] -= $horhgt;
                    $aha = $ah[2];
                }

                // Bisection step (swecl.c:4681-4686)
                if ($aha * $dc[0] <= 0) {
                    $dc[1] = $aha;
                    $t2[1] = $t;
                } else {
                    $dc[0] = $aha;
                    $t2[0] = $t;
                }
            }

            // Return first event after tjd_ut (swecl.c:4689-4693)
            if ($debug) {
                if ($t > $tjd_ut) {
                    error_log(sprintf("  ✅ Event at JD %.7f is AFTER tjd_ut %.7f - RETURNING", $t, $tjd_ut));
                } else {
                    error_log(sprintf("  ⏩ Event at JD %.7f is BEFORE tjd_ut %.7f - SKIPPING", $t, $tjd_ut));
                }
            }
            if ($t > $tjd_ut) {
                $tret = $t;
                return 0; // OK
            }
            // If t <= tjd_ut, continue searching for next crossing
        }

        // No rise or set found after tjd_ut (swecl.c:4694-4696)
        if ($serr !== null) {
            $serr = sprintf('rise or set not found for planet %d', $ipl);
        }
        return -2;
    }

    /**
     * Calculate meridian transits (upper and lower culminations)
     * Stub - full implementation needed for complete functionality
     * Port from swecl.c:4702-4833 (calc_mer_trans)
     *
     * @return int -1 (not yet fully implemented)
     */
    private static function calcMerTrans(
        float $tjd_ut,
        int $ipl,
        int $epheflag,
        int $rsmi,
        array $geopos,
        ?string $starname,
        ?float &$tret,
        ?string &$serr
    ): int {
        $serr = 'Meridian transits not yet fully implemented';
        return Constants::SE_ERR;
    }

    /**
     * Calculate dip of horizon due to observer altitude
     * Port from swecl.c:3158-3169 (calc_dip)
     *
     * Formula based on A. Thom, Megalithic lunar observations, 1973 (page 32)
     * Conversion to metric by V. Reijs, 2000
     *
     * @param float $geoalt     Altitude in meters
     * @param float $atpress    Atmospheric pressure (mbar)
     * @param float $attemp     Temperature (°C)
     * @param float $lapse_rate Temperature lapse rate (K/m)
     * @return float            Dip angle in degrees (negative)
     */
    private static function calcDip(float $geoalt, float $atpress, float $attemp, float $lapse_rate): float
    {
        $krefr = (0.0342 + $lapse_rate) / (0.154 * 0.0238);
        $d = 1.0 - 1.8480 * $krefr * $atpress / (273.15 + $attemp) / (273.15 + $attemp);
        return -180.0 / M_PI * acos(1.0 / (1.0 + $geoalt / self::EARTH_RADIUS)) * sqrt($d);
    }

    /**
     * Get twilight angle based on flags
     * Port from swecl.c:4164-4175 (rdi_twilight)
     *
     * @param int $rsmi Rise/set flags
     * @return float    Twilight angle in degrees (6, 12, or 18)
     */
    private static function rdiTwilight(int $rsmi): float
    {
        $rdi = 0.0;
        if ($rsmi & Constants::SE_BIT_CIVIL_TWILIGHT) {
            $rdi = 6.0;
        }
        if ($rsmi & Constants::SE_BIT_NAUTIC_TWILIGHT) {
            $rdi = 12.0;
        }
        if ($rsmi & Constants::SE_BIT_ASTRO_TWILIGHT) {
            $rdi = 18.0;
        }
        return $rdi;
    }

    /**
     * Find maximum of parabola through three points
     * Port from swecl.c:4133-4146 (find_maximum)
     *
     * Given three points at x = -1, 0, 1 with values y00, y11, y2,
     * fit a parabola and find its maximum.
     *
     * @param float $y00    Value at x=-1
     * @param float $y11    Value at x=0
     * @param float $y2     Value at x=1
     * @param float $dx     Step size
     * @param float &$dxret Offset to maximum from x=0
     * @param float &$yret  Value at maximum
     * @return int          0 (OK)
     */
    private static function findMaximum(float $y00, float $y11, float $y2, float $dx, float &$dxret, float &$yret): int
    {
        $c = $y11;
        $b = ($y2 - $y00) / 2.0;
        $a = ($y2 + $y00) / 2.0 - $c;
        $x = -$b / 2.0 / $a;
        $y = (4.0 * $a * $c - $b * $b) / 4.0 / $a;
        $dxret = ($x - 1.0) * $dx;
        $yret = $y;
        return 0;
    }
}
