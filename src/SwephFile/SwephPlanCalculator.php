<?php

namespace Swisseph\SwephFile;

use Swisseph\Constants;

/**
 * Port of C function sweplan() from sweph.c:1819-1970
 *
 * Coordinates calculation of planet positions along with necessary auxiliary bodies
 * (barycentric Sun, Earth, Moon) according to Swiss Ephemeris logic.
 *
 * This is the main entry point for calculating planetary positions from ephemeris files.
 * It handles:
 * - Determining which auxiliary bodies need to be computed (Sun, Earth, Moon)
 * - Caching computed positions in SwedState
 * - Converting heliocentric planets to barycentric by adding barycentric Sun
 * - Computing Earth from EMB and Moon
 */
final class SwephPlanCalculator
{
    /**
     * Calculate planet position with proper handling of Sun/Earth/Moon dependencies
     *
     * Port of C function sweplan() from sweph.c:1819-1970
     *
     * @param float $tjd Julian day (TT/ET)
     * @param int $ipli Internal planet index (SEI_*)
     * @param int $iplExternal External planet index (SE_*) - needed to distinguish SUN/EARTH/MOON
     * @param int $ifno File number (SEI_FILE_PLANET or SEI_FILE_MOON)
     * @param int $iflag Calculation flags (SEFLG_*)
     * @param bool $doSave Whether to save results in SwedState cache
     * @param array &$xpret Output: planet position [6] (can be null)
     * @param array|null &$xperet Output: Earth position [6] (can be null)
     * @param array|null &$xpsret Output: Sun barycenter position [6] (can be null)
     * @param array|null &$xpmret Output: Moon position [6] (can be null)
     * @param string|null &$serr Error string
     * @return int OK (0) or error code
     */
    public static function calculate(
        float $tjd,
        int $ipli,
        int $iplExternal,
        int $ifno,
        int $iflag,
        bool $doSave,
        ?array &$xpret,
        ?array &$xperet,
        ?array &$xpsret,
        ?array &$xpmret,
        ?string &$serr
    ): int {
        $swed = SwedState::getInstance();

        // References to plan_data structures in SwedState
        $pdp = &$swed->pldat[$ipli];
        $pebdp = &$swed->pldat[SwephConstants::SEI_EMB];
        $psbdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];
        $pmdp = &$swed->pldat[SwephConstants::SEI_MOON];

        // Local arrays for non-saved calculations
        $xxp = array_fill(0, 6, 0.0);
        $xxe = array_fill(0, 6, 0.0);
        $xxs = array_fill(0, 6, 0.0);
        $xxm = array_fill(0, 6, 0.0);

        // Determine what needs to be computed
        // C code lines 1836-1847
        $doSunbary = $doSave
            || $ipli === SwephConstants::SEI_SUNBARY
            || ($pdp->iflg & SwephConstants::SEI_FLG_HELIO)
            || $xpsret !== null
            || ($iflag & Constants::SEFLG_HELCTR);

        $doEarth = $doSave
            || $ipli === SwephConstants::SEI_EARTH
            || $xperet !== null;

        $doMoon = $ipli === SwephConstants::SEI_MOON
            || $doSave
            || $ipli === SwephConstants::SEI_EARTH
            || $xperet !== null
            || $xpmret !== null;

        if ($ipli === SwephConstants::SEI_MOON) {
            $doEarth = true;
            $doSunbary = true;
        }

        // Choose output arrays: either save area or local arrays
        // C code lines 1848-1856
        if ($doSave) {
            $xp = &$pdp->x;
            $xpe = &$pebdp->x;
            $xps = &$psbdp->x;
            $xpm = &$pmdp->x;
        } else {
            $xp = &$xxp;
            $xpe = &$xxe;
            $xps = &$xxs;
            $xpm = &$xxm;
        }

        $speedf2 = $iflag & Constants::SEFLG_SPEED;

