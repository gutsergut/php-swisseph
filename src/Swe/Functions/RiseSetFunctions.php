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
     *
     * Automatically selects fast or slow algorithm:
     * - rise_set_fast() for planets Sun-True_Node at latitudes <60° (Moon) or <65° (Sun)
     * - riseTransTrueHor() for extreme latitudes, fixed stars, or twilight
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
        $do_fixstar = ($starname !== null && $starname !== '');

        // Simple fast algorithm for risings and settings of
        // - planets Sun, Moon, Mercury - Pluto + Lunar Nodes
        // Does not work well for geographic latitudes
        // > 65 N/S for the Sun
        // > 60 N/S for the Moon and the planets
        // Beyond these limits, some risings or settings may be missed.
        // (swecl.c:4364-4381)
        if (!$do_fixstar
            && ($rsmi & (Constants::SE_CALC_RISE | Constants::SE_CALC_SET))
            && !($rsmi & Constants::SE_BIT_FORCE_SLOW_METHOD)
            && !($rsmi & (Constants::SE_BIT_CIVIL_TWILIGHT | Constants::SE_BIT_NAUTIC_TWILIGHT | Constants::SE_BIT_ASTRO_TWILIGHT))
            && ($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_TRUE_NODE)
            && (abs($geopos[1]) <= 60.0 || ($ipl === Constants::SE_SUN && abs($geopos[1]) <= 65.0))
        ) {
            return self::riseSetFast($tjd_ut, $ipl, $epheflag, $rsmi, $geopos, $atpress, $attemp, $tret, $serr);
        }

        // Use slow accurate method for extreme latitudes and fixed stars
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
            \swe_azalt($t, $tohor_flag, $geopos, $atpress, $attemp, $xc, $xhii);

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
                \swe_azalt_rev($t, Constants::SE_HOR2EQU, $geopos, $xhii, $xc);
                \swe_azalt($t, Constants::SE_EQU2HOR, $geopos, $atpress, $attemp, $xc, $xhii);
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
                        \swe_azalt($tt, $tohor_flag, $geopos, $atpress, $attemp, $xc, $ah);
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
                    \swe_azalt($tc[$j], $tohor_flag, $geopos, $atpress, $attemp, $xc, $ah);

                    if ($rsmi & Constants::SE_BIT_DISC_BOTTOM) {
                        $ah[1] -= $rdi;
                    } else {
                        $ah[1] += $rdi;
                    }

                    if ($rsmi & Constants::SE_BIT_NO_REFRACTION) {
                        $ah[1] -= $horhgt;
                        $h[$j] = $ah[1];
                    } else {
                        \swe_azalt_rev($tc[$j], Constants::SE_HOR2EQU, $geopos, $ah, $xc);
                        \swe_azalt($tc[$j], Constants::SE_EQU2HOR, $geopos, $atpress, $attemp, $xc, $ah);
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
                \swe_azalt($t, $tohor_flag, $geopos, $atpress, $attemp, $xc, $ah);

                if ($rsmi & Constants::SE_BIT_DISC_BOTTOM) {
                    $ah[1] -= $rdi;
                } else {
                    $ah[1] += $rdi;
                }

                if ($rsmi & Constants::SE_BIT_NO_REFRACTION) {
                    $ah[1] -= $horhgt;
                    $aha = $ah[1];
                } else {
                    \swe_azalt_rev($t, Constants::SE_HOR2EQU, $geopos, $ah, $xc);
                    \swe_azalt($t, Constants::SE_EQU2HOR, $geopos, $atpress, $attemp, $xc, $ah);
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
     * Calculate meridian transits (upper and lower culmination)
     * Full C port from swecl.c:4688-4755 (calc_mer_trans)
     *
     * WITHOUT SIMPLIFICATIONS - complete algorithm:
     * - Calculates sidereal time at observer location
     * - Iterates to find when RA matches ARMC (or ARMC+180° for lower transit)
     * - Handles both planets and fixed stars
     * - 4 iterations for convergence
     *
     * @param float $tjd_ut     Starting time (UT)
     * @param int $ipl          Planet number
     * @param int $epheflag     Ephemeris flags
     * @param int $rsmi         Rise/set flags (SE_CALC_MTRANSIT or SE_CALC_ITRANSIT)
     * @param array $geopos     Geographic position [lon, lat, alt]
     * @param string|null $starname Fixed star name (null for planets)
     * @param float|null $tret  Output: time of transit
     * @param string|null $serr Error string
     * @return int              OK (0) or SE_ERR (-1)
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
        $tret = 0.0;
        $serr = null;

        // Ephemeris flags: equatorial + topocentric (swecl.c:4701-4702)
        $iflag = $epheflag & Constants::SEFLG_EPHMASK;
        $iflag |= (Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR);

        $do_fixstar = ($starname !== null && $starname !== '');

        // Initial sidereal time at observer location (swecl.c:4703-4708)
        $armc0 = \swe_sidtime($tjd_ut) + $geopos[0] / 15.0;
        if ($armc0 >= 24.0) {
            $armc0 -= 24.0;
        }
        if ($armc0 < 0.0) {
            $armc0 += 24.0;
        }
        $armc0 *= 15.0; // convert hours to degrees

        // Get initial position (swecl.c:4709-4716)
        $tjd_et = $tjd_ut + \swe_deltat_ex($tjd_ut, $epheflag, $serr);
        $x0 = [];

        if ($do_fixstar) {
            $rc = \swe_fixstar($starname, $tjd_et, $iflag, $x0, $serr);
            if ($rc < 0) {
                return Constants::SE_ERR;
            }
        } else {
            $rc = \swe_calc($tjd_et, $ipl, $iflag, $x0, $serr);
            if ($rc < 0) {
                return Constants::SE_ERR;
            }
        }

        // Meridian transits: iterate to find when RA = ARMC (swecl.c:4717-4744)
        $x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $x[0] = $x0[0]; // RA
        $x[1] = $x0[1]; // Dec

        $t = $tjd_ut;
        $arxc = $armc0;

        // For lower transit (ITRANSIT), use ARMC + 180° (swecl.c:4722-4723)
        if ($rsmi & Constants::SE_CALC_ITRANSIT) {
            $arxc = \swe_degnorm($arxc + 180.0);
        }

        // Iterate 4 times for convergence (swecl.c:4724-4744)
        for ($i = 0; $i < 4; $i++) {
            // Meridian distance: difference between RA and ARMC (swecl.c:4725)
            $mdd = \swe_degnorm($x[0] - $arxc);

            // After first iteration, avoid jumping to next day (swecl.c:4726-4727)
            if ($i > 0 && $mdd > 180.0) {
                $mdd -= 360.0;
            }

            // Advance time: ~361°/day (slightly more than 360° due to solar motion) (swecl.c:4728)
            $t += $mdd / 361.0;

            // Recalculate sidereal time at new time (swecl.c:4729-4733)
            $armc = \swe_sidtime($t) + $geopos[0] / 15.0;
            if ($armc >= 24.0) {
                $armc -= 24.0;
            }
            if ($armc < 0.0) {
                $armc += 24.0;
            }
            $armc *= 15.0; // hours to degrees

            $arxc = $armc;

            // For lower transit, add 180° (swecl.c:4735-4736)
            if ($rsmi & Constants::SE_CALC_ITRANSIT) {
                $arxc = \swe_degnorm($arxc + 180.0);
            }

            // Recalculate planet/star position (swecl.c:4737-4740)
            // For fixed stars, position doesn't change significantly, so we skip recalc
            if (!$do_fixstar) {
                $te = $t + \swe_deltat_ex($t, $epheflag, $serr);
                $rc = \swe_calc($te, $ipl, $iflag, $x, $serr);
                if ($rc < 0) {
                    return Constants::SE_ERR;
                }
            }
        }

        $tret = $t;
        return 0; // OK
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
     * Calculate apparent radius plus refraction for Sun/Moon disc
     * Port from swecl.c:4176-4194 (get_sun_rad_plus_refr)
     *
     * @param int $ipl Planet number
     * @param float $dd Distance in AU
     * @param int $rsmi Rise/set flags
     * @param float $refr Refraction in degrees
     * @return float Apparent radius in degrees (with sign adjustments)
     */
    private static function getSunRadPlusRefr(int $ipl, float $dd, int $rsmi, float $refr): float
    {
        $rdi = 0.0;

        // Fixed disc size option (swecl.c:4179-4184)
        if ($rsmi & Constants::SE_BIT_FIXED_DISC_SIZE) {
            if ($ipl === Constants::SE_SUN) {
                $dd = 1.0;
            } elseif ($ipl === Constants::SE_MOON) {
                $dd = 0.00257;
            }
        }

        // Apparent radius of disc (swecl.c:4185-4187)
        if (!($rsmi & Constants::SE_BIT_DISC_CENTER)) {
            $diameter = self::PLA_DIAM[$ipl] ?? 0.0;
            $rdi = asin($diameter / 2.0 / self::AUNIT / $dd) * self::RADTODEG;
        }

        // Disc bottom flag (swecl.c:4188-4189)
        if ($rsmi & Constants::SE_BIT_DISC_BOTTOM) {
            $rdi = -$rdi;
        }

        // Add refraction unless disabled (swecl.c:4190-4192)
        if (!($rsmi & Constants::SE_BIT_NO_REFRACTION)) {
            $rdi += $refr;
        }

        return $rdi;
    }

    /**
     * Fast algorithm for rise/set of planets Sun-Pluto + Lunar Nodes
     * Port from swecl.c:4203-4325 (rise_set_fast)
     *
     * Simple fast algorithm for risings and settings.
     * Does not work well for geographic latitudes:
     * > 65 N/S for the Sun
     * > 60 N/S for the Moon and the planets
     *
     * Called only for latitudes smaller than this.
     * Uses semi-diurnal arc and iterative refinement.
     *
     * WITHOUT SIMPLIFICATIONS - full C port with:
     * - Semi-diurnal arc calculation
     * - Sidereal time and meridian distance
     * - Iterative refinement (2 loops for planets, 4 for Moon)
     * - Refraction and disc size corrections
     * - Second run if event is before start time
     */
    private static function riseSetFast(
        float $tjd_ut,
        int $ipl,
        int $epheflag,
        int $rsmi,
        array $dgeo,
        float $atpress,
        float $attemp,
        ?float &$tret = null,
        ?string &$serr = null
    ): int {
        $tret = 0.0;
        $serr = null;        // Extract ephemeris flags (swecl.c:4214)
        $iflag = $epheflag & (Constants::SEFLG_JPLEPH | Constants::SEFLG_SWIEPH | Constants::SEFLG_MOSEPH);
        $iflagtopo = $iflag | Constants::SEFLG_EQUATORIAL;

        // Number of iterations: 2 for planets, 4 for Moon (swecl.c:4222-4225)
        $nloop = ($ipl === Constants::SE_MOON) ? 4 : 2;

        // Rise or set? (swecl.c:4226-4227)
        $facrise = ($rsmi & Constants::SE_CALC_SET) ? -1 : 1;

        // Setup topocentric flags (swecl.c:4228-4231)
        if (!($rsmi & Constants::SE_BIT_GEOCTR_NO_ECL_LAT)) {
            $iflagtopo |= Constants::SEFLG_TOPOCTR;
            \swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);
        }

        $tjd_ut0 = $tjd_ut;
        $is_second_run = false;
        $tr = 0.0;

        // Main algorithm with retry loop (swecl.c:4232)
        do {
            // Get body position at start time (swecl.c:4233-4234)
            $xx = [];
            if (\swe_calc_ut($tjd_ut, $ipl, $iflagtopo, $xx, $serr) < 0) {
                return Constants::SE_ERR;
            }

            // Declination (swecl.c:4241)
            $decl = $xx[1];

            // Semi-diurnal arc (swecl.c:4242-4252)
            // The diurnal arc is a bit fuzzy:
            // - because the object changes declination during the day
            // - because there is refraction of light
            // Nevertheless this works well as soon as the object is not
            // circumpolar or near-circumpolar
            $sda = -tan(deg2rad($dgeo[1])) * tan(deg2rad($decl));

            if ($sda >= 1.0) {
                // Actually sda = 0°, but we give it a value of 10°
                // to account for refraction. Value 0 would cause problems (swecl.c:4244-4247)
                $sda = 10.0;
            } elseif ($sda <= -1.0) {
                $sda = 180.0;
            } else {
                $sda = rad2deg(acos($sda));
            }

            // Sidereal time at tjd_ut (swecl.c:4253-4254)
            $armc = \Swisseph\Math::normAngleDeg(\swe_sidtime($tjd_ut) * 15.0 + $dgeo[0]);

            // Meridian distance of object (swecl.c:4255-4256)
            $md = \Swisseph\Math::normAngleDeg($xx[0] - $armc);

            // Meridian distance at rise/set (swecl.c:4257)
            $mdrise = \Swisseph\Math::normAngleDeg($sda * $facrise);

            // Delta meridian distance (swecl.c:4259)
            $dmd = \Swisseph\Math::normAngleDeg($md - $mdrise);

            // Avoid the risk of getting the event of next day (swecl.c:4260-4270)
            if ($dmd > 358.0) {
                $dmd -= 360.0;
            }

            // Rough subsequent rising/setting time (swecl.c:4271-4272)
            $tr = $tjd_ut + $dmd / 360.0;

            // Calculate refraction for horizon (swecl.c:4276-4286)
            if ($atpress == 0.0) {
                // Estimate atmospheric pressure (swecl.c:4280-4283)
                $atpress = 1013.25 * pow(1.0 - 0.0065 * $dgeo[2] / 288.0, 5.255);
            }

            $xx_refr = [0.000001, 0.0]; // input: very small altitude
            \swe_refrac_extended($xx_refr[0], 0.0, $atpress, $attemp, self::LAPSE_RATE, Constants::SE_APP_TO_TRUE, $xx_refr);
            $refr = $xx_refr[1] - $xx_refr[0];

            // Coordinate system flags (swecl.c:4287-4295)
            if ($rsmi & Constants::SE_BIT_GEOCTR_NO_ECL_LAT) {
                $tohor_flag = Constants::SE_ECL2HOR;
                $iflagtopo = $iflag;
            } else {
                $tohor_flag = Constants::SE_EQU2HOR; // this is more efficient
                $iflagtopo = $iflag | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_TOPOCTR;
                \swe_set_topo($dgeo[0], $dgeo[1], $dgeo[2]);
            }

            // Iterative refinement (swecl.c:4296-4315)
            for ($i = 0; $i < $nloop; $i++) {
                // Get body position at trial time (swecl.c:4297-4298)
                if (\swe_calc_ut($tr, $ipl, $iflagtopo, $xx, $serr) < 0) {
                    return Constants::SE_ERR;
                }

                // Zero ecliptic latitude if requested (swecl.c:4299-4300)
                if ($rsmi & Constants::SE_BIT_GEOCTR_NO_ECL_LAT) {
                    $xx[1] = 0.0;
                }

                // Get apparent radius plus refraction (swecl.c:4301)
                $rdi = self::getSunRadPlusRefr($ipl, $xx[2], $rsmi, $refr);

                // Calculate altitude at tr and tr+0.001 (swecl.c:4302-4303)
                $xaz = [];
                $xaz2 = [];
                \swe_azalt($tr, $tohor_flag, $dgeo, $atpress, $attemp, $xx, $xaz);
                \swe_azalt($tr + 0.001, $tohor_flag, $dgeo, $atpress, $attemp, $xx, $xaz2);

                // Rate of altitude change (swecl.c:4304)
                $dd = $xaz2[1] - $xaz[1];

                // Altitude including disc radius (swecl.c:4305)
                $dalt = $xaz[1] + $rdi;

                // Time correction (swecl.c:4306)
                $dt = $dalt / $dd / 1000.0;

                // Limit correction (swecl.c:4307-4311)
                if ($dt > 0.1) {
                    $dt = 0.1;
                } elseif ($dt < -0.1) {
                    $dt = -0.1;
                }

                // Apply correction (swecl.c:4314)
                $tr -= $dt;
            }

            // If the event found is before input time, search next event (swecl.c:4317-4322)
            $need_retry = ($tr < $tjd_ut0 && !$is_second_run);
            if ($need_retry) {
                $tjd_ut += 0.5;
                $is_second_run = true;
            }
        } while ($need_retry);

        $tret = $tr;
        return 0; // OK
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
