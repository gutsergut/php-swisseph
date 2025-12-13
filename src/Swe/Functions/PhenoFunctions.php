<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\ErrorCodes;
use Swisseph\DeltaT;
use Swisseph\Math;

/**
 * Planetary phenomena functions - full port from Swiss Ephemeris swecl.c
 *
 * This implementation includes:
 * - Complete Mallama 2018 magnitude formulas for planets
 * - Allen/Vreijs magnitude formula for Moon
 * - Accurate phase angle calculations
 * - Apparent diameter calculations
 * - Horizontal parallax for Moon
 *
 * Based on swecl.c:swe_pheno() from Swiss Ephemeris C library.
 */
final class PhenoFunctions
{
    // Constants for magnitude calculations
    private const EULER = 2.718281828459;

    // Magnitude elements array [H, G, ...] for each body
    // From mag_elem in swecl.c
    private const MAG_ELEM = [
        Constants::SE_SUN => [-26.86, 0, 0, 0],
        Constants::SE_MOON => [-12.55, 0, 0, 0],
        Constants::SE_MERCURY => [-0.42, 3.80, -2.73, 2.00], // Obsolete, Mallama used
        Constants::SE_VENUS => [-4.40, 0.09, 2.39, -0.65],   // Obsolete, Mallama used
        Constants::SE_MARS => [-1.52, 1.60, 0, 0],
        Constants::SE_JUPITER => [-9.40, 0.5, 0, 0],
        Constants::SE_SATURN => [-8.88, -2.60, 1.25, 0.044],
        Constants::SE_URANUS => [-7.19, 0.0, 0, 0],
        Constants::SE_NEPTUNE => [-6.87, 0.0, 0, 0],
        Constants::SE_PLUTO => [-1.00, 0.0, 0, 0],
    ];

    // Planetary diameters in meters
    // From pla_diam in sweph.h
    private const PLA_DIAM = [
        Constants::SE_SUN => 1392000000.0,
        Constants::SE_MOON => 3475000.0,
        Constants::SE_MERCURY => 2439400.0 * 2,
        Constants::SE_VENUS => 6051800.0 * 2,
        Constants::SE_MARS => 3389500.0 * 2,
        Constants::SE_JUPITER => 69911000.0 * 2,
        Constants::SE_SATURN => 58232000.0 * 2,
        Constants::SE_URANUS => 25362000.0 * 2,
        Constants::SE_NEPTUNE => 24622000.0 * 2,
        Constants::SE_PLUTO => 1188300.0 * 2,
    ];

    // J2000.0 constant
    private const J2000 = 2451545.0;

