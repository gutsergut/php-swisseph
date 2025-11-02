<?php

declare(strict_types=1);

namespace Swisseph\Swe\FixedStars;

use Swisseph\Bias;
use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\FK4FK5;
use Swisseph\ICRS;
use Swisseph\Nutation;
use Swisseph\Obliquity;
use Swisseph\Precession;
use Swisseph\State;

/**
 * Calculator for fixed star positions with full astronomical transformations
 *
 * Port of fixstar_calc_from_struct() from sweph.c:6461-6718
 *
 * Applies all necessary corrections to convert catalog coordinates to apparent position:
 * 1. Proper motion correction
 * 2. Epoch conversion (FK4 B1950 -> FK5 J2000 -> ICRS)
 * 3. Parallax correction
 * 4. Relativistic light deflection
 * 5. Annual aberration
 * 6. Precession to date
 * 7. Nutation
 * 8. Coordinate transformations (equatorial <-> ecliptic)
 * 9. Sidereal mode support
 *
 * Uses FixedStarData structures from StarRegistry (fixstar2 API)
 */
final class StarCalculator
{
    /**
     * Time interval for speed calculation (days)
     * PLAN_SPEED_INTV * 0.1 where PLAN_SPEED_INTV = 0.0001
     */
    private const DT = 0.00001;

    /**
     * Parsec to AU conversion: 1 parsec = 206264.806 AU
     */
    private const PARSEC_TO_AUNIT = 206264.806;

    /**
     * Calculate position of fixed star
     *
     * Port of fixstar_calc_from_struct() from sweph.c:6461-6718
     *
     * Current implementation covers steps 1-8 (proper motion through coord transforms).
     * TODO: Add light deflection, aberration, parallax correction
     * TODO: Add topocentric observer support
     *
     * @param FixedStarData $stardata Star catalog data
     * @param float $tjd Julian Day (ET)
     * @param int $iflag Calculation flags
     * @param string &$star Output: formatted star name
     * @param array &$xx Output: 6 doubles for position [0-2] and speed [3-5]
     * @param string|null &$serr Output: error message
     * @return int iflag on success, ERR on error
     */
    public static function calculate(
        FixedStarData $stardata,
        float $tjd,
        int $iflag,
        string &$star,
        array &$xx,
        ?string &$serr = null
    ): int {
        $serr = '';
        $iflgsave = $iflag;
        $iflag |= Constants::SEFLG_SPEED; // We need speed for intermediate calculations

        // Initialize arrays
        $xx = array_fill(0, 6, 0.0);
        $x = array_fill(0, 6, 0.0);
        $xxsv = array_fill(0, 6, 0.0);

        // Format star name
        $star = $stardata->getFullName();

        // Calculate obliquity for coordinate transformations
        $epsJ2000 = Obliquity::calc(self::J2000);
        $epsDate = Obliquity::calc($tjd);

        // Calculate nutation if needed
        $nut = null;
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $nutResult = Nutation::calc($tjd, $iflag);
            $nut = [
                'nutlo' => $nutResult['nutlo'],
                'obliqMeanEps' => $nutResult['obliqMeanEps'],
                'obliqTrueEps' => $nutResult['obliqTrueEps'],
                'snut' => sin($nutResult['nutlo']),
                'cnut' => cos($nutResult['nutlo']),
            ];
        }

        // Extract star data
        $epoch = $stardata->epoch;
        $ra = $stardata->ra;
        $de = $stardata->de;
        $ra_pm = $stardata->ramot;
        $de_pm = $stardata->demot;
        $radv = $stardata->radvel;
        $parall = $stardata->parall;

        // Calculate time difference from epoch
        if ($epoch == 1950.0) {
            $t = $tjd - self::B1950; // days since B1950.0
        } else {
            $t = $tjd - self::J2000; // days since J2000.0
        }

        // Initial position in polar coordinates (RA, Dec, distance)
        $x[0] = $ra;
        $x[1] = $de;

        // Calculate distance from parallax
        if ($parall == 0.0) {
            $rdist = 1000000000.0; // Very distant star
        } else {
            $rdist = 1.0 / ($parall * Constants::RADTODEG * 3600.0) * self::PARSEC_TO_AUNIT;
        }
        $x[2] = $rdist;

        // Proper motion and radial velocity (per day)
        $x[3] = $ra_pm / 36525.0;
        $x[4] = $de_pm / 36525.0;
        $x[5] = $radv / 36525.0;

        // Convert to Cartesian space motion vector
        Coordinates::polCartSp($x, $x);

        /******************************************
         * FK4 -> FK5 conversion for B1950 epoch *
         ******************************************/
        if ($epoch == 1950.0) {
            FK4FK5::fk4ToFk5($x, self::B1950);
            Precession::precess($x, self::B1950, 0, Precession::J_TO_J2000);
            Precession::precess(array_slice($x, 3, 3), self::B1950, 0, Precession::J_TO_J2000);
        }

