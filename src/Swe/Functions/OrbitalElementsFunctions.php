<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\ErrorCodes;
use Swisseph\Math;

/**
 * Orbital elements functions
 *
 * Port of swe_get_orbital_elements() from swecl.c
 */
final class OrbitalElementsFunctions
{
    /**
     * Get orbital elements for a planet
     *
     * Port of swe_get_orbital_elements() from swecl.c lines 5802-6003
     *
     * @param float       $tjd_et Julian day in ET/TT
     * @param int         $ipl    Planet number (SE_SUN to SE_PLUTO, SE_EARTH, SE_MOON)
     * @param int         $iflag  Calculation flags (SEFLG_*)
     * @param array       &$dret  Output array with 17 elements:
     *                            [0] = semimajor axis (AU) [1]
     *                            = eccentricity [2] =
     *                            inclination (degrees) [3] =
     *                            longitude of ascending node
     *                            (degrees) [4] = argument of
     *                            perihelion (degrees) [5] =
     *                            longitude of perihelion
     *                            (degrees) [6] = mean anomaly
     *                            (degrees) [7] = true anomaly
     *                            (degrees) [8] = eccentric
     *                            anomaly (degrees) [9] = mean
     *                            longitude (degrees) [10] =
     *                            sidereal orbital period
     *                            (sidereal years or months for
     *                            Moon) [11] = daily motion
     *                            (degrees/day) [12] = tropical
     *                            period (tropical years) [13] =
     *                            synodic period (days) [14] =
     *                            time of perihelion passage
     *                            (JD) [15] = perihelion
     *                            distance (AU) [16] = aphelion
     *                            distance (AU)
     *
     * @param  string|null &$serr  Error message
     * @return int SE_OK (0) or error code
     */
    public static function getOrbitalElements(
        float $tjd_et,
        int $ipl,
        int $iflag,
        ?array &$dret = null,
        ?string &$serr = null
    ): int {
        $dret = array_fill(0, 17, 0.0);
        $serr = null;

        // Validate planet
        if ($ipl <= 0
            || $ipl === Constants::SE_MEAN_NODE
            || $ipl === Constants::SE_TRUE_NODE
            || $ipl === Constants::SE_MEAN_APOG
            || $ipl === Constants::SE_OSCU_APOG
        ) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                "object $ipl not valid for orbital elements"
            );
            return Constants::SE_ERR;
        }

        // Prepare flags
        $iflJ2000 = ($iflag & Constants::SEFLG_EPHMASK) |
                    Constants::SEFLG_J2000 |
                    Constants::SEFLG_XYZ |
                    Constants::SEFLG_TRUEPOS |
                    Constants::SEFLG_NONUT |
                    Constants::SEFLG_SPEED;

        $iflJ2000p = ($iflag & Constants::SEFLG_EPHMASK) |
                     Constants::SEFLG_J2000 |
                     Constants::SEFLG_TRUEPOS |
                     Constants::SEFLG_NONUT |
                     Constants::SEFLG_SPEED;

        // First, get heliocentric distance
        $x = [];
        if (PlanetsFunctions::calc($tjd_et, $ipl, $iflJ2000p, $x, $serr) < 0) {
            return Constants::SE_ERR;
        }
        $r = $x[2];

        // Determine center (heliocentric or barycentric)
        if ($ipl !== Constants::SE_MOON) {
            if (($iflag & Constants::SEFLG_BARYCTR) && $r > 6.0) {
                $iflJ2000 |= Constants::SEFLG_BARYCTR; // only planets beyond Jupiter
            } else {
                $iflJ2000 |= Constants::SEFLG_HELCTR;
            }
        }

        // Get GM * SM (gravitational parameter)
        $Gmsm = self::getGmsm($tjd_et, $ipl, $iflag, $r, $serr);
        if ($Gmsm === null) {
            return Constants::SE_ERR;
        }

        // Get position and velocity in cartesian coordinates
        $xpos = [];
        if (PlanetsFunctions::calc($tjd_et, $ipl, $iflJ2000, $xpos, $serr) < 0) {
            return Constants::SE_ERR;
        }

        // For Earth, use EMB (Earth-Moon barycenter)
        if ($ipl === Constants::SE_EARTH) {
            $xposm = [];
            $iflMoon = $iflJ2000 & ~(Constants::SEFLG_BARYCTR | Constants::SEFLG_HELCTR);
            if (PlanetsFunctions::calc($tjd_et, Constants::SE_MOON, $iflMoon, $xposm, $serr) < 0) {
                return Constants::SE_ERR;
            }
            // Add Moon contribution to get EMB
            $emrat = 1.0 / (Constants::EARTH_MOON_MRAT + 1.0);
            for ($j = 0; $j <= 5; $j++) {
                $xpos[$j] += $xposm[$j] * $emrat;
            }
        }

        // Calculate node vector: xn = (x - fac*v)*sgn
        $fac = $xpos[2] / $xpos[5];
        $sgn = $xpos[5] / abs($xpos[5]);
        $xn = [];
        $xs = [];
        for ($j = 0; $j <= 2; $j++) {
            $xn[$j] = ($xpos[$j] - $fac * $xpos[$j + 3]) * $sgn;
            $xs[$j] = -$xn[$j];
        }

        // Node: longitude of ascending node
        $rxy = sqrt($xn[0] * $xn[0] + $xn[1] * $xn[1]);
        $cosnode = $xn[0] / $rxy;
        $sinnode = $xn[1] / $rxy;

        // Inclination: cross product of position and velocity
        $xnorm = self::crossProduct(
            $xpos[0], $xpos[1], $xpos[2],
            $xpos[3], $xpos[4], $xpos[5]
        );
        $rxy_norm = $xnorm[0] * $xnorm[0] + $xnorm[1] * $xnorm[1];
        $c2 = $rxy_norm + $xnorm[2] * $xnorm[2];
        $rxyz = sqrt($c2);
        $rxy_norm = sqrt($rxy_norm);
        $sinincl = $rxy_norm / $rxyz;
        $cosincl = sqrt(1.0 - $sinincl * $sinincl);
        if ($xnorm[2] < 0) {
            $cosincl = -$cosincl; // retrograde
        }
        $incl = acos($cosincl) * Math::RAD_TO_DEG;

        // Argument of latitude
        $cosu = $xpos[0] * $cosnode + $xpos[1] * $sinnode;
        $sinu = $xpos[2] / $sinincl;
        $uu = atan2($sinu, $cosu);

        // Semi-major axis
        $rxyz_pos = sqrt(self::squareSum($xpos[0], $xpos[1], $xpos[2]));
        $v2 = self::squareSum($xpos[3], $xpos[4], $xpos[5]);
        $sema = 1.0 / (2.0 / $rxyz_pos - $v2 / $Gmsm);

        // Eccentricity
        $pp = $c2 / $Gmsm;
        $ecce = $pp / $sema;
        if ($ecce > 1.0) {
            $ecce = 1.0;
        }
        $ecce = sqrt(1.0 - $ecce);

        // Eccentric anomaly
        $ecce2 = $ecce;
        if ($ecce2 === 0.0) {
            $ecce2 = 0.0000000001;
        }
        $cosE = (1.0 - $rxyz_pos / $sema) / $ecce2;
        $sinE = self::dotProduct(
            $xpos[0], $xpos[1], $xpos[2],
            $xpos[3], $xpos[4], $xpos[5]
        ) /
                ($ecce2 * sqrt($sema * $Gmsm));
        $eanom = Math::normAngleDeg(atan2($sinE, $cosE) * Math::RAD_TO_DEG);

        // True anomaly
        $ny = 2.0 * atan(sqrt((1.0 + $ecce) / (1.0 - $ecce)) * $sinE / (1.0 + $cosE));
        $tanom = Math::normAngleDeg($ny * Math::RAD_TO_DEG);

        // Adjust true anomaly quadrant
        if ($eanom > 180.0 && $tanom < 180.0) {
            $tanom += 180.0;
        }
        if ($eanom < 180.0 && $tanom > 180.0) {
            $tanom -= 180.0;
        }

        // Mean anomaly
        $manom = Math::normAngleDeg($eanom - $ecce * Math::RAD_TO_DEG * sin($eanom * Math::DEG_TO_RAD));

        // Argument of perihelion (distance from node)
        $parg = Math::mod2PI($uu - $ny) * Math::RAD_TO_DEG;

        // Longitude of perihelion and node
        $node = atan2($sinnode, $cosnode) * Math::RAD_TO_DEG;
        $peri = Math::normAngleDeg($node + $parg);

        // Mean longitude
        $mlon = Math::normAngleDeg($manom + $peri);

        // Sidereal period
        $csid = $sema * sqrt($sema); // in sidereal years
        if ($ipl === Constants::SE_MOON) {
            // For Moon, convert to sidereal months
            $semam = $sema * Constants::AUNIT / 383397772.5;
            $csid = $semam * sqrt($semam);
            $csid *= 27.32166 / 365.25636300;
        }

        // Daily motion
        $dmot = 0.9856076686 / $csid;

        // Tropical period
        $csid *= 365.25636 / 365.242189; // sidereal period in tropical years J2000

        // Precession correction (Simon et al. 1994)
        $T = ($tjd_et - 2451545.0) / 365250.0;
        $T2 = $T * $T;
        $T3 = $T2 * $T;
        $T4 = $T3 * $T;
        $T5 = $T4 * $T;
        $pa = (50288.200 + 222.4045 * $T + 0.2095 * $T2 -
               0.9408 * $T3 - 0.0090 * $T4 + 0.0010 * $T5) / 3600.0 / 365250.0;

        // Year lengths (Simon et al. 1994)
        $ysid = (1295977422.83429 - 2.0 * 2.0441 * $T - 3.0 * 0.00523 * $T2) / 3600.0 / 365250.0;
        $ysid = 360.0 / $ysid;

        $ytrop = (1296027711.03429 + 2.0 * 109.15809 * $T + 3.0 * 0.07207 * $T2 -
                  4.0 * 0.23530 * $T3 - 5.0 * 0.00180 * $T4 + 6.0 * 0.00020 * $T5) / 3600.0 / 365250.0;
        $ytrop = 360.0 / $ytrop;

        $ctro = 360.0 / ($dmot + $pa) / 365.242189; // tropical period in years
        $ctro *= $ysid / $ytrop; // tropical period in tropical years J2000

        // Synodic period
        if ($ipl === Constants::SE_EARTH) {
            $csyn = 0.0;
        } else {
            $csyn = 360.0 / (0.9856076686 - $dmot); // synodic period in days
        }

        // Fill output array
        $dret[0] = $sema;   // semimajor axis
        $dret[1] = $ecce;   // eccentricity
        $dret[2] = $incl;   // inclination
        $dret[3] = $node;   // longitude of ascending node
        $dret[4] = $parg;   // argument of perihelion
        $dret[5] = $peri;   // longitude of perihelion
        $dret[6] = $manom;  // mean anomaly
        $dret[7] = $tanom;  // true anomaly
        $dret[8] = $eanom;  // eccentric anomaly
        $dret[9] = $mlon;   // mean longitude
        $dret[10] = $csid;  // sidereal orbital period
        $dret[11] = $dmot;  // daily motion
        $dret[12] = $ctro;  // tropical period
        $dret[13] = $csyn;  // synodic period
        $dret[14] = $tjd_et - $dret[6] / $dmot; // time of perihelion passage
        $dret[15] = $sema * (1.0 - $ecce);      // perihelion distance
        $dret[16] = $sema * (1.0 + $ecce);      // aphelion distance

        return Constants::SE_OK;
    }

    /**
     * Get GM*SM (gravitational parameter) for a planet
     *
     * Port of get_gmsm() from swecl.c
     *
     * @param  float       $tjd_et Julian day in ET/TT
     * @param  int         $ipl    Planet number
     * @param  int         $iflag  Calculation flags
     * @param  float       $r      Heliocentric distance (AU)
     * @param  string|null &$serr  Error message
     * @return float|null Gravitational parameter or null on error
     */
    private static function getGmsm(
        float $tjd_et,
        int $ipl,
        int $iflag,
        float $r,
        ?string &$serr
    ): ?float {
        // Ratios of mass of Sun to masses of the planets (from swecl.c)
        $plmass = [
            Constants::SE_MERCURY => 6023600.0,
            Constants::SE_VENUS => 408523.719,
            Constants::SE_EARTH => 328900.5,      // Earth+Moon
            Constants::SE_MARS => 3098703.59,
            Constants::SE_JUPITER => 1047.348644,
            Constants::SE_SATURN => 3497.901768,
            Constants::SE_URANUS => 22869.0,
            Constants::SE_NEPTUNE => 19314.0,
            Constants::SE_PLUTO => 130000000.0,
        ];

        $Gmsm = 0.0;
        $plm = 0.0;

        if ($ipl === Constants::SE_MOON) {
            // For Moon: use geocentric constant
            $Gmsm = Constants::HELGRAVCONST * (1.0 + 1.0 / Constants::EARTH_MOON_MRAT) /
                    (Constants::AUNIT * Constants::AUNIT * Constants::AUNIT) * 86400.0 * 86400.0;
        } else {
            // For planets: use heliocentric constant + planet mass
            if (isset($plmass[$ipl])) {
                $plm = 1.0 / $plmass[$ipl];
            } else {
                $plm = 0.0;  // asteroid or unknown
            }

            $Gmsm = Constants::HELGRAVCONST * (1.0 + $plm) /
                    (Constants::AUNIT * Constants::AUNIT * Constants::AUNIT) * 86400.0 * 86400.0;
        }

        return $Gmsm;
    }

    /**
     * Cross product of two 3D vectors
     */
    private static function crossProduct(
        float $x1, float $y1, float $z1,
        float $x2, float $y2, float $z2
    ): array {
        return [
            $y1 * $z2 - $z1 * $y2,
            $z1 * $x2 - $x1 * $z2,
            $x1 * $y2 - $y1 * $x2,
        ];
    }

    /**
     * Dot product of two 3D vectors
     */
    private static function dotProduct(
        float $x1, float $y1, float $z1,
        float $x2, float $y2, float $z2
    ): float {
        return $x1 * $x2 + $y1 * $y2 + $z1 * $z2;
    }

    /**
     * Square sum of three values
     */
    private static function squareSum(float $x, float $y, float $z): float
    {
        return $x * $x + $y * $y + $z * $z;
    }
}
