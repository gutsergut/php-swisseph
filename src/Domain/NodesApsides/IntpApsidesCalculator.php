<?php

declare(strict_types=1);

namespace Swisseph\Domain\NodesApsides;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\Nutation;
use Swisseph\Obliquity;
use Swisseph\Precession;
use Swisseph\VectorMath;
use Swisseph\SwephFile\SwedState;
use Swisseph\Domain\Moshier\MoshierMoon;
use Swisseph\Swe\Functions\CentisecFunctions;

/**
 * Calculator for interpolated lunar apsides (apogee/perigee)
 *
 * Full port of intp_apsides() from sweph.c:5627-5775
 *
 * This calculates:
 * - SE_INTP_APOG (21): Interpolated lunar apogee (Lilith variant)
 * - SE_INTP_PERG (22): Interpolated lunar perigee
 *
 * The algorithm uses numerical iteration on Moshier ELP2000-85 lunar ephemeris
 * to find the exact time when Moon's distance reaches a local maximum (apogee)
 * or minimum (perigee).
 *
 * IMPORTANT: This function only works with Moshier ephemeris (SEFLG_MOSEPH is implied).
 * Valid date range: MOSHLUEPH_START to MOSHLUEPH_END (~ -3027 to +2972)
 */
class IntpApsidesCalculator
{
    // Valid date range for Moshier lunar ephemeris
    private const MOSHLUEPH_START = 625674.5;      // -3027 Jan 1
    private const MOSHLUEPH_END = 2816090.5;       // +2972 Dec 31

    // Speed calculation interval (days)
    private const SPEED_INTV = 0.1;

    /**
     * Calculate interpolated lunar apogee or perigee
     *
     * @param float $tjd Julian day TT
     * @param int $ipl SE_INTP_APOG (21) or SE_INTP_PERG (22)
     * @param int $iflag Calculation flags
     * @param array &$xreturn Output array (24 elements like C ndp->xreturn)
     * @param string|null &$serr Error string
     * @return int iflag on success, SE_ERR on error
     */
    public static function calculate(
        float $tjd,
        int $ipl,
        int $iflag,
        array &$xreturn,
        ?string &$serr = null
    ): int {
        // Initialize return array (24 elements like C ndp->xreturn)
        $xreturn = array_fill(0, 24, 0.0);

        // Heliocentric/barycentric lunar apogee not allowed
        if (($iflag & Constants::SEFLG_HELCTR) || ($iflag & Constants::SEFLG_BARYCTR)) {
            return $iflag;
        }

        // Check valid date range
        if ($tjd < self::MOSHLUEPH_START || $tjd > self::MOSHLUEPH_END) {
            $serr = sprintf(
                "Interpolated apsides are restricted to JD %.1f - JD %.1f",
                self::MOSHLUEPH_START,
                self::MOSHLUEPH_END
            );
            return Constants::SE_ERR;
        }

        // Map public ipl (21, 22) to internal indices (4, 5)
        if ($ipl === Constants::SE_INTP_APOG) {
            $ipli = Constants::SEI_INTP_APOG;
        } elseif ($ipl === Constants::SE_INTP_PERG) {
            $ipli = Constants::SEI_INTP_PERG;
        } else {
            $serr = "Invalid planet index for interpolated apsides: $ipl";
            return Constants::SE_ERR;
        }

        // Get obliquity and nutation for coordinate transformations
        $swed = SwedState::getInstance();
        $swed->oec->calculate($tjd, $iflag);

        $oe = $swed->oec;
        $swed->ensureNutation($tjd, $iflag, $oe->seps, $oe->ceps);
        // Nutation is stored directly in swed->snut, swed->cnut

        // Create Moshier Moon calculator
        $moon = new MoshierMoon();

        // Calculate 3 apside positions for speed computation
        // t - speed_intv, t + speed_intv, t
        $xpos = [];
        $speedIntv = self::SPEED_INTV;

        for ($t = $tjd - $speedIntv, $i = 0; $i < 3; $t += $speedIntv, $i++) {
            if (!($iflag & Constants::SEFLG_SPEED) && $i !== 1) {
                continue;
            }

            $xpos[$i] = array_fill(0, 6, 0.0);
            $pol = array_fill(0, 3, 0.0);

            // Call MoshierMoon::intpApsides() to get position
            $moon->intpApsides($t, $pol, $ipli);

            // pol is in ecliptic polar: [longitude (radians), latitude (radians), distance (AU)]
            // moon4() already converts to radians via STR multiplier
            $xpos[$i][0] = $pol[0];
            $xpos[$i][1] = $pol[1];
            $xpos[$i][2] = $pol[2]; // distance in AU
        }

        // Position + speed calculation
        $xx = array_fill(0, 6, 0.0);
        for ($i = 0; $i < 3; $i++) {
            $xx[$i] = $xpos[1][$i];
            $xx[$i + 3] = 0.0;
        }

        if ($iflag & Constants::SEFLG_SPEED) {
            // Speed in longitude: use swe_difrad2n for proper angle difference
            $xx[3] = CentisecFunctions::difrad2n($xpos[2][0], $xpos[0][0]) / $speedIntv / 2.0;
            // Speed in latitude: simple difference
            $xx[4] = ($xpos[2][1] - $xpos[0][1]) / $speedIntv / 2.0;
            // Speed in distance
            $xx[5] = ($xpos[2][2] - $xpos[0][2]) / $speedIntv / 2.0;
        }

        // Convert polar to cartesian
        Coordinates::polCartSp($xx, $xx);

        // Light-time correction
        if (!($iflag & Constants::SEFLG_TRUEPOS)) {
            $dt = sqrt(VectorMath::squareSum($xx)) * Constants::AUNIT / Constants::CLIGHT / 86400.0;
            for ($i = 1; $i < 3; $i++) {
                $xx[$i] -= $dt * $xx[$i + 3];
            }
        }

        // Store ecliptic cartesian in xreturn[6..11]
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[$i + 6] = $xx[$i];
        }