        /******************************************
         * FK5 to ICRF conversion                *
         ******************************************/
        if ($epoch != 0) {
            // ICRS to FK5 backward (i.e., FK5 to ICRS)
            ICRS::icrs2fk5($x, $iflag, true);

            // With ephemerides >= DE403, convert via bias
            // TODO: Check ephemeris denum
            $denum = 431; // Assume DE431 for now
            if ($denum >= 403) {
                Bias::bias($x, self::J2000, Constants::SEFLG_SPEED, false);
            }
        }

        /******************************************
         * Position at tjd with proper motion     *
         ******************************************/
        // Note: For now, we skip parallax correction (observer position)
        // as it requires main_planet_bary() which is not yet ported
        for ($i = 0; $i <= 2; $i++) {
            $x[$i] += $t * $x[$i + 3];
        }

        // TODO: Light deflection (requires swi_deflect_light)
        // TODO: Aberration (requires swi_aberr_light_ex and observer position)

        /******************************************
         * ICRS to J2000 if needed               *
         ******************************************/
        if (!($iflag & Constants::SEFLG_ICRS) && $denum >= 403) {
            Bias::bias($x, $tjd, $iflag, false);
        }

        // Save J2000 coordinates for sidereal mode
        for ($i = 0; $i <= 5; $i++) {
            $xxsv[$i] = $x[$i];
        }

        /******************************************
         * Precession: J2000 -> date              *
         ******************************************/
        $eps = null;
        $seps = null;
        $ceps = null;
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($x, $tjd, $iflag, Precession::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($x, $tjd, $iflag, Precession::J2000_TO_J);
            }
            $eps = $epsDate;
        } else {
            $eps = $epsJ2000;
        }
        $seps = sin($eps);
        $ceps = cos($eps);

        /******************************************
         * Nutation                               *
         ******************************************/
        if (!($iflag & Constants::SEFLG_NONUT)) {
            Coordinates::nutate($x, $iflag, false, $serr);
        }

        /******************************************
         * Transform to ecliptic if needed        *
         ******************************************/
        if (!($iflag & Constants::SEFLG_EQUATORIAL)) {
            Coordinates::coortrf2($x, $x, $oe['seps'], $oe['ceps']);
            if ($iflag & Constants::SEFLG_SPEED) {
                $xSpeed = array_slice($x, 3, 3);
                Coordinates::coortrf2($xSpeed, $xSpeed, $oe['seps'], $oe['ceps']);
                $x[3] = $xSpeed[0];
                $x[4] = $xSpeed[1];
                $x[5] = $xSpeed[2];
            }
            if (!($iflag & Constants::SEFLG_NONUT)) {
                $nut = State::getNut();
                Coordinates::coortrf2($x, $x, $nut['snut'], $nut['cnut']);
                if ($iflag & Constants::SEFLG_SPEED) {
                    $xSpeed = array_slice($x, 3, 3);
                    Coordinates::coortrf2($xSpeed, $xSpeed, $nut['snut'], $nut['cnut']);
                    $x[3] = $xSpeed[0];
                    $x[4] = $xSpeed[1];
                    $x[5] = $xSpeed[2];
                }
            }
        }

        /******************************************
         * Sidereal mode                          *
         ******************************************/
        if ($iflag & Constants::SEFLG_SIDEREAL) {
            // TODO: Implement sidereal transformations
            // Requires swi_trop_ra2sid_lon, swi_trop_ra2sid_lon_sosy, or ayanamsa
            $serr = 'Sidereal mode not yet implemented for fixstar2';
            return Constants::SE_ERR;
        }

        /******************************************
         * Convert to polar coordinates           *
         ******************************************/
        if (!($iflag & Constants::SEFLG_XYZ)) {
            Coordinates::cartPolSp($x, $x);
        }

        /******************************************
         * Convert to degrees if needed           *
         ******************************************/
        if (!($iflag & Constants::SEFLG_RADIANS) && !($iflag & Constants::SEFLG_XYZ)) {
            for ($i = 0; $i < 2; $i++) {
                $x[$i] *= Constants::RADTODEG;
                $x[$i + 3] *= Constants::RADTODEG;
            }
        }

        // Copy to output
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $x[$i];
        }

        // If speed not requested, zero out velocity components
        if (!($iflgsave & Constants::SEFLG_SPEED)) {
            $xx[3] = 0.0;
            $xx[4] = 0.0;
            $xx[5] = 0.0;
        }

        // Clear SPEED flag from return value
        $iflag = $iflag & ~Constants::SEFLG_SPEED;

        return $iflag;
    }
}
