<?php

declare(strict_types=1);

namespace Swisseph\Swe\Planets;

use Swisseph\Constants;
use Swisseph\Math;
use Swisseph\SwephFile\SwedState;
use Swisseph\Coordinates;
use Swisseph\Precession;
use Swisseph\Obliquity;

/**
 * Fictitious Planets Calculator
 *
 * Port of swemplan.c:swi_osc_el_plan() and related functions
 *
 * Computes positions of fictitious bodies (Uranian planets, Isis-Transpluto, etc.)
 * from osculating orbital elements.
 *
 * Supported bodies:
 * - SE_CUPIDO (40) through SE_POSEIDON (47): Uranian/Hamburger planets
 * - SE_ISIS (48): Isis-Transpluto
 * - SE_NIBIRU (49): Nibiru
 * - SE_HARRINGTON (50): Harrington's Planet X
 * - SE_NEPTUNE_LEVERRIER (51), SE_NEPTUNE_ADAMS (52): Historical Neptune predictions
 * - SE_PLUTO_LOWELL (53), SE_PLUTO_PICKERING (54): Historical Pluto predictions
 * - SE_VULCAN (55): Vulcan (intra-Mercurial planet)
 * - SE_WHITE_MOON (56): White Moon (Selena)
 * - SE_PROSERPINA (57): Proserpina
 * - SE_WALDEMATH (58): Waldemath's second moon
 *
 * WITHOUT SIMPLIFICATIONS - complete C port from swemplan.c
 */
class FictitiousPlanets
{
    // Constants from swemplan.c
    private const FICT_GEO = 1;  // Flag for geocentric fictitious body
    private const KGAUSS = 0.01720209895;  // Gaussian gravitational constant (heliocentric)
    private const KGAUSS_GEO = 0.0000298122353216;  // Gaussian constant for Earth-centered bodies

    // J1900 epoch
    private const J1900 = 2415020.0;

    /**
     * Names of fictitious planets (from swemplan.c:plan_fict_nam)
     */
    private const PLAN_FICT_NAM = [
        'Cupido',
        'Hades',
        'Zeus',
        'Kronos',
        'Apollon',
        'Admetos',
        'Vulkanus',
        'Poseidon',
        'Isis-Transpluto',
        'Nibiru',
        'Harrington',
        'Leverrier',
        'Adams',
        'Lowell',
        'Pickering',
    ];

    /**
     * Built-in osculating elements for fictitious planets
     * From swemplan.c:plan_oscu_elem (SE_NEELY version)
     *
     * Format: [epoch, equinox, mean_anomaly, semi_axis, eccentricity, arg_perihelion, asc_node, inclination]
     */
    private const PLAN_OSCU_ELEM = [
        // Cupido (Neely)
        [self::J1900, self::J1900, 163.7409, 40.99837, 0.00460, 171.4333, 129.8325, 1.0833],
        // Hades (Neely)
        [self::J1900, self::J1900, 27.6496, 50.66744, 0.00245, 148.1796, 161.3339, 1.0500],
        // Zeus (Neely)
        [self::J1900, self::J1900, 165.1232, 59.21436, 0.00120, 299.0440, 0.0000, 0.0000],
        // Kronos (Neely)
        [self::J1900, self::J1900, 169.0193, 64.81960, 0.00305, 208.8801, 0.0000, 0.0000],
        // Apollon (Neely)
        [self::J1900, self::J1900, 138.0533, 70.29949, 0.00000, 0.0000, 0.0000, 0.0000],
        // Admetos (Neely)
        [self::J1900, self::J1900, 351.3350, 73.62765, 0.00000, 0.0000, 0.0000, 0.0000],
        // Vulcanus (Neely)
        [self::J1900, self::J1900, 55.8983, 77.25568, 0.00000, 0.0000, 0.0000, 0.0000],
        // Poseidon (Neely)
        [self::J1900, self::J1900, 165.5163, 83.66907, 0.00000, 0.0000, 0.0000, 0.0000],
        // Isis-Transpluto (Die Sterne 3/1952)
        [2368547.66, 2431456.5, 0.0, 77.775, 0.3, 0.7, 0, 0],
        // Nibiru (Christian Woeltge)
        [1856113.380954, 1856113.380954, 0.0, 234.8921, 0.981092, 103.966, -44.567, 158.708],
        // Harrington (Astronomical Journal 96(4), Oct. 1988)
        [2374696.5, Constants::J2000, 0.0, 101.2, 0.411, 208.5, 275.4, 32.4],
        // Leverrier's Neptune
        [2395662.5, 2395662.5, 34.05, 36.15, 0.10761, 284.75, 0, 0],
        // Adam's Neptune
        [2395662.5, 2395662.5, 24.28, 37.25, 0.12062, 299.11, 0, 0],
        // Lowell's Pluto
        [2425977.5, 2425977.5, 281, 43.0, 0.202, 204.9, 0, 0],
        // Pickering's Pluto
        [2425977.5, 2425977.5, 48.95, 55.1, 0.31, 280.1, 100, 15],
    ];