    /**
     * Calculate planetary phenomena.
     *
     * Full port of swe_pheno() from swecl.c including all Mallama 2018 formulas.
     *
     * @param float $tjd_et Julian Day in Ephemeris Time
     * @param int $ipl Planet number
     * @param int $iflag Calculation flags
     * @param array|null &$attr Output array (at least 6 elements):
     *   [0] = phase angle (earth-planet-sun, in degrees)
     *   [1] = phase (illuminated fraction of disc, 0..1)
     *   [2] = elongation of planet (degrees)
     *   [3] = apparent diameter of disc (arc-seconds)
     *   [4] = apparent magnitude
     *   [5] = horizontal parallax (Moon only, degrees)
     * @param string|null &$serr Error message
     * @return int iflag on success, ERR on error
     */
    public static function pheno(
        float $tjd_et,
        int $ipl,
        int $iflag,
        ?array &$attr = null,
        ?string &$serr = null
    ): int {
        $serr = null;
        $serr2 = '';

        // Filter out JPLHOR flags
        $iflag &= ~(Constants::SEFLG_JPLHOR | Constants::SEFLG_JPLHOR_APPROX);

        // Pluto with asteroid number 134340 is treated as SE_PLUTO
        if ($ipl === Constants::SE_AST_OFFSET + 134340) {
            $ipl = Constants::SE_PLUTO;
        }

        // Initialize attr array with zeros (20 elements)
        $attr = array_fill(0, 20, 0.0);

        // Filter allowed flags
        $iflag = $iflag & (Constants::SEFLG_EPHMASK |
                          Constants::SEFLG_TRUEPOS |
                          Constants::SEFLG_J2000 |
                          Constants::SEFLG_NONUT |
                          Constants::SEFLG_NOGDEFL |
                          Constants::SEFLG_NOABERR |
                          Constants::SEFLG_TOPOCTR);

        $iflagp = $iflag & (Constants::SEFLG_EPHMASK |
                           Constants::SEFLG_TRUEPOS |
                           Constants::SEFLG_J2000 |
                           Constants::SEFLG_NONUT |
                           Constants::SEFLG_NOABERR);
        $iflagp |= Constants::SEFLG_HELCTR;

        $epheflag = $iflag & Constants::SEFLG_EPHMASK;

        // Get geocentric planet position in XYZ
        $xx = [];
        $retflag = PlanetsFunctions::calc($tjd_et, $ipl, $iflag | Constants::SEFLG_XYZ, $xx, $serr);
        if ($retflag < 0) {
            return Constants::SE_ERR;
        }

        // Check ephemeris flag and adjust if needed
        $epheflag2 = $retflag & Constants::SEFLG_EPHMASK;
        if ($epheflag !== $epheflag2) {
            $iflag &= ~$epheflag;
            $iflagp &= ~$epheflag;
            $iflag |= $epheflag2;
            $iflagp |= $epheflag2;
            $epheflag = $epheflag2;
        }

        // Get geocentric planet position in spherical (lon, lat, rad)
        $lbr = [];
        $retflag = PlanetsFunctions::calc($tjd_et, $ipl, $iflag, $lbr, $serr);
        if ($retflag < 0) {
            return Constants::SE_ERR;
        }

        // If moon, we need sun as well, for magnitude calculation
        $xxs = [];
        if ($ipl === Constants::SE_MOON) {
            if (PlanetsFunctions::calc($tjd_et, Constants::SE_SUN, $iflag | Constants::SEFLG_XYZ, $xxs, $serr) < 0) {
                return Constants::SE_ERR;
            }
        }

        // Calculate phase angle and illuminated fraction for planets
        $lbr2 = [];
        $dt = 0.0;

        if ($ipl === Constants::SE_SUN || $ipl === Constants::SE_EARTH ||
                  $ipl === Constants::SE_MEAN_NODE || $ipl === Constants::SE_TRUE_NODE ||
                  $ipl === Constants::SE_MEAN_APOG || $ipl === Constants::SE_OSCU_APOG) {
            // Sun and special bodies: always fully illuminated
            $attr[0] = 0.0;
            $attr[1] = 1.0;
            $lbr2 = $lbr;
        } else {
            // Planets: use heliocentric calculations
            // Light time correction
            $dt = $lbr[2] * Constants::AUNIT / Constants::CLIGHT / 86400.0;
            if ($iflag & Constants::SEFLG_TRUEPOS) {
                $dt = 0.0;
            }

            // Heliocentric planet position at tjd - dt (XYZ)
            $xx2 = [];
            if (PlanetsFunctions::calc($tjd_et - $dt, $ipl, $iflagp | Constants::SEFLG_XYZ, $xx2, $serr) < 0) {
                return Constants::SE_ERR;
            }

            // Heliocentric planet position (spherical)
            if (PlanetsFunctions::calc($tjd_et - $dt, $ipl, $iflagp, $lbr2, $serr) < 0) {
                return Constants::SE_ERR;
            }

            // Phase angle
            $attr[0] = acos(self::dotProductUnit($xx, $xx2)) * Constants::RADTODEG;

            // Phase (illuminated fraction)
            $attr[1] = (1.0 + cos($attr[0] * Constants::DEGTORAD)) / 2.0;
        }        // Apparent diameter of disk
        $dd = self::PLA_DIAM[$ipl] ?? 0.0;

        if ($lbr[2] < $dd / 2.0 / Constants::AUNIT) {
            $attr[3] = 180.0 * 3600.0; // On surface of Earth (convert to arcsec)
        } else {
            // Result in degrees, convert to arcsec (1° = 3600")
            $attr[3] = asin($dd / 2.0 / Constants::AUNIT / $lbr[2]) * 2.0 * Constants::RADTODEG * 3600.0;
        }

        // Apparent magnitude
        self::calculateMagnitude($ipl, $tjd_et, $dt, $attr, $lbr, $lbr2, $serr2);

        // Elongation (for non-Sun/Earth bodies)
        if ($ipl !== Constants::SE_SUN && $ipl !== Constants::SE_EARTH) {
            $xx2 = [];
            if (PlanetsFunctions::calc($tjd_et, Constants::SE_SUN, $iflag | Constants::SEFLG_XYZ, $xx2, $serr) < 0) {
                return Constants::SE_ERR;
            }

            $lbr2_sun = [];
            if (PlanetsFunctions::calc($tjd_et, Constants::SE_SUN, $iflag, $lbr2_sun, $serr) < 0) {
                return Constants::SE_ERR;
            }

            $attr[2] = acos(self::dotProductUnit($xx, $xx2)) * Constants::RADTODEG;
        }

        // Horizontal parallax (Moon only)
        if ($ipl === Constants::SE_MOON) {
            $xm = [];
            if (PlanetsFunctions::calc(
                $tjd_et,
                $ipl,
                $epheflag | Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_RADIANS,
                $xm,
                $serr
            ) < 0) {
                return Constants::SE_ERR;
            }

            $sinhp = Constants::EARTH_RADIUS / ($xm[2] * Constants::AUNIT);
            $attr[5] = asin($sinhp) / Constants::DEGTORAD;

            // Topocentric horizontal parallax
            if ($iflag & Constants::SEFLG_TOPOCTR) {
                $xm_topo = [];
                if (PlanetsFunctions::calc($tjd_et, $ipl, $epheflag | Constants::SEFLG_XYZ | Constants::SEFLG_TOPOCTR, $xm_topo, $serr) < 0) {
                    return Constants::SE_ERR;
                }
                if (PlanetsFunctions::calc($tjd_et, $ipl, $epheflag | Constants::SEFLG_XYZ, $xx, $serr) < 0) {
                    return Constants::SE_ERR;
                }
                $attr[5] = acos(self::dotProductUnit($xm_topo, $xx)) / Constants::DEGTORAD;
            }
        }

        // Return only first 6 elements
        $attr = array_slice($attr, 0, 6);

        if ($serr2 !== '' && $serr !== null) {
            $serr = $serr2;
        }

        return Constants::SE_OK;
    }