        // Convert to equatorial cartesian (xreturn[18..23])
        $xeq = [];
        Coordinates::coortrf2(array_slice($xreturn, 6, 3), $xeq, -$oe->seps, $oe->ceps);
        for ($i = 0; $i < 3; $i++) {
            $xreturn[18 + $i] = $xeq[$i];
        }
        if ($iflag & Constants::SEFLG_SPEED) {
            $xeq_vel = [];
            Coordinates::coortrf2(array_slice($xreturn, 9, 3), $xeq_vel, -$oe->seps, $oe->ceps);
            for ($i = 0; $i < 3; $i++) {
                $xreturn[21 + $i] = $xeq_vel[$i];
            }
        }

        // Handle coordinate frame transformations based on flags
        if ($iflag & Constants::SEFLG_SIDEREAL) {
            // Sidereal coordinates - TODO: implement if needed
            // For now, fall through to tropical
        }

        if ($iflag & Constants::SEFLG_J2000) {
            // J2000 ecliptic frame
            $x = array_fill(0, 6, 0.0);
            for ($i = 0; $i <= 5; $i++) {
                $x[$i] = $xreturn[18 + $i];
            }

            // Precess to J2000
            Precession::precess($x, $tjd, $iflag, Constants::J_TO_J2000);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($x, $tjd, $iflag, Constants::J_TO_J2000);
            }

            for ($i = 0; $i <= 5; $i++) {
                $xreturn[18 + $i] = $x[$i];
            }

            // Equatorial polar
            $eqTemp = [];
            Coordinates::cartPolSp(array_slice($xreturn, 18, 6), $eqTemp);
            for ($i = 0; $i < 6; $i++) {
                $xreturn[12 + $i] = $eqTemp[$i];
            }

            // Convert equatorial J2000 to ecliptic J2000
            $xecl = [];
            Coordinates::coortrf2(array_slice($xreturn, 18, 3), $xecl, $swed->oec2000->seps, $swed->oec2000->ceps);
            for ($i = 0; $i < 3; $i++) {
                $xreturn[6 + $i] = $xecl[$i];
            }
            if ($iflag & Constants::SEFLG_SPEED) {
                $xecl_vel = [];
                Coordinates::coortrf2(array_slice($xreturn, 21, 3), $xecl_vel, $swed->oec2000->seps, $swed->oec2000->ceps);
                for ($i = 0; $i < 3; $i++) {
                    $xreturn[9 + $i] = $xecl_vel[$i];
                }
            }

