<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\DeltaT;
use Swisseph\Math;
use Swisseph\NodesApsides;
use Swisseph\Nutation;
use Swisseph\NutationMatrix;
use Swisseph\Obliquity;
use Swisseph\Precession;

/**
 * Swiss Ephemeris API for Nodes and Apsides
 * Wrapper functions matching swe_nod_aps* C API
 */
class NodesApsidesFunctions
{
    /** J2000.0 epoch */
    private const J2000 = 2451545.0;

    /**
     * Calculate nodes and apsides of planets
     *
     * Port of swe_nod_aps() from Swiss Ephemeris.
     *
     * Method flags:
     * - SE_NODBIT_MEAN (1): mean nodes/apsides for Sun-Neptune, osculating for Pluto+ (default)
     * - SE_NODBIT_OSCU (2): osculating nodes/apsides for all planets
     * - SE_NODBIT_OSCU_BAR (4): osculating from barycentric ellipses (planets beyond Jupiter)
     * - SE_NODBIT_FOPOINT (256): return focal point instead of aphelion
     *
     * @param float $tjdEt Julian day in Ephemeris Time (TT)
     * @param int $ipl Planet number (SE_SUN, SE_MOON, SE_MERCURY, ..., SE_NEPTUNE)
     * @param int $iflag Calculation flags (SEFLG_SPEED, etc.)
     * @param int $method Method bits (SE_NODBIT_*)
     * @param array|null &$xnasc Output: ascending node [lon, lat, dist, dlon, dlat, ddist] or null
     * @param array|null &$xndsc Output: descending node or null
     * @param array|null &$xperi Output: perihelion or null
     * @param array|null &$xaphe Output: aphelion (or focal point if SE_NODBIT_FOPOINT) or null
     * @param string|null &$serr Error message or null
     * @return int OK (>=0) on success, ERR (<0) on error
     */
    public static function nodAps(
        float $tjdEt,
        int $ipl,
        int $iflag,
        int $method,
        ?array &$xnasc,
        ?array &$xndsc,
        ?array &$xperi,
        ?array &$xaphe,
        ?string &$serr = null
    ): int {
        $serr = null;

        // Validate planet number
        if (
            $ipl === Constants::SE_MEAN_NODE || $ipl === Constants::SE_TRUE_NODE ||
            $ipl === Constants::SE_MEAN_APOG || $ipl === Constants::SE_OSCU_APOG ||
            $ipl < 0 || ($ipl > Constants::SE_NEPTUNE && $ipl !== Constants::SE_EARTH)
        ) {
            $serr = sprintf('nodes/apsides for planet %d are not implemented', $ipl);

            // Initialize output arrays to zero
            if ($xnasc !== null) {
                $xnasc = array_fill(0, 6, 0.0);
            }
            if ($xndsc !== null) {
                $xndsc = array_fill(0, 6, 0.0);
            }
            if ($xperi !== null) {
                $xperi = array_fill(0, 6, 0.0);
            }
            if ($xaphe !== null) {
                $xaphe = array_fill(0, 6, 0.0);
            }

            return Constants::SE_ERR;
        }

        // Extract method flags
        $doFocalPoint = (bool)($method & Constants::SE_NODBIT_FOPOINT);
        $methodBase = $method & ~Constants::SE_NODBIT_FOPOINT;

        $withSpeed = (bool)($iflag & Constants::SEFLG_SPEED);

        // For now, only implement mean nodes/apsides (default method)
        if ($methodBase === 0 || ($methodBase & Constants::SE_NODBIT_MEAN)) {
            // Mean positions for Sun-Neptune
            if (($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_NEPTUNE) || $ipl === Constants::SE_EARTH) {
                // If speeds are requested, compute at 3 times: t-dt, t, t+dt
                // Port of swecl.c:5250-5265 (NODE_CALC_INTV logic)
                $dt = 0.0001; // NODE_CALC_INTV
                $istart = 0;
                $iend = 0;
                if ($withSpeed) {
                    $istart = 0;
                    $iend = 2;
                }

                // Storage for 3 calculations
                $xna_all = [];
                $xnd_all = [];
                $xpe_all = [];
                $xap_all = [];

                for ($i = $istart; $i <= $iend; $i++) {
                    $t = $tjdEt + ($i - 1) * $dt; // t-dt, t, t+dt
                    if ($istart === $iend) {
                        $t = $tjdEt;
                    }

                    $errTemp = null;
                    $result = NodesApsides::compute($t, $ipl, $iflag & ~Constants::SEFLG_SPEED, $method, $errTemp);

                    if ($result < 0) {
                        $serr = $errTemp ?? 'Unknown error in NodesApsides::compute()';
                        if ($xnasc !== null) {
                            $xnasc = array_fill(0, 6, 0.0);
                        }
                        if ($xndsc !== null) {
                            $xndsc = array_fill(0, 6, 0.0);
                        }
                        if ($xperi !== null) {
                            $xperi = array_fill(0, 6, 0.0);
                        }
                        if ($xaphe !== null) {
                            $xaphe = array_fill(0, 6, 0.0);
                        }
                        return -1;
                    }

                    [$xna, $xnd, $xpe, $xap] = NodesApsides::getResults();

                    // Apply final transformations
                    $xx = [&$xna, &$xnd, &$xpe, &$xap];
                    self::applyFinalNodApsTransformations($xx, $t, $iflag & ~Constants::SEFLG_SPEED, $ipl);

                    $xna_all[$i] = $xna;
                    $xnd_all[$i] = $xnd;
                    $xpe_all[$i] = $xpe;
                    $xap_all[$i] = $xap;
                }

                // Compute final positions and speeds (C code: swecl.c:5398-5416)
                if ($withSpeed) {
                    // Use central position and compute speed via central difference
                    $xna = $xna_all[1];
                    $xnd = $xnd_all[1];
                    $xpe = $xpe_all[1];
                    $xap = $xap_all[1];

                    // Speeds (indices 3,4,5) = (x[2] - x[0]) / (2*dt)
                    for ($i = 0; $i < 3; $i++) {
                        $xna[$i + 3] = ($xna_all[2][$i] - $xna_all[0][$i]) / (2 * $dt);
                        $xnd[$i + 3] = ($xnd_all[2][$i] - $xnd_all[0][$i]) / (2 * $dt);
                        $xpe[$i + 3] = ($xpe_all[2][$i] - $xpe_all[0][$i]) / (2 * $dt);
                        $xap[$i + 3] = ($xap_all[2][$i] - $xap_all[0][$i]) / (2 * $dt);
                    }
                } else {
                    // No speed requested - use single calculation
                    $xna = $xna_all[0];
                    $xnd = $xnd_all[0];
                    $xpe = $xpe_all[0];
                    $xap = $xap_all[0];
                    // Initialize speeds to 0
                    for ($i = 3; $i < 6; $i++) {
                        $xna[$i] = 0.0;
                        $xnd[$i] = 0.0;
                        $xpe[$i] = 0.0;
                        $xap[$i] = 0.0;
                    }
                }

                // Copy transformed results to output (always assign)
                $xnasc = $xna;
                $xndsc = $xnd;
                $xperi = $xpe;
                $xaphe = $xap;
                return 0;  // OK
            }
        }

        // Osculating nodes/apsides
        if ($methodBase & (Constants::SE_NODBIT_OSCU | Constants::SE_NODBIT_OSCU_BAR)) {
            $errTemp = null;
            $result = NodesApsides::compute($tjdEt, $ipl, $iflag, $method, $errTemp);

            if ($result < 0) {
                $serr = $errTemp ?? 'Unknown error in NodesApsides::compute()';
                if ($xnasc !== null) {
                    $xnasc = array_fill(0, 6, 0.0);
                }
                if ($xndsc !== null) {
                    $xndsc = array_fill(0, 6, 0.0);
                }
                if ($xperi !== null) {
                    $xperi = array_fill(0, 6, 0.0);
                }
                if ($xaphe !== null) {
                    $xaphe = array_fill(0, 6, 0.0);
                }
                return Constants::SE_ERR;
            }

            [$xna, $xnd, $xpe, $xap] = NodesApsides::getResults();

            // Osculating nodes need full transformation cycle
            // (ecliptic→equator→J2000→date→ecliptic) per C code lines 5464-5620
            $isTrueNodaps = NodesApsides::isTrueNodaps();

            if ($isTrueNodaps) {
                // For TRUE (osculating) nodes/apsides, apply full transformation chain
                // This matches C code swe_nod_aps lines 5464-5620
                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("Before applyOsculatingNodApsTransformations: xna=[%.10f, %.10f, %.10f]", $xna[0], $xna[1], $xna[2]));
                    $test_lon = rad2deg(atan2($xna[1], $xna[0]));
                    if ($test_lon < 0) $test_lon += 360.0;
                    error_log(sprintf("  Initial node lon=%.10f°", $test_lon));
                }

                $xx = [&$xna, &$xnd, &$xpe, &$xap];
                self::applyOsculatingNodApsTransformations($xx, $tjdEt, $iflag, $ipl);

                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("After applyOsculatingNodApsTransformations: xna=[%.10f, %.10f, %.10f]", $xna[0], $xna[1], $xna[2]));
                    error_log(sprintf("  Final node lon=%.10f°", $xna[0]));
                }

                // Results are now in ecliptic spherical (lon, lat, r) after transformations
                // Always assign results (C code doesn't check for NULL after transformations)
                $xnasc = $xna;
                $xndsc = $xnd;
                $xperi = $xpe;
                $xaphe = $xap;
            } else {
                // For MEAN nodes/apsides, just convert cartesian to spherical
                // (transformations already applied in mean calculation)
                if ($xnasc !== null) {
                    $xnasc = self::convertNodApsToSpherical($xna, $iflag, $tjdEt, $isTrueNodaps);
                }
                if ($xndsc !== null) {
                    $xndsc = self::convertNodApsToSpherical($xnd, $iflag, $tjdEt, $isTrueNodaps);
                }
                if ($xperi !== null) {
                    $xperi = self::convertNodApsToSpherical($xpe, $iflag, $tjdEt, $isTrueNodaps);
                }
                if ($xaphe !== null) {
                    $xaphe = self::convertNodApsToSpherical($xap, $iflag, $tjdEt, $isTrueNodaps);
                }
            }

            return 0;  // OK
        }

        $serr = sprintf('unsupported planet %d or method %d', $ipl, $method);

        if ($xnasc !== null) {
            $xnasc = array_fill(0, 6, 0.0);
        }
        if ($xndsc !== null) {
            $xndsc = array_fill(0, 6, 0.0);
        }
        if ($xperi !== null) {
            $xperi = array_fill(0, 6, 0.0);
        }
        if ($xaphe !== null) {
            $xaphe = array_fill(0, 6, 0.0);
        }

        return Constants::SE_ERR;
    }

    /**
     * Calculate nodes and apsides of planets (UT version)
     *
     * Same as swe_nod_aps() but takes UT time instead of ET/TT.
     * Automatically converts UT to ET using Delta T.
     *
     * @param float $tjdUt Julian day in Universal Time
     * @param int $ipl Planet number
     * @param int $iflag Calculation flags
     * @param int $method Method bits (SE_NODBIT_*)
     * @param array|null &$xnasc Output: ascending node or null
     * @param array|null &$xndsc Output: descending node or null
     * @param array|null &$xperi Output: perihelion or null
     * @param array|null &$xaphe Output: aphelion or null
     * @param string|null &$serr Error message or null
     * @return int OK (>=0) on success, ERR (<0) on error
     */
    public static function nodApsUt(
        float $tjdUt,
        int $ipl,
        int $iflag,
        int $method,
        ?array &$xnasc,
        ?array &$xndsc,
        ?array &$xperi,
        ?array &$xaphe,
        ?string &$serr = null
    ): int {
        // Convert UT to ET
        $deltaT = DeltaT::deltaTSecondsFromJd($tjdUt) / 86400.0;
        $tjdEt = $tjdUt + $deltaT;

        return self::nodAps(
            $tjdEt,
            $ipl,
            $iflag,
            $method,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );
    }

    /**
     * Apply full coordinate transformation cycle for OSCULATING nodes/apsides
     * Input: Cartesian ecliptic J2000 XYZ coordinates from osculating calculation
     * Output: Ecliptic spherical (lon, lat, r) in requested frame (J2000 or date)
     * Matches C code swe_nod_aps lines 5464-5620
     *
     * @param array &$xx Array of 4 cartesian arrays: [ascending node, descending node, perihelion, aphelion]
     * @param float $tjdEt Julian day (ET)
     * @param int $iflag Calculation flags
     * @param int $ipl Planet number
     */
    private static function applyOsculatingNodApsTransformations(array &$xx, float $tjdEt, int $iflag, int $ipl): void
    {
        // Initialize save area by computing planet (C code lines 5418-5428)
        $x = [];
        $serr = '';
        $iflg0 = ($iflag & Constants::SEFLG_EPHMASK) | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS;

        // Compute planet to initialize save area (required for obliquity, nutation, etc.)
        if ($ipl === Constants::SE_MOON && ($iflag & (Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR))) {
            PlanetsFunctions::calc($tjdEt, Constants::SE_SUN, $iflg0, $x, $serr);
        } else {
            PlanetsFunctions::calc($tjdEt, $ipl, $iflg0 | ($iflag & Constants::SEFLG_TOPOCTR), $x, $serr);
        }

        // Get barycentric Sun and Earth (C code uses swed.pldat[SEI_SUNBARY].x and swed.pldat[SEI_EARTH].x)
        $xsun = \Swisseph\BarycentricPositions::getBarycentricSun($tjdEt, $iflag);
        $xear = \Swisseph\BarycentricPositions::getBarycentricEarth($tjdEt, $iflag);

        // Calculate observer position (C code lines 5429-5447)
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        if ($iflag & (Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR)) {
            if ($iflag & Constants::SEFLG_HELCTR) {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs[$i] = $xsun[$i];
                }
            }
            // else: barycentric, observer at origin
        } elseif ($ipl === Constants::SE_SUN) {
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $xsun[$i];
            }
        } else {
            // Geocentric: observer at barycentric Earth
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $xear[$i];
            }
        }

        // Get obliquity for coordinate transformations
        $useJ2000 = (bool)($iflag & Constants::SEFLG_J2000);
        $oe = \Swisseph\Obliquity::calc($useJ2000 ? 2451545.0 : $tjdEt, $iflag, 0, null);
        $seps = sin($oe);
        $ceps = cos($oe);

        // Get nutation angles if needed
        $donut = !($iflag & Constants::SEFLG_NONUT);
        if ($donut) {
            $nutModel = \Swisseph\Nutation::selectModelFromFlags($iflag);
            [$dpsi, $deps] = \Swisseph\Nutation::calc($tjdEt, $nutModel, false);
            $snut = sin($deps);
            $cnut = cos($deps);
        }

        // Process each of the 4 points (ascending node, descending node, perihelion, aphelion)
        // C code lines 5464-5620
        for ($ij = 0; $ij < 4; $ij++) {
            $xp = &$xx[$ij];

            // Skip Earth nodes (C code lines 5465-5469)
            if ($ipl === Constants::SE_EARTH && $ij <= 1) {
                $xp = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
                continue;
            }

            // INPUT: xp is in ECLIPTIC J2000 XYZ (from osculating calculation)

            // Step 1: If osculating + not NONUT, apply nutation to ecliptic
            // C code lines 5474-5477
            if ($donut) {
                $xOut = [];
                \Swisseph\Coordinates::coortrf2($xp, $xOut, -$snut, $cnut);
                $xp[0] = $xOut[0];
                $xp[1] = $xOut[1];
                $xp[2] = $xOut[2];
                if ($iflag & Constants::SEFLG_SPEED) {
                    $velOut = [];
                    \Swisseph\Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, -$snut, $cnut);
                    $xp[3] = $velOut[0];
                    $xp[4] = $velOut[1];
                    $xp[5] = $velOut[2];
                }
            }

            // Step 2: Transform ECLIPTIC → EQUATOR
            // C code lines 5478-5479
            $xOut = [];
            \Swisseph\Coordinates::coortrf2($xp, $xOut, -$seps, $ceps);
            $xp[0] = $xOut[0];
            $xp[1] = $xOut[1];
            $xp[2] = $xOut[2];
            if ($iflag & Constants::SEFLG_SPEED) {
                $velOut = [];
                \Swisseph\Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, -$seps, $ceps);
                $xp[3] = $velOut[0];
                $xp[4] = $velOut[1];
                $xp[5] = $velOut[2];
            }

            // Step 3: Apply nutation to mean ecliptic of date
            // C code lines 5483-5485
            if ($donut) {
                $nutMatrix = \Swisseph\NutationMatrix::build($dpsi, $deps, $oe, $seps, $ceps);
                $xTemp = \Swisseph\NutationMatrix::apply($nutMatrix, [$xp[0], $xp[1], $xp[2]]);
                $xp[0] = $xTemp[0];
                $xp[1] = $xTemp[1];
                $xp[2] = $xTemp[2];
                if ($iflag & Constants::SEFLG_SPEED) {
                    $velTemp = \Swisseph\NutationMatrix::apply($nutMatrix, [$xp[3], $xp[4], $xp[5]]);
                    $xp[3] = $velTemp[0];
                    $xp[4] = $velTemp[1];
                    $xp[5] = $velTemp[2];
                }
            }

            // Step 4: Precess from date to J2000
            // C code lines 5489-5491
            \Swisseph\Precession::precess($xp, $tjdEt, $iflag, 1, null); // direction=1 = J_TO_J2000
            if ($iflag & Constants::SEFLG_SPEED) {
                $vel = [$xp[3], $xp[4], $xp[5]];
                \Swisseph\Precession::precess($vel, $tjdEt, $iflag, 1, null);
                $xp[3] = $vel[0];
                $xp[4] = $vel[1];
                $xp[5] = $vel[2];
            }

            // Step 5: Convert from heliocentric to barycentric
            // C code lines 5496-5507
            if ($ipl === Constants::SE_MOON) {
                // Moon: add Earth position
                for ($i = 0; $i <= 5; $i++) {
                    $xp[$i] += $xear[$i];
                }
            } else {
                // Other planets: add Sun position
                for ($i = 0; $i <= 5; $i++) {
                    $xp[$i] += $xsun[$i];
                }
            }

            // Step 6: Convert from barycentric to observer-centric
            // C code lines 5511-5512
            for ($i = 0; $i <= 5; $i++) {
                $xp[$i] -= $xobs[$i];
            }

            // Step 7: Special case for Sun geocentric perigee/apogee
            // C code lines 5514-5516
            if ($ipl === Constants::SE_SUN && !($iflag & (Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR))) {
                for ($i = 0; $i <= 5; $i++) {
                    $xp[$i] = -$xp[$i];
                }
            }

            // Step 8: Light time correction (for light deflection)
            // C code lines 5520-5522
            $dt = sqrt(\Swisseph\VectorMath::squareSum($xp)) * Constants::AUNIT / Constants::CLIGHT / 86400.0;
            $doDefl = !($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOGDEFL);
            if ($doDefl) {
                // swi_deflect_light not yet implemented, skip for now
                // \Swisseph\Deflection::deflectLight($xp, $dt, $iflag);
            }

            // Step 9: Aberration
            // C code lines 5526+
            $doAberr = !($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOABERR);
            if ($doAberr) {
                // swi_aberr_light not yet implemented, skip for now
                // \Swisseph\Aberration::aberrLight($xp, $xobs, $iflag);
            }

            // Step 10: If not J2000, precess back from J2000 to date
            // C code lines 5568-5572
            if (!$useJ2000) {
                \Swisseph\Precession::precess($xp, $tjdEt, $iflag, -1, null); // direction=-1 = J2000_TO_J
                if ($iflag & Constants::SEFLG_SPEED) {
                    $vel = [$xp[3], $xp[4], $xp[5]];
                    \Swisseph\Precession::precess($vel, $tjdEt, $iflag, -1, null);
                    $xp[3] = $vel[0];
                    $xp[4] = $vel[1];
                    $xp[5] = $vel[2];
                }
            }

            // Step 11: Apply nutation (if not NONUT)
            // C code lines 5576-5578
            if ($donut) {
                $nutMatrix = \Swisseph\NutationMatrix::build($dpsi, $deps, $oe, $seps, $ceps);
                $xTemp = \Swisseph\NutationMatrix::apply($nutMatrix, [$xp[0], $xp[1], $xp[2]]);
                $xp[0] = $xTemp[0];
                $xp[1] = $xTemp[1];
                $xp[2] = $xTemp[2];
                if ($iflag & Constants::SEFLG_SPEED) {
                    $velTemp = \Swisseph\NutationMatrix::apply($nutMatrix, [$xp[3], $xp[4], $xp[5]]);
                    $xp[3] = $velTemp[0];
                    $xp[4] = $velTemp[1];
                    $xp[5] = $velTemp[2];
                }
            }

            // Step 12: Transform EQUATOR → ECLIPTIC
            // C code lines 5584-5587
            $xOut = [];
            \Swisseph\Coordinates::coortrf2($xp, $xOut, $seps, $ceps);
            $xp[0] = $xOut[0];
            $xp[1] = $xOut[1];
            $xp[2] = $xOut[2];
            if ($iflag & Constants::SEFLG_SPEED) {
                $velOut = [];
                \Swisseph\Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, $seps, $ceps);
                $xp[3] = $velOut[0];
                $xp[4] = $velOut[1];
                $xp[5] = $velOut[2];
            }

            // Step 13: Apply nutation to ecliptic (if not NONUT)
            // C code lines 5591-5599
            if ($donut) {
                $xOut = [];
                \Swisseph\Coordinates::coortrf2($xp, $xOut, $snut, $cnut);
                $xp[0] = $xOut[0];
                $xp[1] = $xOut[1];
                $xp[2] = $xOut[2];
                if ($iflag & Constants::SEFLG_SPEED) {
                    $velOut = [];
                    \Swisseph\Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, $snut, $cnut);
                    $xp[3] = $velOut[0];
                    $xp[4] = $velOut[1];
                    $xp[5] = $velOut[2];
                }
            }

            // Step 14: Convert CARTESIAN → POLAR (lon, lat, r) with speeds
            // C code line 5620: swi_cartpol_sp(xp, xp)
            if ($iflag & Constants::SEFLG_SPEED) {
                // Use cart_pol_sp() for conversion with speeds
                \Swisseph\Coordinates::cartPolSp($xp, $xp);
            } else {
                // Use cart_pol() for position only
                \Swisseph\Coordinates::cartPol($xp, $xp);
            }

            // Convert to degrees if not radians flag
            if (!($iflag & Constants::SEFLG_RADIANS)) {
                $xp[0] = rad2deg($xp[0]);
                $xp[1] = rad2deg($xp[1]);
                if ($iflag & Constants::SEFLG_SPEED) {
                    $xp[3] = rad2deg($xp[3]);
                    $xp[4] = rad2deg($xp[4]);
                }
            }
        }
    }

    /**
     * Apply final coordinate transformations to nodes/apsides
     * Matches C code swe_nod_aps() lines 5431-5620
     *
     * @param array $xx Array of 4 elements: [xna, xnd, xpe, xap], each is [lon,lat,r,dlon,dlat,dr]
     * @param float $tjdEt Julian day ET
     * @param int $iflag Calculation flags
     * @param int $ipl Planet number
     */
    private static function applyFinalNodApsTransformations(array &$xx, float $tjdEt, int $iflag, int $ipl): void
    {
        // Initialize save area by computing planet (C code lines 5390-5400)
        $x = [];
        $serr = '';
        $iflg0 = ($iflag & Constants::SEFLG_EPHMASK) | Constants::SEFLG_SPEED | Constants::SEFLG_TRUEPOS;
        PlanetsFunctions::calc($tjdEt, $ipl, $iflg0, $x, $serr);

        // Get Sun and Earth positions in J2000 equatorial XYZ coordinates
        // In C code these are stored in swed.pldat[SEI_SUNBARY].x and swed.pldat[SEI_EARTH].x
        // which are computed by main_planet() function in sweph.c

        // Get barycentric Sun (approximation: near zero, true offset ~0.005 AU from Jupiter)
        $xsun = \Swisseph\BarycentricPositions::getBarycentricSun($tjdEt, $iflag);

        // Get barycentric Earth (EMB - Moon offset)
        // Calculate observer position (C code lines 5404-5427)
        $xear = \Swisseph\BarycentricPositions::getBarycentricEarth($tjdEt, $iflag);
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        if ($iflag & (Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR)) {
            if ($iflag & Constants::SEFLG_HELCTR) {
                for ($i = 0; $i <= 5; $i++) {
                    $xobs[$i] = $xsun[$i];
                }
            }
        } elseif ($ipl === Constants::SE_SUN) {
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $xsun[$i];
            }
        } else {
            // Barycentric position of observer = Earth
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $xear[$i];
            }
        }
        // Get obliquity
        $useJ2000 = (bool)($iflag & Constants::SEFLG_J2000);
        $eps = \Swisseph\Obliquity::calc($useJ2000 ? 2451545.0 : $tjdEt, $iflag, 0, null);
        $seps = sin($eps);
        $ceps = cos($eps);

        // Process each of the 4 points (ascending node, descending node, perihelion, aphelion)
        for ($ij = 0; $ij < 4; $ij++) {
            $xp = &$xx[$ij];

            // Skip Earth nodes (lines 5444-5447)
            if ($ipl === Constants::SE_EARTH && $ij <= 1) {
                $xp = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
                continue;
            }

            // Step 1: Convert polar to cartesian (C code line 5242-5245)
            // Input is in degrees, need to convert to radians first

            $lon = $xp[0] * Math::DEG_TO_RAD;
            $lat = $xp[1] * Math::DEG_TO_RAD;
            $r = $xp[2];
            $dlon = $xp[3] * Math::DEG_TO_RAD;
            $dlat = $xp[4] * Math::DEG_TO_RAD;
            $dr = $xp[5];

            // Polar to cartesian with speeds in one shot (swi_polcart_sp)
            $xpCart = [];
            \Swisseph\Coordinates::polCartSp([$lon, $lat, $r, $dlon, $dlat, $dr], $xpCart);
            $xp[0] = $xpCart[0];
            $xp[1] = $xpCart[1];
            $xp[2] = $xpCart[2];
            if ($iflag & Constants::SEFLG_SPEED) {
                $xp[3] = $xpCart[3];
                $xp[4] = $xpCart[4];
                $xp[5] = $xpCart[5];
            }
            $xOut = [];
            \Swisseph\Coordinates::coortrf2($xp, $xOut, -$seps, $ceps);
            $xp[0] = $xOut[0];
            $xp[1] = $xOut[1];
            $xp[2] = $xOut[2];

            if ($iflag & Constants::SEFLG_SPEED) {
                $velOut = [];
                \Swisseph\Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, -$seps, $ceps);
                $xp[3] = $velOut[0];
                $xp[4] = $velOut[1];
                $xp[5] = $velOut[2];
            }

            // Step 3: Precess from date to J2000 (C code lines 5463-5466)
            // swi_precess(xp, tjd_et, iflag, J_TO_J2000);
            \Swisseph\Precession::precess($xp, $tjdEt, $iflag, 1, null); // direction=1 = J_TO_J2000

            if ($iflag & Constants::SEFLG_SPEED) {
                $vel = [$xp[3], $xp[4], $xp[5]];
                \Swisseph\Precession::precess($vel, $tjdEt, $iflag, 1, null);
                $xp[3] = $vel[0];
                $xp[4] = $vel[1];
                $xp[5] = $vel[2];
            }

            // Step 3b: Add barycentric Sun to convert from heliocentric to barycentric (C code lines 5470-5476)
            if ($ipl === Constants::SE_MOON) {
                // Moon: add Earth position
                for ($i = 0; $i <= 5; $i++) {
                    $xp[$i] += $xear[$i];
                }
            } else {
                // Other planets: add Sun position
                for ($j = 0; $j <= 5; $j++) {
                    $xp[$j] += $xsun[$j];
                }
            }

            // Step 3c: Subtract observer position to get geocentric (C code lines 5479-5481)
            for ($j = 0; $j <= 5; $j++) {
                $xp[$j] -= $xobs[$j];
            }

            // Special case for Sun geocentric perigee/apogee (C code lines 5482-5484)
            if ($ipl === Constants::SE_SUN && !($iflag & (Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR))) {
                for ($j = 0; $j <= 5; $j++) {
                    $xp[$j] = -$xp[$j];
                }
            }

            // Step 4: If not J2000, precess back to date (C code lines 5545-5549)
            if (!$useJ2000) {
                \Swisseph\Precession::precess($xp, $tjdEt, $iflag, -1, null); // direction=-1 = J2000_TO_J

                if ($iflag & Constants::SEFLG_SPEED) {
                    $vel = [$xp[3], $xp[4], $xp[5]];
                    \Swisseph\Precession::precess($vel, $tjdEt, $iflag, -1, null);
                    $xp[3] = $vel[0];
                    $xp[4] = $vel[1];
                    $xp[5] = $vel[2];
                }
            }

            // Step 5: Apply nutation (C code lines 5553-5554)
            if (!($iflag & Constants::SEFLG_NONUT)) {
                $nutModel = \Swisseph\Nutation::selectModelFromFlags($iflag);
                [$dpsi, $deps] = \Swisseph\Nutation::calc($tjdEt, $nutModel, false);
                $nutMatrix = \Swisseph\NutationMatrix::build($dpsi, $deps, $eps, $seps, $ceps);

                $xTemp = \Swisseph\NutationMatrix::apply($nutMatrix, $xp);
                $xp[0] = $xTemp[0];
                $xp[1] = $xTemp[1];
                $xp[2] = $xTemp[2];

                if ($iflag & Constants::SEFLG_SPEED) {
                    $velTemp = \Swisseph\NutationMatrix::apply($nutMatrix, [$xp[3], $xp[4], $xp[5]]);
                    $xp[3] = $velTemp[0];
                    $xp[4] = $velTemp[1];
                    $xp[5] = $velTemp[2];
                }
            }

            // Step 6: Transform to ecliptic (C code lines 5561-5568)
            $xOut2 = [];
            \Swisseph\Coordinates::coortrf2($xp, $xOut2, $seps, $ceps);
            $xp[0] = $xOut2[0];
            $xp[1] = $xOut2[1];
            $xp[2] = $xOut2[2];

            if ($iflag & Constants::SEFLG_SPEED) {
                $velOut2 = [];
                \Swisseph\Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut2, $seps, $ceps);
                $xp[3] = $velOut2[0];
                $xp[4] = $velOut2[1];
                $xp[5] = $velOut2[2];
            }

            if (!($iflag & Constants::SEFLG_NONUT)) {
                $nutModel = \Swisseph\Nutation::selectModelFromFlags($iflag);
                [$dpsi, $deps] = \Swisseph\Nutation::calc($tjdEt, $nutModel, false);
                $snut = sin($deps);
                $cnut = cos($deps);

                $xOut3 = [];
                \Swisseph\Coordinates::coortrf2($xp, $xOut3, $snut, $cnut);
                $xp[0] = $xOut3[0];
                $xp[1] = $xOut3[1];
                $xp[2] = $xOut3[2];

                if ($iflag & Constants::SEFLG_SPEED) {
                    $velOut3 = [];
                    \Swisseph\Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut3, $snut, $cnut);
                    $xp[3] = $velOut3[0];
                    $xp[4] = $velOut3[1];
                    $xp[5] = $velOut3[2];
                }
            }

            // Step 7: Cartesian to polar (C code line 5602)
            \Swisseph\Coordinates::cartPol($xp, $xp);

            // Convert from radians to degrees (C code lines 5607-5615)
            if (!($iflag & Constants::SEFLG_RADIANS)) {
                $xp[0] = Math::radToDeg($xp[0]);
                $xp[1] = Math::radToDeg($xp[1]);
                if (isset($xp[3])) {
                    $xp[3] = Math::radToDeg($xp[3]);
                    $xp[4] = Math::radToDeg($xp[4]);
                }
            }
        }
    }

    /**
     * Convert nodes/apsides from cartesian to spherical coordinates
     * Internal helper matching C API behavior
     *
     * @param array $cart Cartesian [x,y,z,vx,vy,vz]
     * @param int $iflag Flags
     * @param float $jdEt Julian day ET
     * @return array Spherical [lon,lat,r,dlon,dlat,dr]
     */
    /**
     * Convert nodes/apsides from cartesian to spherical coordinates.
     * For osculating (true) nodes, applies full transformation cycle matching C code (lines 5433-5618):
     * 1. date ecliptic → equator (with reverse nutation if applicable)
     * 2. date equator → J2000 equator (precession)
     * 3. J2000 equator → date equator (precession + nutation if applicable)
     * 4. date equator → ecliptic
     * 5. cartesian → spherical
     */
    private static function convertNodApsToSpherical(
        array $cart,
        int $iflag,
        float $jdEt,
        bool $isTrueNodaps = false
    ): array {
        if (getenv('DEBUG_OSCU')) {
            error_log(sprintf("convertNodApsToSpherical INPUT: cart=[%.10f, %.10f, %.10f], isTrueNodaps=%d",
                $cart[0], $cart[1], $cart[2], $isTrueNodaps ? 1 : 0));
        }

        // For true (osculating) nodes, coordinates are already in ecliptic of date
        // Just convert XYZ → spherical (lon, lat, r)
        if ($isTrueNodaps) {
            $pol = [];
            Coordinates::cartPol($cart, $pol);

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("convertNodApsToSpherical OUTPUT: pol=[%.10f°, %.10f°, %.10f AU]",
                    rad2deg($pol[0]), rad2deg($pol[1]), $pol[2]));
            }

            // Return in format [lon, lat, r, dlon, dlat, dr]
            $result = [
                $pol[0],  // longitude (radians)
                $pol[1],  // latitude (radians)
                $pol[2],  // distance (AU)
                isset($cart[3]) ? ($cart[3] ?? 0.0) : 0.0,  // speed lon (placeholder)
                isset($cart[4]) ? ($cart[4] ?? 0.0) : 0.0,  // speed lat (placeholder)
                isset($cart[5]) ? ($cart[5] ?? 0.0) : 0.0,  // speed rad (placeholder)
            ];

            // Convert to degrees if not radians flag
            if (!($iflag & Constants::SEFLG_RADIANS)) {
                $result[0] = rad2deg($result[0]);
                $result[1] = rad2deg($result[1]);
            }

            return $result;
        }

        // For mean nodes (OLD CODE), apply transformation cycle per C code
        if (false) {  // Temporarily disabled
            $xp = $cart;  // Copy to avoid modifying input            // Get obliquity
            $errTemp = null;
            $oe = ($iflag & Constants::SEFLG_J2000)
                ? Obliquity::calc(self::J2000, $iflag, 0, $errTemp)
                : Obliquity::calc($jdEt, $iflag, 0, $errTemp);
            $seps = sin($oe);
            $ceps = cos($oe);

            // Step 1: to equator (reverse nutation if applicable)
            // C code line 5442: swi_coortrf2(xp, xp, -swed.nut.snut, swed.nut.cnut);
            if (($iflag & Constants::SEFLG_NONUT) === 0) {
                // Get nutation angles
                [$dpsi, $deps] = Nutation::calc($jdEt, Nutation::MODEL_DEFAULT, true);
                $snut = sin($dpsi);
                $cnut = cos($dpsi);

                // Reverse nutation: -snut instead of snut
                $xpOut = [];
                Coordinates::coortrf2([$xp[0], $xp[1], $xp[2]], $xpOut, -$snut, $cnut);
                $xp[0] = $xpOut[0];
                $xp[1] = $xpOut[1];
                $xp[2] = $xpOut[2];

                if (isset($xp[3])) {
                    $velOut = [];
                    Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, -$snut, $cnut);
                    $xp[3] = $velOut[0];
                    $xp[4] = $velOut[1];
                    $xp[5] = $velOut[2];
                }
            }

            // Ecliptic → equator rotation
            // C code line 5448: swi_coortrf2(xp, xp, -oe->seps, oe->ceps);
            $xpOut = [];
            Coordinates::coortrf2([$xp[0], $xp[1], $xp[2]], $xpOut, -$seps, $ceps);
            $xp[0] = $xpOut[0];
            $xp[1] = $xpOut[1];
            $xp[2] = $xpOut[2];

            if (isset($xp[3])) {
                $velOut = [];
                Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, -$seps, $ceps);
                $xp[3] = $velOut[0];
                $xp[4] = $velOut[1];
                $xp[5] = $velOut[2];
            }

            // Step 3: to mean ecliptic of date (nutation)
            // C code lines 5450-5455: if (is_true_nodaps) { if (!(iflag & SEFLG_NONUT)) swi_nutate(xp, iflag, TRUE); }
            if (($iflag & Constants::SEFLG_NONUT) === 0) {
                [$dpsi3, $deps3] = Nutation::calc($jdEt, Nutation::MODEL_DEFAULT, true);
                $nutMatrix = NutationMatrix::build($dpsi3, $deps3, $oe, $seps, $ceps);
                $xpTemp = NutationMatrix::apply($nutMatrix, [$xp[0], $xp[1], $xp[2]]);
                $xp[0] = $xpTemp[0];
                $xp[1] = $xpTemp[1];
                $xp[2] = $xpTemp[2];

                if (isset($xp[3])) {
                    $velTemp = NutationMatrix::apply($nutMatrix, [$xp[3], $xp[4], $xp[5]]);
                    $xp[3] = $velTemp[0];
                    $xp[4] = $velTemp[1];
                    $xp[5] = $velTemp[2];
                }
            }

            // Step 4: to J2000 (precession)
            // C code lines 5460-5462: swi_precess(xp, tjd_et, iflag, J_TO_J2000);
            Precession::precess($xp, $jdEt, $iflag, 1, $errTemp); // J_TO_J2000 = 1
            if (isset($xp[3])) {
                $vel = [$xp[3], $xp[4], $xp[5]];
                Precession::precess($vel, $jdEt, $iflag, 1, $errTemp);
                $xp[3] = $vel[0];
                $xp[4] = $vel[1];
                $xp[5] = $vel[2];
            }

            // Now xp is in J2000 equatorial cartesian
            // Apply reverse transformations: J2000 → date ecliptic

            // Step 5: precession back to date (if not J2000 output)
            // C code lines 5544-5548: if (!(iflag & SEFLG_J2000)) swi_precess(xp, tjd_et, iflag, J2000_TO_J);
            if (($iflag & Constants::SEFLG_J2000) === 0) {
                Precession::precess($xp, $jdEt, $iflag, -1, $errTemp); // J2000_TO_J = -1
                if (isset($xp[3])) {
                    $vel = [$xp[3], $xp[4], $xp[5]];
                    Precession::precess($vel, $jdEt, $iflag, -1, $errTemp);
                    $xp[3] = $vel[0];
                    $xp[4] = $vel[1];
                    $xp[5] = $vel[2];
                }
            }

            // Step 6: nutation
            // C code lines 5552-5553: if (!(iflag & SEFLG_NONUT)) swi_nutate(xp, iflag, FALSE);
            if (($iflag & Constants::SEFLG_NONUT) === 0) {
                // Note: NutationMatrix already calculated above with same jdEt, reuse
                $xpTemp = NutationMatrix::apply($nutMatrix, [$xp[0], $xp[1], $xp[2]]);
                $xp[0] = $xpTemp[0];
                $xp[1] = $xpTemp[1];
                $xp[2] = $xpTemp[2];

                if (isset($xp[3])) {
                    $velTemp = NutationMatrix::apply($nutMatrix, [$xp[3], $xp[4], $xp[5]]);
                    $xp[3] = $velTemp[0];
                    $xp[4] = $velTemp[1];
                    $xp[5] = $velTemp[2];
                }
            }

            // Now we have equatorial cartesian coordinates
            // Step 7: transformation to ecliptic
            // C code lines 5562-5564: swi_coortrf2(xp, xp, oe->seps, oe->ceps);
            Coordinates::coortrf2([$xp[0], $xp[1], $xp[2]], $xpOut, $seps, $ceps);
            $xp[0] = $xpOut[0];
            $xp[1] = $xpOut[1];
            $xp[2] = $xpOut[2];

            if (isset($xp[3])) {
                Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, $seps, $ceps);
                $xp[3] = $velOut[0];
                $xp[4] = $velOut[1];
                $xp[5] = $velOut[2];
            }

            // Step 8: nutation rotation in ecliptic frame
            // C code lines 5565-5568: if (!(iflag & SEFLG_NONUT)) swi_coortrf2(xp, xp, swed.nut.snut, swed.nut.cnut);
            if (($iflag & Constants::SEFLG_NONUT) === 0) {
                // Forward nutation rotation: +snut (not -snut)
                Coordinates::coortrf2([$xp[0], $xp[1], $xp[2]], $xpOut, $snut, $cnut);
                $xp[0] = $xpOut[0];
                $xp[1] = $xpOut[1];
                $xp[2] = $xpOut[2];

                if (isset($xp[3])) {
                    Coordinates::coortrf2([$xp[3], $xp[4], $xp[5]], $velOut, $snut, $cnut);
                    $xp[3] = $velOut[0];
                    $xp[4] = $velOut[1];
                    $xp[5] = $velOut[2];
                }
            }

            // Now we have ecliptic cartesian coordinates
            $cart = $xp;  // Use transformed coordinates
        }

        // Extract position
        $x = $cart[0];
        $y = $cart[1];
        $z = $cart[2];        if (getenv('DEBUG_OSCU')) {
            fprintf(STDERR, "convertNodApsToSpherical: cart=[%.10f, %.10f, %.10f]\n", $x, $y, $z);
        }

        // Calculate spherical coordinates
        $r = sqrt($x * $x + $y * $y + $z * $z);
        $rxy = sqrt($x * $x + $y * $y);

        $lon = atan2($y, $x);
        if ($lon < 0.0) {
            $lon += Math::TWO_PI;
        }

        $lat = ($rxy === 0.0) ? (($z >= 0) ? M_PI / 2 : -M_PI / 2) : atan($z / $rxy);

        // Convert velocities if present
        $dlon = 0.0;
        $dlat = 0.0;
        $dr = 0.0;

        if (isset($cart[3]) && isset($cart[4]) && isset($cart[5])) {
            $vx = $cart[3];
            $vy = $cart[4];
            $vz = $cart[5];

            // Convert cartesian velocities to spherical
            // Using chain rule: d(lon)/dt, d(lat)/dt, d(r)/dt
            if ($rxy > 0) {
                $dlon = ($x * $vy - $y * $vx) / ($rxy * $rxy);
                $dlat = ($vz * $rxy - $z * ($x * $vx + $y * $vy) / $rxy) / ($r * $r);
            }
            $dr = ($x * $vx + $y * $vy + $z * $vz) / $r;
        }

        // Apply output format
        $isRadians = (bool)($iflag & Constants::SEFLG_RADIANS);
        if (!$isRadians) {
            $lon = Math::radToDeg($lon);
            $lat = Math::radToDeg($lat);
            $dlon = Math::radToDeg($dlon);
            $dlat = Math::radToDeg($dlat);
        }

        return [$lon, $lat, $r, $dlon, $dlat, $dr];
    }
}