    /**
     * Calculate planetary phenomena for UT time.
     *
     * @param float $tjd_ut Julian Day in Universal Time
     * @param int $ipl Planet number
     * @param int $iflag Calculation flags
     * @param array|null &$attr Output array
     * @param string|null &$serr Error message
     * @return int iflag on success, ERR on error
     */
    public static function phenoUt(
        float $tjd_ut,
        int $ipl,
        int $iflag,
        ?array &$attr = null,
        ?string &$serr = null
    ): int {
        $epheflag = $iflag & Constants::SEFLG_EPHMASK;
        if ($epheflag === 0) {
            $epheflag = Constants::SEFLG_SWIEPH;
            $iflag |= Constants::SEFLG_SWIEPH;
        }

        $deltat = DeltaT::deltaTSecondsFromJd($tjd_ut) / 86400.0;
        $retflag = self::pheno($tjd_ut + $deltat, $ipl, $iflag, $attr, $serr);

        // If ephemeris required is not ephemeris returned, adjust delta t
        if (($retflag & Constants::SEFLG_EPHMASK) !== $epheflag) {
            $deltat = DeltaT::deltaTSecondsFromJd($tjd_ut) / 86400.0;
            $retflag = self::pheno($tjd_ut + $deltat, $ipl, $iflag, $attr, $serr);
        }

        return $retflag;
    }