            // Ecliptic polar
            $eclTemp = [];
            Coordinates::cartPolSp(array_slice($xreturn, 6, 6), $eclTemp);
            for ($i = 0; $i < 6; $i++) {
                $xreturn[$i] = $eclTemp[$i];
            }
        } else {
            // Tropical ecliptic of date (default)

            // Apply nutation to equatorial coordinates
            if (!($iflag & Constants::SEFLG_NONUT)) {
                $xeq_nut = [];
                Coordinates::coortrf2(array_slice($xreturn, 18, 3), $xeq_nut, -$swed->snut, $swed->cnut);
                for ($i = 0; $i < 3; $i++) {
                    $xreturn[18 + $i] = $xeq_nut[$i];
                }
                if ($iflag & Constants::SEFLG_SPEED) {
                    $xeq_vel_nut = [];
                    Coordinates::coortrf2(array_slice($xreturn, 21, 3), $xeq_vel_nut, -$swed->snut, $swed->cnut);
                    for ($i = 0; $i < 3; $i++) {
                        $xreturn[21 + $i] = $xeq_vel_nut[$i];
                    }
                }
            }

            // Equatorial cartesian to polar
            $eqPol = [];
            Coordinates::cartPolSp(array_slice($xreturn, 18, 6), $eqPol);
            for ($i = 0; $i < 6; $i++) {
                $xreturn[12 + $i] = $eqPol[$i];
            }

            // Convert equatorial to ecliptic of date
            $xecl = [];
            Coordinates::coortrf2(array_slice($xreturn, 18, 3), $xecl, $oe->seps, $oe->ceps);
            for ($i = 0; $i < 3; $i++) {
                $xreturn[6 + $i] = $xecl[$i];
            }
            if ($iflag & Constants::SEFLG_SPEED) {
                $xecl_vel = [];
                Coordinates::coortrf2(array_slice($xreturn, 21, 3), $xecl_vel, $oe->seps, $oe->ceps);
                for ($i = 0; $i < 3; $i++) {
                    $xreturn[9 + $i] = $xecl_vel[$i];
                }
            }

            // Apply nutation to ecliptic
            if (!($iflag & Constants::SEFLG_NONUT)) {
                $xecl_nut = [];
                Coordinates::coortrf2(array_slice($xreturn, 6, 3), $xecl_nut, $swed->snut, $swed->cnut);
                for ($i = 0; $i < 3; $i++) {
                    $xreturn[6 + $i] = $xecl_nut[$i];
                }
                if ($iflag & Constants::SEFLG_SPEED) {
                    $xecl_vel_nut = [];
                    Coordinates::coortrf2(array_slice($xreturn, 9, 3), $xecl_vel_nut, $swed->snut, $swed->cnut);
                    for ($i = 0; $i < 3; $i++) {
                        $xreturn[9 + $i] = $xecl_vel_nut[$i];
                    }
                }
            }

            // Ecliptic cartesian to polar
            $eclPol = [];
            Coordinates::cartPolSp(array_slice($xreturn, 6, 6), $eclPol);
            for ($i = 0; $i < 6; $i++) {
                $xreturn[$i] = $eclPol[$i];
            }
        }

        // Convert radians to degrees
        for ($i = 0; $i < 2; $i++) {
            $xreturn[$i] *= Constants::RADTODEG;        // ecliptic lon, lat
            $xreturn[$i + 3] *= Constants::RADTODEG;    // speed lon, lat
            $xreturn[$i + 12] *= Constants::RADTODEG;   // equatorial RA, Dec
            $xreturn[$i + 15] *= Constants::RADTODEG;   // speed RA, Dec
        }

        // Normalize longitude
        $xreturn[0] = \swe_degnorm($xreturn[0]);
        $xreturn[12] = \swe_degnorm($xreturn[12]);

        return $iflag;
    }
}