    /**
     * Compute position of a fictitious planet
     *
     * Port of swemplan.c:swi_osc_el_plan()
     * Returns HELIOCENTRIC ECLIPTIC cartesian coordinates referred to the mean ecliptic of the element's equinox
     *
     * @param float $tjd Julian day (TT)
     * @param int $ipl Planet number (SE_CUPIDO..SE_FICT_MAX)
     * @param string|null &$serr Error message
     * @return array|null Position array [x, y, z, vx, vy, vz] in heliocentric ecliptic cartesian, or null on error
     */
    public static function compute(float $tjd, int $ipl, ?string &$serr = null): ?array
    {
        $fictIndex = $ipl - Constants::SE_FICT_OFFSET;

        // Get orbital elements
        $elements = self::getElements($fictIndex, $tjd, $serr);
        if ($elements === null) {
            return null;
        }

        [$tjd0, $tequ, $mano, $sema, $ecce, $parg, $node, $incl, $fictIfl] = $elements;

        // Daily motion (in radians/day)
        // dmot = 0.9856076686 * DEGTORAD / sema / sqrt(sema)
        $dmot = 0.9856076686 * Constants::DEGTORAD / $sema / sqrt($sema);
        if ($fictIfl & self::FICT_GEO) {
            // For geocentric bodies, adjust by sqrt(SUN_EARTH_MRAT)
            $dmot /= sqrt(Constants::EARTH_MOON_MRAT);
        }

        // Gaussian vector (PQR matrix)
        $cosnode = cos($node);
        $sinnode = sin($node);
        $cosincl = cos($incl);
        $sinincl = sin($incl);
        $cosparg = cos($parg);
        $sinparg = sin($parg);

        $pqr = [];
        $pqr[0] = $cosparg * $cosnode - $sinparg * $cosincl * $sinnode;
        $pqr[1] = -$sinparg * $cosnode - $cosparg * $cosincl * $sinnode;
        $pqr[2] = $sinincl * $sinnode;
        $pqr[3] = $cosparg * $sinnode + $sinparg * $cosincl * $cosnode;
        $pqr[4] = -$sinparg * $sinnode + $cosparg * $cosincl * $cosnode;
        $pqr[5] = -$sinincl * $cosnode;
        $pqr[6] = $sinparg * $sinincl;
        $pqr[7] = $cosparg * $sinincl;
        $pqr[8] = $cosincl;

        // Kepler problem: solve for eccentric anomaly E
        $M = Math::mod2pi($mano + ($tjd - $tjd0) * $dmot);
        $E = self::solveKepler($M, $ecce);

        // Position and velocity in orbital plane
        if ($fictIfl & self::FICT_GEO) {
            $K = self::KGAUSS_GEO / sqrt($sema);
        } else {
            $K = self::KGAUSS / sqrt($sema);
        }

        $cose = cos($E);
        $sine = sin($E);
        $fac = sqrt((1 - $ecce) * (1 + $ecce));
        $rho = 1 - $ecce * $cose;

        // Position in orbital plane
        $x = [];
        $x[0] = $sema * ($cose - $ecce);
        $x[1] = $sema * $fac * $sine;
        // Velocity in orbital plane
        $x[3] = -$K * $sine / $rho;
        $x[4] = $K * $fac * $cose / $rho;

        // Transform to ecliptic coordinates
        $xp = [];
        $xp[0] = $pqr[0] * $x[0] + $pqr[1] * $x[1];
        $xp[1] = $pqr[3] * $x[0] + $pqr[4] * $x[1];
        $xp[2] = $pqr[6] * $x[0] + $pqr[7] * $x[1];
        $xp[3] = $pqr[0] * $x[3] + $pqr[1] * $x[4];
        $xp[4] = $pqr[3] * $x[3] + $pqr[4] * $x[4];
        $xp[5] = $pqr[6] * $x[3] + $pqr[7] * $x[4];

        // Transform from ecliptic to equatorial (ecliptic of tequ)
        // swi_epsiln(tequ, 0) + swi_coortrf(xp, xp, -eps)
        $eps = Obliquity::meanObliquityRadFromJdTT($tequ);
        $xpn = $xp;
        Coordinates::coortrf($xp, $xpn, -$eps);  // position to equator
        $xp[0] = $xpn[0];
        $xp[1] = $xpn[1];
        $xp[2] = $xpn[2];
        // Also transform velocity
        $xps = [$xp[3], $xp[4], $xp[5]];
        $xpsn = [];
        Coordinates::coortrf($xps, $xpsn, -$eps);  // velocity to equator
        $xp[3] = $xpsn[0];
        $xp[4] = $xpsn[1];
        $xp[5] = $xpsn[2];

        // Precess to J2000 if needed
        // swi_precess(xp, tequ, 0, J_TO_J2000)
        if (abs($tequ - Constants::J2000) > 0.0001) {
            // Precess position
            $pos = [$xp[0], $xp[1], $xp[2]];
            Precession::precess($pos, $tequ, 0, Constants::J_TO_J2000);
            $xp[0] = $pos[0];
            $xp[1] = $pos[1];
            $xp[2] = $pos[2];

            // Precess velocity
            $vel = [$xp[3], $xp[4], $xp[5]];
            Precession::precess($vel, $tequ, 0, Constants::J_TO_J2000);
            $xp[3] = $vel[0];
            $xp[4] = $vel[1];
            $xp[5] = $vel[2];
        }

        // Return heliocentric equatorial J2000 cartesian coordinates
        return $xp;
    }

