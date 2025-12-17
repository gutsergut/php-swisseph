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
 * Complete port of swemplan.c functions:
 * - swi_osc_el_plan()
 * - read_elements_file()
 * - check_t_terms()
 * - swi_get_fict_name()
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
    public const FICT_GEO = 1;  // Flag for geocentric fictitious body
    private const KGAUSS = 0.01720209895;  // Gaussian gravitational constant (heliocentric)
    private const KGAUSS_GEO = 0.0000298122353216;  // Gaussian constant for Earth-centered bodies

    // J1900 epoch
    private const J1900 = 2415020.0;
    // B1950 epoch
    private const B1950 = 2433282.42345905;
    // File name for orbital elements
    private const SE_FICTFILE = 'seorbel.txt';

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
            $dmot /= sqrt(Constants::SUN_EARTH_MRAT);
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
        $M = Math::mod2PI($mano + ($tjd - $tjd0) * $dmot);
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
        // Port of swi_get_fict_name() - first try seorbel.txt, then built-in names
        $name = null;
        $result = self::readElementsFile($ipl - Constants::SE_FICT_OFFSET, 0.0, $name);
        if ($result !== null && $name !== null) {
            return $name;
        }
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
     * Read orbital elements from seorbel.txt or use built-in values
     *
     * Port of swemplan.c:read_elements_file()
     *
     * @param int $fictIndex Index in fictitious planet table (0 = Cupido, etc.)
     * @param float $tjd Julian day (for T terms in elements)
     * @param string|null &$pname Returns planet name
     * @param string|null &$serr Error message
     * @return array|null [tjd0, tequ, mano, sema, ecce, parg, node, incl, fict_ifl] or null on error
     */
    public static function readElementsFile(int $fictIndex, float $tjd, ?string &$pname = null, ?string &$serr = null): ?array
    {
        // Try to open seorbel.txt
        $ephepath = SwedState::getInstance()->ephepath ?? '';
        $filepath = null;

        // Search in ephepath directories
        $paths = explode(PATH_SEPARATOR, $ephepath);
        foreach ($paths as $path) {
            $candidate = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . self::SE_FICTFILE;
            if (is_file($candidate) && is_readable($candidate)) {
                $filepath = $candidate;
                break;
            }
        }

        // If file not found, use built-in elements
        if ($filepath === null) {
            if ($fictIndex < 0 || $fictIndex >= Constants::SE_NFICT_ELEM) {
                $serr = sprintf("error no elements for fictitious body no %d", $fictIndex + Constants::SE_FICT_OFFSET);
                return null;
            }

            $elem = self::PLAN_OSCU_ELEM[$fictIndex];
            if ($pname !== null) {
                $pname = self::PLAN_FICT_NAM[$fictIndex] ?? "Unknown";
            }

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

        // Read file and parse elements
        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $serr = "Cannot read file " . self::SE_FICTFILE;
            return null;
        }

        $iplan = -1;
        $elemFound = false;

        foreach ($lines as $lineNum => $line) {
            // Skip leading whitespace
            $line = ltrim($line);

            // Skip comments and empty lines
            if ($line === '' || $line[0] === '#' || $line[0] === "\r" || $line[0] === "\n") {
                continue;
            }

            // Remove inline comments
            $hashPos = strpos($line, '#');
            if ($hashPos !== false) {
                $line = substr($line, 0, $hashPos);
            }

            // Split by comma
            $parts = explode(',', $line);
            $ncpos = count($parts);

            if ($ncpos < 9) {
                // Not enough elements - skip line
                continue;
            }

            $iplan++;
            if ($iplan !== $fictIndex) {
                continue;
            }

            $elemFound = true;
            $serri = sprintf("error in file %s, line %d:", self::SE_FICTFILE, $lineNum + 1);

            // epoch of elements (column 0)
            $sp = strtolower(trim($parts[0]));
            if (strncmp($sp, 'j2000', 5) === 0) {
                $tjd0 = Constants::J2000;
            } elseif (strncmp($sp, 'b1950', 5) === 0) {
                $tjd0 = self::B1950;
            } elseif (strncmp($sp, 'j1900', 5) === 0) {
                $tjd0 = self::J1900;
            } elseif ($sp[0] === 'j' || $sp[0] === 'b') {
                $serr = "$serri invalid epoch";
                return null;
            } else {
                $tjd0 = (float)$sp;
            }

            $tt = $tjd - $tjd0;  // time since epoch (for T terms)

            // equinox (column 1)
            $sp = strtolower(trim($parts[1]));
            if (strncmp($sp, 'j2000', 5) === 0) {
                $tequ = Constants::J2000;
            } elseif (strncmp($sp, 'b1950', 5) === 0) {
                $tequ = self::B1950;
            } elseif (strncmp($sp, 'j1900', 5) === 0) {
                $tequ = self::J1900;
            } elseif (strncmp($sp, 'jdate', 5) === 0) {
                $tequ = $tjd;
            } elseif ($sp[0] === 'j' || $sp[0] === 'b') {
                $serr = "$serri invalid equinox";
                return null;
            } else {
                $tequ = (float)$sp;
            }

            // mean anomaly (column 2) - may have T terms
            [$retc, $mano] = self::checkTTerms($tt, trim($parts[2]));
            if ($retc === -1) {
                $serr = "$serri mean anomaly value invalid";
                return null;
            }
            $mano = Math::degnorm($mano);
            // If mean anomaly has T terms, set epoch = tjd
            if ($retc === 1) {
                $tjd0 = $tjd;
            }
            $mano *= Constants::DEGTORAD;

            // semi-axis (column 3)
            [$retc, $sema] = self::checkTTerms($tt, trim($parts[3]));
            if ($sema <= 0 || $retc === -1) {
                $serr = "$serri semi-axis value invalid";
                return null;
            }

            // eccentricity (column 4)
            [$retc, $ecce] = self::checkTTerms($tt, trim($parts[4]));
            if ($ecce >= 1 || $ecce < 0 || $retc === -1) {
                $serr = "$serri eccentricity invalid (no parabolic or hyperbolic orbits allowed)";
                return null;
            }

            // perihelion argument (column 5)
            [$retc, $parg] = self::checkTTerms($tt, trim($parts[5]));
            if ($retc === -1) {
                $serr = "$serri perihelion argument value invalid";
                return null;
            }
            $parg = Math::degnorm($parg) * Constants::DEGTORAD;

            // node (column 6)
            [$retc, $node] = self::checkTTerms($tt, trim($parts[6]));
            if ($retc === -1) {
                $serr = "$serri node value invalid";
                return null;
            }
            $node = Math::degnorm($node) * Constants::DEGTORAD;

            // inclination (column 7)
            [$retc, $incl] = self::checkTTerms($tt, trim($parts[7]));
            if ($retc === -1) {
                $serr = "$serri inclination value invalid";
                return null;
            }
            $incl = Math::degnorm($incl) * Constants::DEGTORAD;

            // planet name (column 8)
            if ($pname !== null) {
                $pname = trim($parts[8]);
            }

            // geocentric flag (column 9)
            $fictIfl = 0;
            if ($ncpos > 9) {
                $flagStr = strtolower(trim($parts[9]));
                if (strpos($flagStr, 'geo') !== false) {
                    $fictIfl |= self::FICT_GEO;
                }
            }

            return [$tjd0, $tequ, $mano, $sema, $ecce, $parg, $node, $incl, $fictIfl];
        }

        // Element not found in file - fall back to built-in
        if (!$elemFound) {
            if ($fictIndex < 0 || $fictIndex >= Constants::SE_NFICT_ELEM) {
                $serr = sprintf("elements for planet %d not found", $fictIndex + Constants::SE_FICT_OFFSET);
                return null;
            }

            $elem = self::PLAN_OSCU_ELEM[$fictIndex];
            if ($pname !== null) {
                $pname = self::PLAN_FICT_NAM[$fictIndex] ?? "Unknown";
            }

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

        return null;
    }

    /**
     * Get orbital elements for a fictitious planet (wrapper for readElementsFile)
     *
     * @param int $fictIndex Index in fictitious planet table (0 = Cupido, etc.)
     * @param float $tjd Julian day
     * @param string|null &$serr Error message
     * @return array|null [tjd0, tequ, mano, sema, ecce, parg, node, incl, fict_ifl] or null on error
     */
    private static function getElements(int $fictIndex, float $tjd, ?string &$serr = null): ?array
    {
        $pname = null;
        return self::readElementsFile($fictIndex, $tjd, $pname, $serr);
    }

    /**
     * Parse T-term expressions like "242.2205555 + 5143.5418158 * T"
     *
     * Port of swemplan.c:check_t_terms()
     *
     * @param float $t Time since epoch in Julian days
     * @param string $sinp Input string with potential T terms
     * @return array [retc, value] where retc = 0 (no T terms), 1 (with T terms), -1 (error)
     */
    public static function checkTTerms(float $t, string $sinp): array
    {
        // tt[0] = T (Julian centuries from epoch)
        // tt[1] = T^1, tt[2] = T^2, etc.
        $tt = [];
        $tt[0] = $t / 36525.0;
        $tt[1] = $tt[0];
        $tt[2] = $tt[1] * $tt[1];
        $tt[3] = $tt[2] * $tt[1];
        $tt[4] = $tt[3] * $tt[1];

        // Check if there are additional terms (+ or - after start)
        $retc = 0;
        $sinp = trim($sinp);

        // Check if there's a + or - sign (indicating T terms)
        if (preg_match('/[+\-]/', substr($sinp, 1)) !== 0) {
            $retc = 1;  // with additional terms
        }

        $doutp = 0.0;
        $fac = 1.0;
        $isgn = 1;
        $z = 0;
        $pos = 0;
        $len = strlen($sinp);

        while ($pos < $len) {
            // Skip whitespace
            while ($pos < $len && ($sinp[$pos] === ' ' || $sinp[$pos] === "\t")) {
                $pos++;
            }

            if ($pos >= $len) {
                break;
            }

            $ch = $sinp[$pos];

            if ($ch === '+' || $ch === '-') {
                // End of previous term
                if ($z > 0) {
                    $doutp += $fac;
                }
                $isgn = ($ch === '-') ? -1 : 1;
                $fac = 1.0 * $isgn;
                $pos++;
            } else {
                // Skip * and whitespace
                while ($pos < $len && ($sinp[$pos] === '*' || $sinp[$pos] === ' ' || $sinp[$pos] === "\t")) {
                    $pos++;
                }

                if ($pos >= $len) {
                    break;
                }

                $ch = $sinp[$pos];

                if ($ch === 't' || $ch === 'T') {
                    // A T term
                    $pos++;
                    if ($pos < $len && ($sinp[$pos] === '+' || $sinp[$pos] === '-')) {
                        // Just T (T^1)
                        $fac *= $tt[0];
                    } else {
                        // T with power (T2, T3, etc.)
                        $power = 0;
                        while ($pos < $len && ctype_digit($sinp[$pos])) {
                            $power = $power * 10 + (int)$sinp[$pos];
                            $pos++;
                        }
                        if ($power <= 4 && $power >= 0) {
                            $fac *= $tt[$power];
                        } else {
                            $fac *= $tt[0];  // default to T^1
                        }
                    }
                } else {
                    // A number
                    $numStr = '';
                    while ($pos < $len && (ctype_digit($sinp[$pos]) || $sinp[$pos] === '.' || $sinp[$pos] === '-')) {
                        // Only include leading minus if we're starting
                        if ($sinp[$pos] === '-' && $numStr !== '') {
                            break;
                        }
                        $numStr .= $sinp[$pos];
                        $pos++;
                    }
                    if ($numStr !== '' && ($numStr !== '0' || $numStr === '0')) {
                        $num = (float)$numStr;
                        if ($num != 0.0 || $numStr[0] === '0') {
                            $fac *= $num;
                        }
                    }
                }
            }
            $z++;
        }

        // Final term
        if ($z > 0) {
            $doutp += $fac;
        }

        return [$retc, $doutp];
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
