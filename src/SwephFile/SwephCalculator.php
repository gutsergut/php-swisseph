<?php

namespace Swisseph\SwephFile;

use Swisseph\Constants;

/**
 * Swiss Ephemeris calculator
 *
 * Port of sweph() from sweph.c:2124
 *
 * Main function for calculating planetary positions from .se1 ephemeris files
 * using Chebyshev polynomial interpolation
 */
final class SwephCalculator
{
    /** Return code: Success */
    private const OK = 0;

    /** Return code: Error */
    private const ERR = -1;

    /** Return code: Data not available */
    private const NOT_AVAILABLE = -2;

    /** Do not save to cache */
    private const NO_SAVE = false;

    /**
     * Calculate planetary position from Swiss Ephemeris file
     *
     * Port of sweph() from sweph.c:2124
     *
     * @param float $tjd Julian Day (TT)
     * @param int $ipli Planet index (SEI_SUNBARY, SEI_EMB, etc.)
     * @param int $ifno File index (SEI_FILE_PLANET, etc.)
     * @param int $iflag Calculation flags
     * @param array|null $xsunb Barycentric sun position (for asteroids)
     * @param bool $doSave Whether to save result to cache
     * @param array|null &$xpret Output: position and velocity [x,y,z,dx,dy,dz]
     * @param string|null &$serr Error message
     * @return int OK, ERR, or NOT_AVAILABLE
     */
    public static function calculate(
        float $tjd,
        int $ipli,
        int $ifno,
        int $iflag,
        ?array $xsunb,
        bool $doSave,
        ?array &$xpret,
        ?string &$serr
    ): int {
        $swed = SwedState::getInstance();

        // Sync ephemeris path from State
        $swed->ephepath = \Swisseph\State::getEphePath();

        $ipl = $ipli;
        if ($ipli > Constants::SE_AST_OFFSET) {
            $ipl = SwephConstants::SEI_ANYBODY;
        }

        // Special handling for barycentric Sun:
        // Modern ephemeris files don't have barycentric sun data,
        // but have heliocentric earth (index 0) with SEI_FLG_EMBHEL flag.
        // In this case, we read Earth data and later compute Sun = EMB - Earth
        $readEarthForSun = false;
        $sunbaryCached = false;
        if ($ipli == SwephConstants::SEI_SUNBARY) {
            // CRITICAL: Only use Earth data if SUNBARY is not already cached
            // Otherwise we get EMB - EMB = 0 bug (issue found 2025-10-30)
            $sunPdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];

            // Check if SUNBARY is already cached and valid
            $sunbaryCached = ($sunPdp->teval == $tjd &&
                            $sunPdp->iephe == Constants::SEFLG_SWIEPH);

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG SwephCalculator: SEI_SUNBARY requested, tjd=%.10f, sunPdp->teval=%.10f, cached=%d",
                    $tjd, $sunPdp->teval, $sunbaryCached ? 1 : 0));
            }

            if ($sunbaryCached && getenv('DEBUG_OSCU')) {
                error_log("DEBUG SwephCalculator: using CACHED SUNBARY, skipping EMB-Earth computation");
            }

            // Note: Don't check EMBHEL flag yet - pdp->iflg not loaded until file is opened
            // We'll check after loading the file
        }

        $pdp = &$swed->pldat[$ipl];
        $fdp = &$swed->fidat[$ifno];

        // Working array
        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        if ($doSave) {
            $xp = &$pdp->x;
        } else {
            $xp = &$xx;
        }

        // Check if already computed
        $speedf1 = $pdp->xflgs & Constants::SEFLG_SPEED;
        $speedf2 = $iflag & Constants::SEFLG_SPEED;

        if ($tjd == $pdp->teval &&
            $pdp->iephe == Constants::SEFLG_SWIEPH &&
            (!$speedf2 || $speedf1) &&
            $ipl < SwephConstants::SEI_ANYBODY) {

            if ($xpret !== null) {
                for ($i = 0; $i <= 5; $i++) {
                    $xpret[$i] = $pdp->x[$i];
                }
            }
            return self::OK;
        }

        // Check if file is open and valid
        if ($fdp->fptr !== null) {
            // Close if date out of range or different asteroid
            if ($tjd < $fdp->tfstart || $tjd > $fdp->tfend ||
                ($ipl == SwephConstants::SEI_ANYBODY && $ipli != $pdp->ibdy)) {

                fclose($fdp->fptr);
                $fdp->fptr = null;
                $pdp->refep = null;
                $pdp->segp = null;
            }
        }

        // Open file if not open
        if ($fdp->fptr === null) {
            $fname = self::generateFilename($tjd, $ipli);

            if (!SwephReader::openAndReadHeader($ifno, $fname, $swed->ephepath, $serr)) {
                return self::NOT_AVAILABLE;
            }
        }

        // Check date range
        if ($tjd < $fdp->tfstart || $tjd > $fdp->tfend) {
            $serr = sprintf(
                "Date %f out of range [%f, %f] for file",
                $tjd,
                $fdp->tfstart,
                $fdp->tfend
            );
            return self::NOT_AVAILABLE;
        }

        // Get new segment if necessary
        if ($pdp->segp === null || $tjd < $pdp->tseg0 || $tjd > $pdp->tseg1) {
            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG SwephCalculator: loading segment for ipli=%d (ipl=%d), tjd=%.2f", $ipli, $ipl, $tjd));
            }

            if (!SwephReader::getNewSegment($tjd, $ipl, $ifno, $serr)) {
                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("DEBUG SwephCalculator: getNewSegment failed: %s", $serr));
                }
                return self::ERR;
            }

            // NOW check EMBHEL flag AFTER file is loaded and pdp->iflg is set
            // This must be done BEFORE rotating coefficients
            if ($ipli == SwephConstants::SEI_SUNBARY && !$sunbaryCached) {
                // Check if SUNBARY file contains heliocentric Earth (EMBHEL flag)
                if ($pdp->iflg & SwephConstants::SEI_FLG_EMBHEL) {
                    // File contains heliocentric Earth in SUNBARY slot
                    // Need to compute: barycentric Sun = EMB - heliocentric Earth
                    $readEarthForSun = true;
                    if (getenv('DEBUG_OSCU')) {
                        error_log(sprintf("DEBUG SwephCalculator: SUNBARY file has EMBHEL flag (0x%X), will compute Sun = EMB - helio_earth", $pdp->iflg));
                    }
                } else if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("DEBUG SwephCalculator: SUNBARY file has NO EMBHEL flag (0x%X), reading barycentric Sun directly", $pdp->iflg));
                }
            }

            // Rotate Chebyshev coefficients back to equatorial system
            if ($pdp->iflg & SwephConstants::SEI_FLG_ROTATE) {
                if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER) {
                    error_log(sprintf("DEBUG SwephCalculator BEFORE rotateBack: first 3 coefs X=[%.10f, %.10f, %.10f], Y=[%.10f, %.10f, %.10f], Z=[%.10f, %.10f, %.10f]",
                        $pdp->segp[0], $pdp->segp[1], $pdp->segp[2],
                        $pdp->segp[$pdp->ncoe], $pdp->segp[$pdp->ncoe+1], $pdp->segp[$pdp->ncoe+2],
                        $pdp->segp[2*$pdp->ncoe], $pdp->segp[2*$pdp->ncoe+1], $pdp->segp[2*$pdp->ncoe+2]));
                }
                SeriesRotation::rotateBack($ipl);
                if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_JUPITER) {
                    error_log(sprintf("DEBUG SwephCalculator AFTER rotateBack: first 3 coefs X=[%.10f, %.10f, %.10f], Y=[%.10f, %.10f, %.10f], Z=[%.10f, %.10f, %.10f]",
                        $pdp->segp[0], $pdp->segp[1], $pdp->segp[2],
                        $pdp->segp[$pdp->ncoe], $pdp->segp[$pdp->ncoe+1], $pdp->segp[$pdp->ncoe+2],
                        $pdp->segp[2*$pdp->ncoe], $pdp->segp[2*$pdp->ncoe+1], $pdp->segp[2*$pdp->ncoe+2]));
                }
            } else {
                $pdp->neval = $pdp->ncoe;
            }
        }

        // Evaluate Chebyshev polynomial for tjd
        $t = ($tjd - $pdp->tseg0) / $pdp->dseg;
        $t = $t * 2.0 - 1.0;

        if (getenv('DEBUG_OSCU') && ($ipli == SwephConstants::SEI_SATURN || $ipli == SwephConstants::SEI_SUNBARY)) {
            error_log(sprintf("DEBUG Chebyshev: tjd=%.16f, tseg0=%.16f, dseg=%.16f, t_normalized=%.16f",
                $tjd, $pdp->tseg0, $pdp->dseg, $t));
        }

        // Determine if speed is needed
        $needSpeed = $doSave || ($iflag & Constants::SEFLG_SPEED);

        // Interpolate position and velocity for each coordinate
        // C code sweph.c:2307-2314
        // CRITICAL: Always initialize ALL 6 elements (positions 0-2 and velocities 3-5)
        // Even when !need_speed, velocities must be set to 0.0
        // This ensures arrays are always complete for subsequent operations
        for ($i = 0; $i <= 2; $i++) {
            $coeffOffset = $i * $pdp->ncoe;
            $coeffArray = array_slice($pdp->segp, $coeffOffset, $pdp->ncoe);

            $xp[$i] = ChebyshevInterpolation::evaluate($t, $coeffArray, $pdp->neval);

            if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_SATURN && $i == 2 && abs($tjd - 2451545.0) < 0.001) {
                error_log(sprintf("DEBUG Chebyshev Saturn Z: tjd=%.10f, t_norm=%.15f, ncoe=%d, neval=%d", $tjd, $t, $pdp->ncoe, $pdp->neval));
                error_log(sprintf("  First 5 coeffs: [%.15f, %.15f, %.15f, %.15f, %.15f]",
                    $coeffArray[0], $coeffArray[1], $coeffArray[2], $coeffArray[3], $coeffArray[4]));
                error_log(sprintf("  Position result: %.15f", $xp[$i]));
            }

            if ($needSpeed) {
                $deriv = ChebyshevInterpolation::evaluateDerivative($t, $coeffArray, $pdp->neval);
                $xp[$i + 3] = $deriv / $pdp->dseg * 2.0;

                if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_SATURN && $i == 2 && abs($tjd - 2451545.0) < 0.001) {
                    error_log(sprintf("  Derivative result: %.15f, dseg=%.15f", $deriv, $pdp->dseg));
                    error_log(sprintf("  Velocity result: %.15f AU/day", $xp[$i + 3]));
                }
            } else {
                // C code sweph.c:2313: "von Alois als billiger fix, evtl. illegal"
                // BUT: This is CRITICAL! Must always initialize velocity elements
                // to ensure arrays have exactly 6 elements for all subsequent code
                $xp[$i + 3] = 0.0;
            }
        }

        if (getenv('DEBUG_OSCU') && ($ipli == SwephConstants::SEI_SATURN || $ipli == SwephConstants::SEI_SUNBARY)) {
            error_log(sprintf("DEBUG SwephCalculator AFTER Chebyshev interpolation: ipli=%d, xp=[%.15f, %.15f, %.15f], velocity=[%.15f, %.15f, %.15f]",
                $ipli, $xp[0], $xp[1], $xp[2], $xp[3], $xp[4], $xp[5]));
        }

        // Special handling for barycentric Sun: compute as EMB - heliocentric Earth
        // This is used when ephemeris files have heliocentric Earth instead of barycentric Sun
        // CRITICAL: Check EMBHEL flag EVERY TIME for SUNBARY, not just when loading file!
        // File may already be loaded from previous call, but we still need EMB-Earth computation
        if ($ipli == SwephConstants::SEI_SUNBARY && !$sunbaryCached) {
            // Check if we need to compute Sun from EMB - heliocentric Earth
            if ($pdp->iflg & SwephConstants::SEI_FLG_EMBHEL) {
                $readEarthForSun = true;
                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("DEBUG SwephCalculator: EMBHEL flag present (0x%X), will compute Sun = EMB - helio_earth", $pdp->iflg));
                }
            }
        }

        if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_SUNBARY) {
            error_log(sprintf("DEBUG SwephCalculator check EMBHEL: ipli=%d, ipl=%d, readEarthForSun=%d, pdp->iflg=0x%X, SEI_FLG_EMBHEL=0x%X, has_EMBHEL=%d",
                $ipli, $ipl, $readEarthForSun ? 1 : 0, $pdp->iflg, SwephConstants::SEI_FLG_EMBHEL, ($pdp->iflg & SwephConstants::SEI_FLG_EMBHEL) ? 1 : 0));
        }

        if ($readEarthForSun) {
            // Compute EMB and subtract heliocentric Earth to get barycentric Sun
            $pedp = &$swed->pldat[SwephConstants::SEI_EARTH];
            $tsv = $pedp->teval;
            $pedp->teval = 0; // Force new computation

            $xemb = array_fill(0, 6, 0.0);
            $retc = self::calculate($tjd, SwephConstants::SEI_EMB, $ifno, $iflag | Constants::SEFLG_SPEED, null, self::NO_SAVE, $xemb, $serr);

            if ($retc != self::OK) {
                return $retc;
            }

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG SwephCalculator SUNBARY via Earth: tjd=%.10f, xemb=[%.15f,%.15f,%.15f], helio_earth=[%.15f,%.15f,%.15f]",
                    $tjd, $xemb[0], $xemb[1], $xemb[2], $xp[0], $xp[1], $xp[2]));
            }

            $pedp->teval = $tsv;

            // Barycentric sun = EMB - heliocentric Earth
            for ($i = 0; $i <= 2; $i++) {
                $xp[$i] = $xemb[$i] - $xp[$i];
            }

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG SwephCalculator SUNBARY result: EMB - helio_earth = [%.15f,%.15f,%.15f]",
                    $xp[0], $xp[1], $xp[2]));
            }

            if ($needSpeed) {
                for ($i = 3; $i <= 5; $i++) {
                    $xp[$i] = $xemb[$i] - $xp[$i];
                }
            }

            // CRITICAL: Save Sun to SEI_SUNBARY slot when computing from Earth
            // In C code, sweplan() does this automatically because xps points to psbdp->x
            if ($doSave || $ipli == SwephConstants::SEI_EARTH) {
                $psdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];
                for ($i = 0; $i <= 5; $i++) {
                    $psdp->x[$i] = $xp[$i];
                }
                $psdp->teval = $tjd;
                $psdp->xflgs = -1;
                $psdp->iephe = Constants::SEFLG_SWIEPH;
            }
        }

        // For asteroids (heliocentric), convert to barycentric
        if ($xsunb !== null && (($iflag & Constants::SEFLG_JPLEPH) || ($iflag & Constants::SEFLG_SWIEPH))) {
            if ($ipl >= SwephConstants::SEI_ANYBODY) {
                for ($i = 0; $i <= 2; $i++) {
                    $xp[$i] += $xsunb[$i];
                }

                if ($needSpeed) {
                    for ($i = 3; $i <= 5; $i++) {
                        $xp[$i] += $xsunb[$i];
                    }
                }
            }
        }

        // Save results if requested
        if ($doSave) {
            $pdp->teval = $tjd;
            $pdp->xflgs = -1; // Mark for new light-time computation

            if ($ifno == SwephConstants::SEI_FILE_PLANET || $ifno == SwephConstants::SEI_FILE_MOON) {
                $pdp->iephe = Constants::SEFLG_SWIEPH;
            } else {
                $psdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];
                $pdp->iephe = $psdp->iephe;
            }

            if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_SUNBARY) {
                error_log(sprintf("DEBUG SwephCalculator: saved SEI_SUNBARY tjd=%.10f, xp=[%.15f,%.15f,%.15f], pdp->x=[%.15f,%.15f,%.15f]",
                    $tjd, $xp[0], $xp[1], $xp[2], $pdp->x[0] ?? 0, $pdp->x[1] ?? 0, $pdp->x[2] ?? 0));
            }
        }

        // Copy result to output
        if ($xpret !== null) {
            for ($i = 0; $i <= 5; $i++) {
                $xpret[$i] = $xp[$i];
            }
        }

        if (getenv('DEBUG_OSCU') && $ipli == SwephConstants::SEI_SUNBARY) {
            error_log(sprintf("DEBUG SwephCalculator EXIT: ipli=%d, tjd=%.10f, xp=[%.15f,%.15f,%.15f], xpret=[%.15f,%.15f,%.15f]",
                $ipli, $tjd, $xp[0], $xp[1], $xp[2], $xpret[0] ?? 0, $xpret[1] ?? 0, $xpret[2] ?? 0));
        }

        return self::OK;
    }

    /**
     * Generate ephemeris filename for given date and planet
     *
     * Simple implementation - returns standard planet file
     */
    /**
     * Generate ephemeris filename for given Julian day and planet
     *
     * Delegates to FilenameGenerator which is a port of C swi_gen_filename()
     *
     * @param float $tjd Julian day
     * @param int $ipli Internal planet index (SEI_*)
     * @return string Filename
     */
    private static function generateFilename(float $tjd, int $ipli): string
    {
        return FilenameGenerator::generate($tjd, $ipli);
    }
}