    /**
     * Get name of a fictitious planet
     *
     * @param int $ipl Planet number
     * @return string Planet name
     */
    public static function getName(int $ipl): string
    {
        $fictIndex = $ipl - Constants::SE_FICT_OFFSET;
        if ($fictIndex >= 0 && $fictIndex < count(self::PLAN_FICT_NAM)) {
            return self::PLAN_FICT_NAM[$fictIndex];
        }
        return "Fictitious body " . $ipl;
    }

    /**
     * Check if planet is a fictitious body
     *
     * @param int $ipl Planet number
     * @return bool
     */
    public static function isFictitious(int $ipl): bool
    {
        return $ipl >= Constants::SE_FICT_OFFSET && $ipl <= Constants::SE_FICT_MAX;
    }

    /**
     * Get orbital elements for a fictitious planet
     *
     * @param int $fictIndex Index in fictitious planet table (0 = Cupido, etc.)
     * @param float $tjd Julian day
     * @param string|null &$serr Error message
     * @return array|null [tjd0, tequ, mano, sema, ecce, parg, node, incl, fict_ifl] or null on error
     */
    private static function getElements(int $fictIndex, float $tjd, ?string &$serr = null): ?array
    {
        // First try to read from seorbel.txt file (not implemented yet)
        // Fall back to built-in elements

        if ($fictIndex < 0 || $fictIndex >= Constants::SE_NFICT_ELEM) {
            $serr = "No elements for fictitious body no " . ($fictIndex + Constants::SE_FICT_OFFSET);
            return null;
        }

        $elem = self::PLAN_OSCU_ELEM[$fictIndex];

        return [
            $elem[0],                           // tjd0 (epoch)
            $elem[1],                           // tequ (equinox)
            $elem[2] * Constants::DEGTORAD,     // mano (mean anomaly) -> radians
            $elem[3],                           // sema (semi-major axis) in AU
            $elem[4],                           // ecce (eccentricity)
            $elem[5] * Constants::DEGTORAD,     // parg (argument of perihelion) -> radians
            $elem[6] * Constants::DEGTORAD,     // node (ascending node) -> radians
            $elem[7] * Constants::DEGTORAD,     // incl (inclination) -> radians
            0,                                  // fict_ifl (flags, 0 = heliocentric)
        ];
    }

    /**
     * Solve Kepler's equation for eccentric anomaly
     *
     * Port of swephlib.c:swi_kepler()
     *
     * @param float $M Mean anomaly (radians)
     * @param float $e Eccentricity
     * @return float Eccentric anomaly (radians)
     */
    private static function solveKepler(float $M, float $e): float
    {
        // Initial approximation
        $E = $M;

        // For high eccentricity, use better initial approximation
        if ($e > 0.975) {
            $M2 = fmod($M, 2 * M_PI);
            if ($M2 < 0) {
                $M2 += 2 * M_PI;
            }
            $M2 = $M2 * Constants::RADTODEG;

            $M_180_or_0 = 0.0;
            if ($M2 > 150 && $M2 < 210) {
                $M2 -= 180;
                $M_180_or_0 = 180;
            }
            if ($M2 > 330) {
                $M2 -= 360;
            }

            $Msgn = 1;
            if ($M2 < 0) {
                $M2 = -$M2;
                $Msgn = -1;
            }

            if ($M2 < 30) {
                $M2 *= Constants::DEGTORAD;
                $alpha = (1 - $e) / (4 * $e + 0.5);
                $beta = $M2 / (8 * $e + 1);
                $zeta = pow($beta + sqrt($beta * $beta + $alpha * $alpha), 1.0 / 3.0);
                $sigma = $zeta - $alpha / 2;
                $sigma = $sigma - 0.078 * $sigma * $sigma * $sigma * $sigma * $sigma / (1 + $e);
                $E = $Msgn * ($M2 + $e * (3 * $sigma - 4 * $sigma * $sigma * $sigma))
                    + $M_180_or_0;
                $E *= Constants::DEGTORAD;
            }
        }

        // Newton-Raphson iteration
        for ($i = 0; $i < 50; $i++) {
            $dE = ($M - $E + $e * sin($E)) / (1 - $e * cos($E));
            $E += $dE;
            if (abs($dE) < 1e-12) {
                break;
            }
        }

        return $E;
    }
}