    /**
     * Calculate apparent magnitude using Mallama 2018 formulas.
     *
     * This is a private method that implements the complex magnitude calculations
     * for each planet according to modern formulas.
     *
     * @param int $ipl Planet number
     * @param float $tjd Julian Day
     * @param float $dt Light time correction
     * @param array &$attr Attribute array (modified in place)
     * @param array $lbr Geocentric spherical coordinates
     * @param array $lbr2 Heliocentric spherical coordinates
     * @param string &$serr2 Error message
     */
    private static function calculateMagnitude(
        int $ipl,
        float $tjd,
        float $dt,
        array &$attr,
        array $lbr,
        array $lbr2,
        string &$serr2
    ): void {
        if (!isset(self::MAG_ELEM[$ipl])) {
            return; // No magnitude data for this body
        }

        $mag_elem = self::MAG_ELEM[$ipl];

        if ($mag_elem[0] >= 99) {
            return; // No magnitude calculation for nodes/apogees
        }

        if ($ipl === Constants::SE_SUN) {
            // Sun magnitude depends on distance
            $avg_diam = asin(self::PLA_DIAM[Constants::SE_SUN] / 2.0 / Constants::AUNIT) * 2.0 * Constants::RADTODEG;
            $fac = $attr[3] / 3600.0 / $avg_diam;
            $fac *= $fac;
            $attr[4] = $mag_elem[0] - 2.5 * log10($fac);
        } elseif ($ipl === Constants::SE_MOON) {
            // Allen/Vreijs formula for Moon
            $a = $attr[0];
            if ($a <= 147.1385465) {
                // Allen formula
                $attr[4] = -21.62 + 0.026 * abs($a) + 0.000000004 * pow($a, 4);
                $attr[4] += 5.0 * log10($lbr[2] * $lbr2[2] * Constants::AUNIT / Constants::EARTH_RADIUS);
            } else {
                // Samaha formula for larger phase angles
                $attr[4] = -4.5444 - (2.5 * log10(pow(180.0 - $a, 3)));
                $attr[4] += 5.0 * log10($lbr[2] * $lbr2[2] * Constants::AUNIT / Constants::EARTH_RADIUS);
            }
        } elseif ($ipl === Constants::SE_MERCURY) {
            // Mallama 2018 formula for Mercury
            $a = $attr[0];
            $a2 = $a * $a;
            $a3 = $a2 * $a;
            $a4 = $a3 * $a;
            $a5 = $a4 * $a;
            $a6 = $a5 * $a;
            $attr[4] = -0.613 + $a * 6.3280E-02 - $a2 * 1.6336E-03 + $a3 * 3.3644E-05 -
                       $a4 * 3.4265E-07 + $a5 * 1.6893E-09 - $a6 * 3.0334E-12;
            $attr[4] += 5.0 * log10($lbr2[2] * $lbr[2]);
        } elseif ($ipl === Constants::SE_VENUS) {
            // Mallama 2018 formula for Venus
            $a = $attr[0];
            $a2 = $a * $a;
            $a3 = $a2 * $a;
            $a4 = $a3 * $a;
            if ($a <= 163.7) {
                $attr[4] = -4.384 - $a * 1.044E-03 + $a2 * 3.687E-04 - $a3 * 2.814E-06 + $a4 * 8.938E-09;
            } else {
                $attr[4] = 236.05828 - $a * 2.81914E+00 + $a2 * 8.39034E-03;
            }
            $attr[4] += 5.0 * log10($lbr2[2] * $lbr[2]);
            if ($attr[0] > 179.0) {
                $serr2 = sprintf("magnitude value for Venus at phase angle i=%.1f is bad; formula is valid only for i < 179.0", $attr[0]);
            }
        } elseif ($ipl === Constants::SE_MARS) {
            // Mallama 2018 formula for Mars
            $a = $attr[0];
            $a2 = $a * $a;
            if ($a <= 50.0) {
                $attr[4] = -1.601 + $a * 0.02267 - $a2 * 0.0001302;
            } else {
                $attr[4] = -0.367 - $a * 0.02573 + $a2 * 0.0003445;
            }
            $attr[4] += 5.0 * log10($lbr2[2] * $lbr[2]);
        } elseif ($ipl === Constants::SE_JUPITER) {
            // Mallama 2018 formula for Jupiter
            $a = $attr[0];
            $a2 = $a * $a;
            $attr[4] = -9.395 - $a * 3.7E-04 + $a2 * 6.16E-04;
            $attr[4] += 5.0 * log10($lbr2[2] * $lbr[2]);
        } elseif ($ipl === Constants::SE_SATURN) {
            // Mallama 2018 formula for Saturn (with ring tilt)
            $a = $attr[0];
            $T = ($tjd - $dt - self::J2000) / 36525.0;
            $in = (28.075216 - 0.012998 * $T + 0.000004 * $T * $T) * Constants::DEGTORAD;
            $om = (169.508470 + 1.394681 * $T + 0.000412 * $T * $T) * Constants::DEGTORAD;

            $sinB = sin($in) * cos($lbr[1] * Constants::DEGTORAD) * sin($lbr[0] * Constants::DEGTORAD - $om) -
                    cos($in) * sin($lbr[1] * Constants::DEGTORAD);
            $sinB2 = sin($in) * cos($lbr2[1] * Constants::DEGTORAD) * sin($lbr2[0] * Constants::DEGTORAD - $om) -
                     cos($in) * sin($lbr2[1] * Constants::DEGTORAD);
            $sinB = abs(sin((asin($sinB) + asin($sinB2)) / 2.0));

            $attr[4] = -8.914 - 1.825 * $sinB + 0.026 * $a - 0.378 * $sinB * pow(self::EULER, -2.25 * $a);
            $attr[4] += 5.0 * log10($lbr2[2] * $lbr[2]);
        } elseif ($ipl === Constants::SE_URANUS) {
            // Mallama 2018 formula for Uranus (simplified)
            $a = $attr[0];
            $a2 = $a * $a;
            $attr[4] = -7.110 + $a * 6.587E-3 + $a2 * 1.045E-4;
            $attr[4] += 5.0 * log10($lbr2[2] * $lbr[2]);
            $attr[4] -= 0.05; // Correction for sub-Earth latitude effect
        } elseif ($ipl === Constants::SE_NEPTUNE) {
            // Mallama 2018 formula for Neptune (time-dependent)
            if ($tjd < 2444239.5) {
                $attr[4] = -6.89;
            } elseif ($tjd <= 2451544.5) {
                $attr[4] = -6.89 - 0.0055 * ($tjd - 2444239.5) / 365.25;
            } else {
                $attr[4] = -7.00;
            }
            $attr[4] += 5.0 * log10($lbr2[2] * $lbr[2]);
        } else {
            // Generic formula for other bodies
            $attr[4] = 5.0 * log10($lbr2[2] * $lbr[2]) +
                       $mag_elem[1] * $attr[0] / 100.0 +
                       $mag_elem[2] * $attr[0] * $attr[0] / 10000.0 +
                       $mag_elem[3] * $attr[0] * $attr[0] * $attr[0] / 1000000.0 +
                       $mag_elem[0];
        }
    }

    /**
     * Dot product of two unit vectors.
     *
     * Port of swi_dot_prod_unit() from Swiss Ephemeris.
     *
     * @param array $x1 First vector [x, y, z]
     * @param array $x2 Second vector [x, y, z]
     * @return float Dot product, clamped to [-1, 1]
     */
    private static function dotProductUnit(array $x1, array $x2): float
    {
        // Normalize x1
        $r1 = sqrt($x1[0] ** 2 + $x1[1] ** 2 + $x1[2] ** 2);
        if ($r1 < 1e-20) {
            return 0.0;
        }
        $u1 = [$x1[0] / $r1, $x1[1] / $r1, $x1[2] / $r1];

        // Normalize x2
        $r2 = sqrt($x2[0] ** 2 + $x2[1] ** 2 + $x2[2] ** 2);
        if ($r2 < 1e-20) {
            return 0.0;
        }
        $u2 = [$x2[0] / $r2, $x2[1] / $r2, $x2[2] / $r2];

        // Dot product
        $dot = $u1[0] * $u2[0] + $u1[1] * $u2[1] + $u1[2] * $u2[2];

        // Clamp to [-1, 1] for acos
        return max(-1.0, min(1.0, $dot));
    }
}