        // ===== BARYCENTRIC SUN =====
        // C code lines 1859-1873
        if ($doSunbary) {
            $speedf1 = $psbdp->xflgs & Constants::SEFLG_SPEED;

            if (getenv('DEBUG_OSCU') || (getenv('DEBUG_VENUS') && $ipli === 3)) {
                error_log(sprintf("DEBUG SwephPlanCalculator SUNBARY: tjd=%.10f, cached_teval=%.10f, doSave=%d, psbdp->x=[%.15f,%.15f,%.15f]",
                    $tjd, $psbdp->teval, $doSave ? 1 : 0, $psbdp->x[0] ?? 0, $psbdp->x[1] ?? 0, $psbdp->x[2] ?? 0));
            }

            // Check cache
            if ($tjd == $psbdp->teval
                && $psbdp->iephe == Constants::SEFLG_SWIEPH
                && (!$speedf2 || $speedf1)
            ) {
                // Use cached value
                if (getenv('DEBUG_OSCU') || (getenv('DEBUG_VENUS') && $ipli === 3)) {
                    error_log("DEBUG SwephPlanCalculator: using CACHED Sun barycenter");
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xps[$i] = $psbdp->x[$i];
                }
            } else {
                // Compute new value
                if (getenv('DEBUG_OSCU') || (getenv('DEBUG_VENUS') && $ipli === 3)) {
                    error_log(sprintf("DEBUG SwephPlanCalculator: COMPUTING new Sun barycenter, speedf1=%d, speedf2=%d", $speedf1, $speedf2));
                }

                $retc = SwephCalculator::calculate(
                    $tjd,
                    SwephConstants::SEI_SUNBARY,
                    SwephConstants::SEI_FILE_PLANET,
                    $iflag,
                    null,
                    $doSave,
                    $xps,
                    $serr
                );

                if ($retc != Constants::SE_OK) {
                    if (getenv('DEBUG_OSCU')) {
                        error_log("DEBUG SwephPlanCalculator: Sun barycenter calculation failed: $serr");
                    }
                    return $retc;
                }

                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("DEBUG SwephPlanCalculator: computed Sun barycenter xps=[%.15f,%.15f,%.15f]",
                        $xps[0], $xps[1], $xps[2]));
                }
            }

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG SwephPlanCalculator SUNBARY FINAL: xps=[%.15f,%.15f,%.15f], psbdp->x=[%.15f,%.15f,%.15f]",
                    $xps[0], $xps[1], $xps[2], $psbdp->x[0] ?? 0, $psbdp->x[1] ?? 0, $psbdp->x[2] ?? 0));
            }

            // Copy to output parameter if requested
            if ($xpsret !== null) {
                // Ensure source has 6 elements
                while (count($xps) < 6) {
                    $xps[] = 0.0;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xpsret[$i] = $xps[$i];
                }
            }
        }

        // ===== MOON =====
        // C code lines 1874-1896
        if ($doMoon) {
            $speedf1 = $pmdp->xflgs & Constants::SEFLG_SPEED;

            // Check cache
            if ($tjd == $pmdp->teval
                && $pmdp->iephe == Constants::SEFLG_SWIEPH
                && (!$speedf2 || $speedf1)
            ) {
                // Use cached value
                for ($i = 0; $i <= 5; $i++) {
                    $xpm[$i] = $pmdp->x[$i];
                }
            } else {
                // Compute new value
                $retc = SwephCalculator::calculate(
                    $tjd,
                    SwephConstants::SEI_MOON,
                    SwephConstants::SEI_FILE_MOON,
                    $iflag,
                    null,
                    $doSave,
                    $xpm,
                    $serr
                );

                if ($retc < 0) {
                    return $retc;
                }

                // TODO: If moon file doesn't exist, use Moshier moon
                // C code lines 1887-1893
            }

            // Copy to output parameter if requested
            if ($xpmret !== null) {
                // Ensure source has 6 elements
                while (count($xpm) < 6) {
                    $xpm[] = 0.0;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xpmret[$i] = $xpm[$i];
                }
            }
        }

        // ===== BARYCENTRIC EARTH =====
        // C code lines 1897-1920
        if ($doEarth) {
            $speedf1 = $pebdp->xflgs & Constants::SEFLG_SPEED;

            // Check cache
            if ($tjd == $pebdp->teval
                && $pebdp->iephe == Constants::SEFLG_SWIEPH
                && (!$speedf2 || $speedf1)
            ) {
                // Use cached value
                for ($i = 0; $i <= 5; $i++) {
                    $xpe[$i] = $pebdp->x[$i];
                }
            } else {
                // Compute EMB (Earth-Moon barycenter)
                $retc = SwephCalculator::calculate(
                    $tjd,
                    SwephConstants::SEI_EMB,
                    SwephConstants::SEI_FILE_PLANET,
                    $iflag,
                    null,
                    $doSave,
                    $xpe,
                    $serr
                );

                if ($retc != Constants::SE_OK) {
                    return $retc;
                }

                // Compute Earth from EMB and Moon: Earth = EMB - (Moon * mass_ratio)
                // C code: embofs(xpe, xpm)
                self::embofs($xpe, $xpm);

                // Speed is needed if:
                // 1. Saving to cache (xpe == pebdp->x)
                // 2. Speed flag is set
                if ($xpe === $pebdp->x || ($iflag & Constants::SEFLG_SPEED)) {
                    self::embofs_speed($xpe, $xpm);
                }
            }

            // Copy to output parameter if requested
            if ($xperet !== null) {
                // Ensure source has 6 elements
                while (count($xpe) < 6) {
                    $xpe[] = 0.0;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xperet[$i] = $xpe[$i];
                }
            }
        }

        // ===== MAIN PLANET/BODY =====
        // C code lines 1921-1970
        // NOTE: SEI_SUN, SEI_EARTH, SEI_EMB all equal 0, so we MUST use $iplExternal to distinguish them!
        // CRITICAL: $xp may point to $pdp->x (when doSave=true), which for SUN is slot 0 (EMB)!
        // We must NOT overwrite EMB data with SUN data - use local array instead!
        $xp_result = null;  // Will point to result data

        if ($ipli === SwephConstants::SEI_MOON) {
            // CRITICAL: Moon file contains GEOCENTRIC coordinates (relative to Earth)
            // But sweplan() must return BARYCENTRIC Moon!
            // Formula: barycentric_moon = geocentric_moon + barycentric_earth
            // C code comment sweph.c:1797-1801: "the geocentric moon, in barycentric cartesian equatorial coordinates"
            // This means: origin at SSB, but vector is geocentric (needs conversion to barycentric)

            if (getenv('DEBUG_MOON')) {
                error_log(sprintf("DEBUG Moon BEFORE conversion: xpm=[%.15f,%.15f,%.15f], dist=%.9f AU",
                    $xpm[0], $xpm[1], $xpm[2],
                    sqrt($xpm[0]*$xpm[0] + $xpm[1]*$xpm[1] + $xpm[2]*$xpm[2])));
                error_log(sprintf("DEBUG Earth: xpe=[%.15f,%.15f,%.15f], dist=%.9f AU",
                    $xpe[0], $xpe[1], $xpe[2],
                    sqrt($xpe[0]*$xpe[0] + $xpe[1]*$xpe[1] + $xpe[2]*$xpe[2])));
            }

            // CRITICAL FIX: C code sweplan() returns GEOCENTRIC Moon for SE_MOON!
            // sweph.c:1960: "xpret[i] = xp[i]" where xp points to xpm (geocentric)
            //
            // Barycentric conversion happens LATER in app_pos_etc_moon()
            // See sweph.c:4183: "xx[i] += xe[i]" after light-time correction
            //
            // Previous PHP code was WRONG - added Earth here, causing double-addition!

            if (getenv('DEBUG_MOON')) {
                error_log(sprintf("DEBUG SwephPlanCalculator: returning GEOCENTRIC Moon xpm=[%.15f,%.15f,%.15f], dist=%.9f AU",
                    $xpm[0], $xpm[1], $xpm[2],
                    sqrt($xpm[0]*$xpm[0] + $xpm[1]*$xpm[1] + $xpm[2]*$xpm[2])));
            }

            // Return GEOCENTRIC Moon (not barycentric!)
            $xp_result = $xpm;
        } elseif ($iplExternal === Constants::SE_EARTH) {
            // C code sweph.c:1933-1935
            // Return Earth position (use external index to distinguish from SUN!)
            $xp_result = $xpe;
        } elseif ($iplExternal === Constants::SE_SUN) {
            // C code sweph.c:1936-1939
            // CRITICAL: SE_SUN uses SEI_SUN=0 which is same as SEI_EMB!
            // $xp points to slot 0 (EMB), but we need SUNBARY data!
            // DO NOT overwrite EMB slot - return SUNBARY directly!
            $xp_result = $xps;
        } elseif ($ipli === SwephConstants::SEI_SUNBARY) {
            // Return Sun barycenter position
            $xp_result = $xps;
        } else {
            // Regular planet
            // For regular planets, $xp correctly points to $pdp->x (own slot)
            $speedf1 = $pdp->xflgs & Constants::SEFLG_SPEED;            // Check cache
            if ($tjd == $pdp->teval
                && $pdp->iephe == Constants::SEFLG_SWIEPH
                && (!$speedf2 || $speedf1)
            ) {
                // Use cached value - already in $xp
                // Copy to output and return early
                if ($xpret !== null) {
                    for ($i = 0; $i <= 5; $i++) {
                        $xpret[$i] = $xp[$i];
                    }
                }
                return Constants::SE_OK;
            } else {
                // Compute planet position
                // C code sweph.c:1077: for asteroids, pass psdp->x (Sun position) as xsunb
                // This is used inside SwephCalculator to convert heliocentric → barycentric
                // for bodies with ipl >= SEI_ANYBODY (i.e., all asteroids)
                $xsunb = ($ipli >= SwephConstants::SEI_ANYBODY) ? $xps : null;

                $retc = SwephCalculator::calculate(
                    $tjd,
                    $ipli,
                    $ifno,
                    $iflag,
                    $xsunb,
                    $doSave,
                    $xp,
                    $serr
                );

                if ($retc != Constants::SE_OK) {
                    return $retc;
                }

                // CRITICAL: If planet is heliocentric, convert to barycentric
                // C code lines 1951-1960
                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("DEBUG SwephPlanCalculator: planet ipli=%d, pdp->iflg=0x%X, SEI_FLG_HELIO=0x%X, check=%d",
                        $ipli, $pdp->iflg, SwephConstants::SEI_FLG_HELIO, ($pdp->iflg & SwephConstants::SEI_FLG_HELIO) ? 1 : 0));
                }

                if ($pdp->iflg & SwephConstants::SEI_FLG_HELIO) {
                    if (getenv('DEBUG_OSCU') || getenv('DEBUG_VENUS')) {
                        error_log(sprintf("DEBUG SwephPlanCalculator: planet ipli=%d is HELIO, adding Sun xps=[%.15f,%.15f,%.15f,%.15f,%.15f,%.15f]",
                            $ipli, $xps[0], $xps[1], $xps[2], $xps[3] ?? 0, $xps[4] ?? 0, $xps[5] ?? 0));
                        error_log(sprintf("DEBUG SwephPlanCalculator: BEFORE: xp=[%.15f,%.15f,%.15f]", $xp[0], $xp[1], $xp[2]));
                    }

                    // Add barycentric sun position
                    for ($i = 0; $i <= 2; $i++) {
                        $xp[$i] += $xps[$i];
                    }

                    // Add sun velocity if needed
                    if ($doSave || ($iflag & Constants::SEFLG_SPEED)) {
                        for ($i = 3; $i <= 5; $i++) {
                            $xp[$i] += $xps[$i];
                        }
                    }
                } else {
                    if (getenv('DEBUG_OSCU')) {
                        error_log("DEBUG SwephPlanCalculator: planet is NOT heliocentric, no conversion needed");
                    }
                }

                // Set result pointer AFTER computation
                $xp_result = $xp;
            }
        }

        // Copy to output parameter if requested
        // Use $xp_result which points to correct data (may be $xps for SUN!)
        if ($xpret !== null && $xp_result !== null) {
            // CRITICAL: Ensure output array ALWAYS has exactly 6 elements
            // In C, all coordinate arrays are double[6], so always have 6 elements
            // In PHP, we must guarantee this explicitly to prevent "Undefined array key" warnings
            while (count($xp_result) < 6) {
                $xp_result[] = 0.0;
            }

            for ($i = 0; $i <= 5; $i++) {
                $xpret[$i] = $xp_result[$i];
            }
        }

        return Constants::SE_OK;
    }

    /**
     * Compute Earth position from EMB and Moon
     * Port of C function embofs() - position part
     *
     * Earth = EMB - Moon * (Moon_mass / (Earth_mass + Moon_mass))
     *
     * @param array &$xemb EMB position [0..2], modified to Earth position
     * @param array $xmoon Moon position [0..2]
     */
    private static function embofs(array &$xemb, array $xmoon): void
    {
        // EARTH_MOON_MRAT from C sweph.h:265 - AA 2006, K7
        // #define EARTH_MOON_MRAT (1 / 0.0123000383)
        // C formula: xemb[i] -= xmoon[i] / (EARTH_MOON_MRAT + 1.0);
        // = xmoon[i] / ((1/0.0123000383) + 1.0) = xmoon[i] / 82.300559852728...
        $earthMoonMassRatio = 1.0 / 0.0123000383;  // = 81.300559852728...

        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("DEBUG embofs BEFORE: xemb=[%.15f, %.15f, %.15f], xmoon=[%.15f, %.15f, %.15f]",
                $xemb[0], $xemb[1], $xemb[2], $xmoon[0], $xmoon[1], $xmoon[2]));
        }

        for ($i = 0; $i <= 2; $i++) {
            $xemb[$i] -= $xmoon[$i] / ($earthMoonMassRatio + 1.0);
        }

        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("DEBUG embofs AFTER: xemb=[%.15f, %.15f, %.15f]",
                $xemb[0], $xemb[1], $xemb[2]));
        }
    }

    /**
     * Compute Earth velocity from EMB and Moon velocities
     * Port of C function embofs() - velocity part
     *
     * @param array &$xemb EMB velocity [3..5], modified to Earth velocity
     * @param array $xmoon Moon velocity [3..5]
     */
    private static function embofs_speed(array &$xemb, array $xmoon): void
    {
        // Same constant as embofs() - EARTH_MOON_MRAT from AA 2006
        $earthMoonMassRatio = 1.0 / 0.0123000383;

        for ($i = 3; $i <= 5; $i++) {
            $xemb[$i] -= $xmoon[$i] / ($earthMoonMassRatio + 1.0);
        }
    }
}
