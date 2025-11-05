<?php

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\DeltaT;
use Swisseph\ErrorCodes;
use Swisseph\Formatter;
use Swisseph\Math;
use Swisseph\Obliquity;
use Swisseph\Output;
use Swisseph\PlanetHelper;
use Swisseph\Precession;
use Swisseph\Sun;
use Swisseph\Moon;
use Swisseph\Mercury;
use Swisseph\Venus;
use Swisseph\Mars;
use Swisseph\Jupiter;
use Swisseph\Saturn;
use Swisseph\Uranus;
use Swisseph\Neptune;
use Swisseph\Pluto;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwephCalculator;

/**
 * Реализация swe_calc и swe_calc_ut как внутренних методов с разнесением логики.
 */
class PlanetsFunctions
{
    /**
     * Mirror of swe_calc logic (moved from global function).
     * @param float $jd_tt
     * @param int $ipl
     * @param int $iflag
     * @param array $xx by-ref
     * @param string|null $serr by-ref
     * @return int
     */
    public static function calc(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $xx = Output::emptyForFlags($iflag);
        // Basic arg validation
        // Allow SE_SUN (0) through SE_PLUTO (9), plus SE_EARTH (14)
        if (($ipl < Constants::SE_SUN || $ipl > Constants::SE_PLUTO) && $ipl !== Constants::SE_EARTH) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, "ipl=$ipl out of supported range");
            return Constants::SE_ERR;
        }
        // Validate mutually exclusive source flags
        $sourceFlags = 0;
        if ($iflag & Constants::SEFLG_JPLEPH) {
            $sourceFlags++;
        }
        if ($iflag & Constants::SEFLG_SWIEPH) {
            $sourceFlags++;
        }
        if ($iflag & Constants::SEFLG_MOSEPH) {
            $sourceFlags++;
        }
        if ($sourceFlags > 1) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                'Conflicting ephemeris source flags (choose one of JPLEPH/SWIEPH/MOSEPH)'
            );
            return Constants::SE_ERR;
        }

        // Determine ephemeris source (default to SWIEPH if not specified)
        $epheflag = Constants::SEFLG_SWIEPH; // Default
        if ($iflag & Constants::SEFLG_JPLEPH) {
            $epheflag = Constants::SEFLG_JPLEPH;
        } elseif ($iflag & Constants::SEFLG_MOSEPH) {
            $epheflag = Constants::SEFLG_MOSEPH;
        } elseif ($iflag & Constants::SEFLG_SWIEPH) {
            $epheflag = Constants::SEFLG_SWIEPH;
        }

        // Route to Swiss Ephemeris calculator if SEFLG_SWIEPH
        if ($epheflag === Constants::SEFLG_SWIEPH) {
            // Convert external planet number to internal (SE_* -> SEI_*)
            if (!isset(SwephConstants::PNOEXT2INT[$ipl])) {
                $serr = ErrorCodes::compose(ErrorCodes::UNSUPPORTED, "Planet $ipl not supported in Swiss Ephemeris");
                return Constants::SE_ERR;
            }
            $ipli = SwephConstants::PNOEXT2INT[$ipl];

            // Call SwephPlanCalculator (port of C sweplan())
            // This handles Sun/Earth/Moon dependencies and heliocentric->barycentric conversion
            // Use doSave=true to cache results in SwedState
            // CRITICAL: Pass $ipl (external index) to distinguish SE_SUN from SE_EARTH (both map to SEI_*=0)
            $xpret = []; // Planet position output
            $xperet = null; // Earth position (not needed here)
            $xpsret = null; // Sun barycenter (not needed here)
            $xpmret = null; // Moon position (not needed here)

            $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
                $jd_tt,
                $ipli,
                $ipl,  // Pass external index to distinguish SUN/EARTH!
                SwephConstants::SEI_FILE_PLANET,
                $iflag,
                true, // doSave
                $xpret,
                $xperet,
                $xpsret,
                $xpmret,
                $serr
            );

            if (getenv('DEBUG_OSCU') || getenv('DEBUG_MOON')) {
                $dist_xpret = sqrt($xpret[0]*$xpret[0] + $xpret[1]*$xpret[1] + $xpret[2]*$xpret[2]);
                error_log(sprintf("DEBUG PlanetsFunctions after SwephPlanCalculator: ipl=%d, xpret=[%.15f, %.15f, %.15f], dist=%.9f AU",
                    $ipl, $xpret[0] ?? 0, $xpret[1] ?? 0, $xpret[2] ?? 0, $dist_xpret));
                // Check what's in slot 0 (EMB/EARTH) after SwephPlanCalculator
                $swed_check = \Swisseph\SwephFile\SwedState::getInstance();
                $slot0_check = &$swed_check->pldat[0];
                error_log(sprintf("DEBUG PlanetsFunctions: Slot 0 AFTER SwephPlanCalculator: x=[%.15f, %.15f, %.15f]",
                    $slot0_check->x[0] ?? 0, $slot0_check->x[1] ?? 0, $slot0_check->x[2] ?? 0));
            }

            if ($retc < 0) { // Check for ERR or NOT_AVAILABLE
                // Return error through serr
                return Constants::SE_ERR;
            }

            // CRITICAL: Special handling for Moon with topocentric/light-time transformations
            // From sweph.c:727: if ((retc = app_pos_etc_moon(iflag, serr)) != OK)
            if (getenv('DEBUG_MOON')) {
                error_log(sprintf("DEBUG [calc] Checking Moon: ipl=%d, SE_MOON=%d, match=%d",
                    $ipl, Constants::SE_MOON, ($ipl === Constants::SE_MOON) ? 1 : 0));
            }
            if ($ipl === Constants::SE_MOON) {
                if (getenv('DEBUG_MOON')) {
                    error_log("DEBUG [calc] Entering MoonTransform::appPosEtc()");
                }
                // Moon requires full app_pos_etc_moon transformation
                // This includes: topocentric correction, light-time, aberration, precession, coordinate conversion
                $retc = \Swisseph\Swe\Moon\MoonTransform::appPosEtc($iflag, $serr);
                if ($retc !== Constants::SE_OK) {
                    return Constants::SE_ERR;
                }

                // Get result from pdp->xreturn (already filled by MoonTransform)
                $swed = \Swisseph\SwephFile\SwedState::getInstance();
                $pdp = &$swed->pldat[SwephConstants::SEI_MOON];

                // Select appropriate slice from xreturn based on flags
                $offset = 0;
                if ($iflag & Constants::SEFLG_EQUATORIAL) {
                    if ($iflag & Constants::SEFLG_XYZ) {
                        $offset = 18; // Equatorial XYZ
                    } else {
                        $offset = 12; // Equatorial polar
                    }
                } else {
                    if ($iflag & Constants::SEFLG_XYZ) {
                        $offset = 6; // Ecliptic XYZ
                    } else {
                        $offset = 0; // Ecliptic polar
                    }
                }

                // Copy result to output
                for ($i = 0; $i < 6; $i++) {
                    $xx[$i] = $pdp->xreturn[$offset + $i];
                }

                $serr = null;
                return $iflag;
            }

            // Copy barycentric result
            $xx = $xpret;

            if (getenv('DEBUG_OSCU') || getenv('DEBUG_MOON')) {
                $dist_xx = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
                error_log(sprintf("DEBUG [calc ipl=%d] xx AFTER copy from xpret=[%.15f, %.15f, %.15f], dist=%.9f AU",
                    $ipl, $xx[0], $xx[1], $xx[2], $dist_xx));
            }

            // Debug: Log coordinates after SwephCalculator (should be ecliptic J2000)
            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG [calc ipl=%d tjd=%.2f] after SwephCalculator (ecliptic J2000): xx=[%.10f, %.10f, %.10f, %.10f, %.10f, %.10f]",
                    $ipl, $jd_tt, $xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]));
                error_log(sprintf("DEBUG [calc ipl=%d tjd=%.2f] iflag=0x%X, SEFLG_J2000=0x%X, check=0x%X",
                    $ipl, $jd_tt, $iflag, Constants::SEFLG_J2000, $iflag & Constants::SEFLG_J2000));
            }

            // CRITICAL: Apply LIGHT-TIME, DEFLECTION, ABERRATION here (TODO)
            // C code: sweph.c:2542-2707

            // Convert barycentric to heliocentric if requested
            // C code sweph.c:2691-2695 in app_pos_etc_plan()
            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG HELCTR check: iflag=0x%X, SEFLG_HELCTR=0x%X, has_HELCTR=%d",
                    $iflag, Constants::SEFLG_HELCTR, ($iflag & Constants::SEFLG_HELCTR) ? 1 : 0));
            }
            if ($iflag & Constants::SEFLG_HELCTR) {
                $swed = \Swisseph\SwephFile\SwedState::getInstance();
                $psdp = &$swed->pldat[SwephConstants::SEI_SUNBARY];

                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("DEBUG SEFLG_HELCTR ENTERED: subtracting Sun barycenter [%.15f, %.15f, %.15f] from planet",
                        $psdp->x[0], $psdp->x[1], $psdp->x[2]));
                }

                // Heliocentric = Barycentric - Sun barycenter
                for ($i = 0; $i <= 5; $i++) {
                    $xx[$i] -= $psdp->x[$i];
                }

                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("DEBUG after SEFLG_HELCTR: xx=[%.15f, %.15f, %.15f]",
                        $xx[0], $xx[1], $xx[2]));
                }
            }

            // Convert barycentric to geocentric (default when neither HELCTR nor BARYCTR)
            // CRITICAL: Geocentric conversion must happen BEFORE precession! (C code sweph.c:2711-2726)
            // C code order: light-time → geocentric → deflection/aberration → precession
            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG checking geocentric: iflag=0x%X, HELCTR=0x%X, BARYCTR=0x%X, has_HELCTR=%d, has_BARYCTR=%d",
                    $iflag, Constants::SEFLG_HELCTR, Constants::SEFLG_BARYCTR,
                    ($iflag & Constants::SEFLG_HELCTR) ? 1 : 0,
                    ($iflag & Constants::SEFLG_BARYCTR) ? 1 : 0));
            }
            if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR)) {
                // Special case: geocentric Earth is always [0, 0, 0]
                if ($ipl === Constants::SE_EARTH) {
                    for ($i = 0; $i <= 5; $i++) {
                        $xx[$i] = 0.0;
                    }
                    if (getenv('DEBUG_OSCU')) {
                        error_log("DEBUG GEOCENTRIC EARTH: returning [0, 0, 0, 0, 0, 0]");
                    }
                } else {
                    // C code sweph.c:2711-2726: subtract Earth for geocentric coordinates
                    $swed = \Swisseph\SwephFile\SwedState::getInstance();
                    $pedp = &$swed->pldat[SwephConstants::SEI_EARTH];

                    if (getenv('DEBUG_OSCU') || getenv('DEBUG_MOON')) {
                        error_log(sprintf("DEBUG GEOCENTRIC: ipl=%d, Earth pedp->teval=%.2f, current tjd=%.2f",
                            $ipl, $pedp->teval, $jd_tt));
                        error_log(sprintf("DEBUG GEOCENTRIC: subtracting Earth [%.15f, %.15f, %.15f] from planet [%.15f, %.15f, %.15f]",
                            $pedp->x[0], $pedp->x[1], $pedp->x[2], $xx[0], $xx[1], $xx[2]));
                    }

                    // Geocentric = Barycentric - Earth
                    for ($i = 0; $i <= 5; $i++) {
                        $xx[$i] -= $pedp->x[$i];
                    }

                    if (getenv('DEBUG_OSCU') || getenv('DEBUG_MOON')) {
                        $dist = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
                        error_log(sprintf("DEBUG after GEOCENTRIC: ipl=%d, xx=[%.15f, %.15f, %.15f], dist=%.9f AU",
                            $ipl, $xx[0], $xx[1], $xx[2], $dist));
                    }
                }
            }

            // CRITICAL: Apply precession if NOT SEFLG_J2000
            // Must be done AFTER geocentric conversion! (C code: sweph.c:2765-2768)
            // C code order: light-time → geocentric → deflection/aberration → PRECESSION
            if (!($iflag & Constants::SEFLG_J2000)) {
                if (getenv('DEBUG_MOON')) {
                    $dist_before = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
                    error_log(sprintf("DEBUG [calc ipl=%d] BEFORE precession: xx=[%.15f, %.15f, %.15f], dist=%.9f AU",
                        $ipl, $xx[0], $xx[1], $xx[2], $dist_before));
                }

                // Precess from J2000 to epoch of date
                Precession::precess($xx, $jd_tt, $iflag, Constants::J2000_TO_J);

                if (getenv('DEBUG_MOON')) {
                    $dist_after = sqrt($xx[0]*$xx[0] + $xx[1]*$xx[1] + $xx[2]*$xx[2]);
                    error_log(sprintf("DEBUG [calc ipl=%d] AFTER precession: xx=[%.15f, %.15f, %.15f], dist=%.9f AU",
                        $ipl, $xx[0], $xx[1], $xx[2], $dist_after));
                }

                // Also precess velocities (speeds) if requested
                // C code sweph.c:2768 calls swi_precess_speed()
                if ($iflag & Constants::SEFLG_SPEED) {
                    Precession::precessSpeed($xx, $jd_tt, $iflag, Constants::J2000_TO_J);
                }

                if (getenv('DEBUG_OSCU')) {
                    error_log(sprintf("DEBUG after precession (epoch of date): xx=[%.10f, %.10f, %.10f, %.10f, %.10f, %.10f]",
                        $xx[0], $xx[1], $xx[2], $xx[3], $xx[4], $xx[5]));
                }
            }

            // Apply coordinate transformations and populate xreturn[24]
            // C code: app_pos_rest() in sweph.c:2776
            $swed = \Swisseph\SwephFile\SwedState::getInstance();

            // CRITICAL: Determine correct slot for result storage
            // For SE_SUN, data comes from SUNBARY (slot 10), not EMB (slot 0)
            // C code uses pdp = &swed.pldat[ipli], but for SUN ipli=0 while data is in slot 10!
            // We need to store result in the slot that contains the SOURCE data
            $result_slot = $ipli;
            if ($ipl === Constants::SE_SUN) {
                $result_slot = SwephConstants::SEI_SUNBARY;  // Store in SUNBARY slot, not EMB!
            }
            $pdp = &$swed->pldat[$result_slot];

            if (getenv('DEBUG_OSCU')) {
                error_log(sprintf("DEBUG [calc ipl=%d] using result_slot=%d, xx before appPosRest=[%.15f, %.15f, %.15f]",
                    $ipl, $result_slot, $xx[0], $xx[1], $xx[2]));
            }

            // Get epsilon (obliquity) - use J2000 constant if J2000 flag set
            if ($iflag & Constants::SEFLG_J2000) {
                $eps = 0.40909280422232897; // J2000 obliquity in radians (23.4392911°)
            } else {
                $eps = \Swisseph\Obliquity::meanObliquityRadFromJdTT($jd_tt);
            }
            $seps = sin($eps);
            $ceps = cos($eps);

            // Call app_pos_rest to fill xreturn[24]
            // This populates:
            //   [0..5]: ecliptic polar (lon, lat, r, dlon, dlat, dr)
            //   [6..11]: ecliptic XYZ (x, y, z, dx, dy, dz)
            //   [12..17]: equatorial polar (RA, Dec, r, dRA, dDec, dr)
            //   [18..23]: equatorial XYZ (x, y, z, dx, dy, dz)
            \Swisseph\CoordinateTransform::appPosRest($pdp, $iflag, $xx, $seps, $ceps);

            // Select appropriate slice from xreturn based on flags
            // Copy values element by element (not array_slice which creates new array)
            $offset = 0;
            if ($iflag & Constants::SEFLG_EQUATORIAL) {
                if ($iflag & Constants::SEFLG_XYZ) {
                    // Equatorial XYZ: xreturn[18..23]
                    $offset = 18;
                } else {
                    // Equatorial polar: xreturn[12..17]
                    $offset = 12;
                }
            } else {
                if ($iflag & Constants::SEFLG_XYZ) {
                    // Ecliptic XYZ: xreturn[6..11]
                    $offset = 6;
                } else {
                    // Ecliptic polar: xreturn[0..5]
                    $offset = 0;
                }
            }

            // Copy 6 elements from xreturn to output array $xx
            for ($i = 0; $i < 6; $i++) {
                $xx[$i] = $pdp->xreturn[$offset + $i];
            }

            $serr = null;
            return $iflag;
        }        // Validate coordinate system flags
        // NOTE: In C code, EQUATORIAL + XYZ are combined for "equatorial cartesian"
        // Temporarily disable this check for osculating nodes implementation
        /*
        if (($iflag & Constants::SEFLG_EQUATORIAL) && ($iflag & Constants::SEFLG_XYZ)) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, 'EQUATORIAL and XYZ flags are mutually exclusive');
            return Constants::SE_ERR;
        }
        */

        // Implement SE_SUN (approximate)
        if ($ipl === Constants::SE_SUN) {
            [$lon, $lat, $dist] = Sun::eclipticLonLatDist($jd_tt);
            $xx = Formatter::eclipticSphericalToOutput($lon, $lat, $dist, $iflag, $jd_tt);
            if ($iflag & Constants::SEFLG_SPEED) {
                $dt = 0.0001; // days (8.64 seconds) - matches C code PLAN_SPEED_INTV
                [$lon_p, $lat_p, $dist_p] = Sun::eclipticLonLatDist($jd_tt + $dt);
                [$lon_m, $lat_m, $dist_m] = Sun::eclipticLonLatDist($jd_tt - $dt);
                $dLon = Math::angleDiffRad($lon_p, $lon_m) / (2 * $dt);
                $dLat = ($lat_p - $lat_m) / (2 * $dt);
                $dR = ($dist_p - $dist_m) / (2 * $dt);
                $isRad = (bool)($iflag & Constants::SEFLG_RADIANS);
                if (!$isRad) {
                    $dLon = Math::radToDeg($dLon);
                    $dLat = Math::radToDeg($dLat);
                }
                if ($iflag & Constants::SEFLG_XYZ) {
                    $clp = cos($lon_p);
                    $slp = sin($lon_p);
                    $cbp = cos($lat_p);
                    $sbp = sin($lat_p);
                    $x_p = $dist_p * $cbp * $clp;
                    $y_p = $dist_p * $cbp * $slp;
                    $z_p = $dist_p * $sbp;
                    $clm = cos($lon_m);
                    $slm = sin($lon_m);
                    $cbm = cos($lat_m);
                    $sbm = sin($lat_m);
                    $x_m = $dist_m * $cbm * $clm;
                    $y_m = $dist_m * $cbm * $slm;
                    $z_m = $dist_m * $sbm;
                    $xx[3] = ($x_p - $x_m) / (2 * $dt);
                    $xx[4] = ($y_p - $y_m) / (2 * $dt);
                    $xx[5] = ($z_p - $z_m) / (2 * $dt);
                } elseif ($iflag & Constants::SEFLG_EQUATORIAL) {
                    $eps = Obliquity::meanObliquityRadFromJdTT($jd_tt);
                    [$ra_p, $dec_p] = Coordinates::eclipticToEquatorialRad($lon_p, $lat_p, $dist_p, $eps);
                    [$ra_m, $dec_m] = Coordinates::eclipticToEquatorialRad($lon_m, $lat_m, $dist_m, $eps);
                    $dRa = Math::angleDiffRad($ra_p, $ra_m) / (2 * $dt);
                    $dDec = ($dec_p - $dec_m) / (2 * $dt);
                    if (!$isRad) {
                        $dRa = Math::radToDeg($dRa);
                        $dDec = Math::radToDeg($dDec);
                    }
                    $xx[3] = $dRa;
                    $xx[4] = $dDec;
                    $xx[5] = $dR;
                } else {
                    $xx[3] = $dLon;
                    $xx[4] = $dLat;
                    $xx[5] = $dR;
                }
            }
            $serr = null;
            return 0;
        }

        // Implement Moon (SE_MOON) approximate
        if ($ipl === Constants::SE_MOON) {
            [$lon, $lat, $dist] = Moon::eclipticLonLatDist($jd_tt);
            $xx = Formatter::eclipticSphericalToOutput($lon, $lat, $dist, $iflag, $jd_tt);
            if ($iflag & Constants::SEFLG_SPEED) {
                $dt = 0.0001; // days (8.64 seconds) - matches C code PLAN_SPEED_INTV
                [$lon_p, $lat_p, $dist_p] = Moon::eclipticLonLatDist($jd_tt + $dt);
                [$lon_m, $lat_m, $dist_m] = Moon::eclipticLonLatDist($jd_tt - $dt);
                $dLon = Math::angleDiffRad($lon_p, $lon_m) / (2 * $dt);
                $dLat = ($lat_p - $lat_m) / (2 * $dt);
                $dR = ($dist_p - $dist_m) / (2 * $dt);
                $isRad = (bool)($iflag & Constants::SEFLG_RADIANS);
                if (!$isRad) {
                    $dLon = Math::radToDeg($dLon);
                    $dLat = Math::radToDeg($dLat);
                }
                if ($iflag & Constants::SEFLG_XYZ) {
                    $clp = cos($lon_p);
                    $slp = sin($lon_p);
                    $cbp = cos($lat_p);
                    $sbp = sin($lat_p);
                    $x_p = $dist_p * $cbp * $clp;
                    $y_p = $dist_p * $cbp * $slp;
                    $z_p = $dist_p * $sbp;
                    $clm = cos($lon_m);
                    $slm = sin($lon_m);
                    $cbm = cos($lat_m);
                    $sbm = sin($lat_m);
                    $x_m = $dist_m * $cbm * $clm;
                    $y_m = $dist_m * $cbm * $slm;
                    $z_m = $dist_m * $sbm;
                    $xx[3] = ($x_p - $x_m) / (2 * $dt);
                    $xx[4] = ($y_p - $y_m) / (2 * $dt);
                    $xx[5] = ($z_p - $z_m) / (2 * $dt);
                } elseif ($iflag & Constants::SEFLG_EQUATORIAL) {
                    $eps = Obliquity::meanObliquityRadFromJdTT($jd_tt);
                    [$ra_p, $dec_p] = Coordinates::eclipticToEquatorialRad($lon_p, $lat_p, $dist_p, $eps);
                    [$ra_m, $dec_m] = Coordinates::eclipticToEquatorialRad($lon_m, $lat_m, $dist_m, $eps);
                    $dRa = Math::angleDiffRad($ra_p, $ra_m) / (2 * $dt);
                    $dDec = ($dec_p - $dec_m) / (2 * $dt);
                    if (!$isRad) {
                        $dRa = Math::radToDeg($dRa);
                        $dDec = Math::radToDeg($dDec);
                    }
                    $xx[3] = $dRa;
                    $xx[4] = $dDec;
                    $xx[5] = $dR;
                } else {
                    $xx[3] = $dLon;
                    $xx[4] = $dLat;
                    $xx[5] = $dR;
                }
            }
            $serr = null;
            return 0;
        }

        // Implement Saturn (SE_SATURN) approximate via helper
        if ($ipl === Constants::SE_SATURN) {
            $xx = PlanetHelper::outputForPlanetHeliocentric(
                $jd_tt,
                $iflag,
                fn (float $t) => Saturn::heliocentricRectEclAU($t)
            );
            $serr = null;
            return 0;
        }
        // Implement Uranus (SE_URANUS) approximate via helper
        if ($ipl === Constants::SE_URANUS) {
            $xx = PlanetHelper::outputForPlanetHeliocentric(
                $jd_tt,
                $iflag,
                fn (float $t) => Uranus::heliocentricRectEclAU($t)
            );
            $serr = null;
            return 0;
        }
        // Implement Neptune (SE_NEPTUNE) approximate via helper
        if ($ipl === Constants::SE_NEPTUNE) {
            $xx = PlanetHelper::outputForPlanetHeliocentric(
                $jd_tt,
                $iflag,
                fn (float $t) => Neptune::heliocentricRectEclAU($t)
            );
            $serr = null;
            return 0;
        }
        // Implement Pluto (SE_PLUTO) approximate via helper
        if ($ipl === Constants::SE_PLUTO) {
            $xx = PlanetHelper::outputForPlanetHeliocentric(
                $jd_tt,
                $iflag,
                fn (float $t) => Pluto::heliocentricRectEclAU($t)
            );
            $serr = null;
            return 0;
        }
        // Implement Mercury (SE_MERCURY) approximate via helper
        if ($ipl === Constants::SE_MERCURY) {
            $xx = PlanetHelper::outputForPlanetHeliocentric(
                $jd_tt,
                $iflag,
                fn (float $t) => Mercury::heliocentricRectEclAU($t)
            );
            $serr = null;
            return 0;
        }
        // Implement Venus (SE_VENUS) approximate via helper
        if ($ipl === Constants::SE_VENUS) {
            $xx = PlanetHelper::outputForPlanetHeliocentric(
                $jd_tt,
                $iflag,
                fn (float $t) => Venus::heliocentricRectEclAU($t)
            );
            $serr = null;
            return 0;
        }

        // Note: The original swe_calc contained additional dead-code branches for Saturn and Mars.
        // We keep behavior by handling Mars and Jupiter via helpers below.

        // Implement Mars (SE_MARS) approximate via helper
        if ($ipl === Constants::SE_MARS) {
            $xx = PlanetHelper::outputForPlanetHeliocentric(
                $jd_tt,
                $iflag,
                fn (float $t) => Mars::heliocentricRectEclAU($t)
            );
            $serr = null;
            return 0;
        }
        // Implement Jupiter (SE_JUPITER) approximate via helper
        if ($ipl === Constants::SE_JUPITER) {
            $xx = PlanetHelper::outputForPlanetHeliocentric(
                $jd_tt,
                $iflag,
                fn (float $t) => Jupiter::heliocentricRectEclAU($t)
            );
            $serr = null;
            return 0;
        }

        // Others: not implemented yet
        $xx = Formatter::eclipticSphericalToOutput(0.0, 0.0, 0.0, $iflag, $jd_tt);
        $serr = ErrorCodes::compose(ErrorCodes::UNSUPPORTED, 'swe_calc not implemented for this ipl yet');
        return Constants::SE_ERR;
    }

    /**
     * Mirror of swe_calc_ut logic: UT -> TT then delegate.
     */
    public static function calcUt(float $jd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $dt_sec = DeltaT::deltaTSecondsFromJd($jd_ut);
        $jd_tt = $jd_ut + $dt_sec / 86400.0;
        return self::calc($jd_tt, $ipl, $iflag, $xx, $serr);
    }
}
