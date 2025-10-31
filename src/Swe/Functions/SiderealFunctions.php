<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\Math;
use Swisseph\Obliquity;
use Swisseph\Precession;
use Swisseph\SiderealMode;
use Swisseph\SwephFile\SwedState;

/**
 * Sidereal transformation functions for Swiss Ephemeris
 *
 * Port of sidereal calculation functions from sweph.c
 */
class SiderealFunctions
{
    // Solar System Plane constants (from sweph.c)
    private const SSY_PLANE_NODE_E2000 = 107.582569 * Constants::DEGTORAD;
    private const SSY_PLANE_INCL = 1.578701 * Constants::DEGTORAD;

    /**
     * Get ayanamsa correction for precession model differences
     *
     * Port of get_aya_correction() from sweph.c:2959-3001
     *
     * Calculates the difference between the precession model used for ayanamsa
     * and the current precession model. This is needed when t0 != J2000.
     *
     * @param int $iflag Calculation flags
     * @param float &$corr Output: correction in degrees
     * @param string|null &$serr Error string
     * @return int OK or ERR
     */
    public static function getAyaCorrection(int $iflag, float &$corr, ?string &$serr = null): int
    {
        $corr = 0.0;

        if (!SiderealMode::isSet()) {
            return Constants::SE_OK;
        }

        [$sidMode, $sidOpts, $t0User, $ayan0User] = SiderealMode::get();

        // Get sidereal data
        $sidData = \Swisseph\Domain\Sidereal\AyanamsaData::get($sidMode);
        if ($sidData === null) {
            return Constants::SE_OK;
        }

        [$t0, $ayanT0, $t0IsUT, $precOffset] = $sidData;

        // No correction if t0 == J2000
        if ($t0 === Constants::J2000) {
            return Constants::SE_OK;
        }

        // No correction if SE_SIDBIT_NO_PREC_OFFSET is set
        if ($sidOpts & Constants::SE_SIDBIT_NO_PREC_OFFSET) {
            return Constants::SE_OK;
        }

        // No correction if precession model matches offset
        if ($precOffset < 0) {
            $precOffset = 0;
        }

        $swed = SwedState::getInstance();
        $precModel = $swed->getPrecessionModel();

        if ($precModel === $precOffset) {
            return Constants::SE_OK;
        }

        // Convert t0 to TT if it's UT
        $t0Tt = $t0;
        if ($t0IsUT) {
            $dt = \Swisseph\DeltaT::deltaTSecondsFromJd($t0) / 86400.0;
            $t0Tt += $dt;
        }

        // Vernal point (tjd), cartesian
        $x = [1.0, 0.0, 0.0];

        // Precess from J2000 to t0 using current model
        Precession::precess($x, $t0Tt, $iflag, Constants::J_TO_J2000);

        // Save current model and switch to offset model
        $savedModel = $swed->getPrecessionModel();
        $swed->setPrecessionModel($precOffset);

        // Precess back to J2000 using offset model
        Precession::precess($x, $t0Tt, $iflag, Constants::J2000_TO_J);

        // Restore original model
        $swed->setPrecessionModel($savedModel);

        // Convert to ecliptic
        $eps = Obliquity::calc($t0Tt, $iflag, null, null);
        Coordinates::coortrf($x, $x, $eps);

        // Convert to polar
        $xPolar = [0.0, 0.0, 0.0];
        Coordinates::cartPol($x, $xPolar);

        // Get ayanamsa correction
        $corr = $xPolar[0] * Constants::RADTODEG;

        // Normalize to signed value near 0
        if ($corr > 350.0) {
            $corr -= 360.0;
        }

        return Constants::SE_OK;
    }

    /**
     * Transform tropical RA to sidereal longitude (ECL_T0 mode)
     *
     * Port of swi_trop_ra2sid_lon() from sweph.c:3272-3304
     *
     * Input coordinates are J2000 equatorial cartesian.
     * Output is ecliptical sidereal position on ecliptic of t0.
     *
     * @param array $xin Input J2000 equatorial cartesian [x,y,z,dx,dy,dz]
     * @param array &$xout Output ecliptical sidereal cartesian [x,y,z,dx,dy,dz]
     * @param array &$xoutr Output equatorial sidereal cartesian (at epoch t0)
     * @param int $iflag Calculation flags
     * @return int OK or ERR
     */
    public static function tropRa2SidLon(array $xin, array &$xout, array &$xoutr, int $iflag): int
    {
        [$sidMode, $sidOpts, $t0User, $ayan0User] = SiderealMode::get();

        // Get sidereal data
        $sidData = \Swisseph\Domain\Sidereal\AyanamsaData::get($sidMode);
        if ($sidData === null) {
            return Constants::SE_ERR;
        }

        [$t0, $ayanT0, $t0IsUT, $precOffset] = $sidData;

        // Convert t0 to TT if needed
        $t0Tt = $t0;
        if ($t0IsUT) {
            $dt = \Swisseph\DeltaT::deltaTSecondsFromJd($t0) / 86400.0;
            $t0Tt += $dt;
        }

        // Copy input
        $x = [
            $xin[0], $xin[1], $xin[2],
            $xin[3] ?? 0.0, $xin[4] ?? 0.0, $xin[5] ?? 0.0
        ];

        // If t0 != J2000, precess to t0
        if ($t0Tt !== Constants::J2000) {
            // Precess position
            Precession::precess($x, $t0Tt, 0, Constants::J2000_TO_J);
            // Precess velocity
            $vel = [$x[3], $x[4], $x[5]];
            Precession::precess($vel, $t0Tt, 0, Constants::J2000_TO_J);
            $x[3] = $vel[0];
            $x[4] = $vel[1];
            $x[5] = $vel[2];
        }

        // Save equatorial sidereal position (at epoch t0)
        $xoutr = $x;

        // Get obliquity at t0
        $oectmp = new \Swisseph\EpsilonData();
        $oectmp->calculate($t0Tt, $iflag);

        // Convert to ecliptic coordinates of t0
        Coordinates::coortrf2($x, $x, $oectmp->seps, $oectmp->ceps);
        if ($iflag & Constants::SEFLG_SPEED) {
            $vel = [$x[3], $x[4], $x[5]];
            Coordinates::coortrf2($vel, $vel, $oectmp->seps, $oectmp->ceps);
            $x[3] = $vel[0];
            $x[4] = $vel[1];
            $x[5] = $vel[2];
        }

        // Convert to polar coordinates
        Coordinates::cartPolSp($x, $x);

        // Get ayanamsa correction
        $corr = 0.0;
        self::getAyaCorrection($iflag, $corr, $serr);

        // Subtract ayan_t0 and add correction
        $x[0] -= $ayanT0 * Constants::DEGTORAD;
        $x[0] = Math::normalizeRadians($x[0] + $corr * Constants::DEGTORAD);

        // Convert back to cartesian
        Coordinates::polCartSp($x, $xout);

        return Constants::SE_OK;
    }

    /**
     * Transform tropical RA to sidereal longitude on solar system plane
     *
     * Port of swi_trop_ra2sid_lon_sosy() from sweph.c:3307-3358
     *
     * Input coordinates are J2000 equatorial cartesian.
     * Output is sidereal position projected on solar system equator plane.
     *
     * @param array $xin Input J2000 equatorial cartesian [x,y,z,dx,dy,dz]
     * @param array &$xout Output sidereal cartesian on SSY plane
     * @param int $iflag Calculation flags
     * @return int OK or ERR
     */
    public static function tropRa2SidLonSosy(array $xin, array &$xout, int $iflag): int
    {
        [$sidMode, $sidOpts, $t0User, $ayan0User] = SiderealMode::get();

        // Get sidereal data
        $sidData = \Swisseph\Domain\Sidereal\AyanamsaData::get($sidMode);
        if ($sidData === null) {
            return Constants::SE_ERR;
        }

        [$t0, $ayanT0, $t0IsUT, $precOffset] = $sidData;

        // Convert t0 to TT if needed
        $t0Tt = $t0;
        if ($t0IsUT) {
            $dt = \Swisseph\DeltaT::deltaTSecondsFromJd($t0) / 86400.0;
            $t0Tt += $dt;
        }

        // Copy input
        $x = [
            $xin[0], $xin[1], $xin[2],
            $xin[3] ?? 0.0, $xin[4] ?? 0.0, $xin[5] ?? 0.0
        ];

        // Get J2000 obliquity
        $swed = SwedState::getInstance();
        $oe = $swed->oec2000;

        // Planet to ecliptic 2000
        Coordinates::coortrf2($x, $x, $oe->seps, $oe->ceps);
        if ($iflag & Constants::SEFLG_SPEED) {
            $vel = [$x[3], $x[4], $x[5]];
            Coordinates::coortrf2($vel, $vel, $oe->seps, $oe->ceps);
            $x[3] = $vel[0];
            $x[4] = $vel[1];
            $x[5] = $vel[2];
        }

        // To polar coordinates
        Coordinates::cartPolSp($x, $x);

        // To solar system equator
        $x[0] -= self::SSY_PLANE_NODE_E2000;
        Coordinates::polCartSp($x, $x);
        Coordinates::coortrf($x, $x, self::SSY_PLANE_INCL);
        $vel = [$x[3], $x[4], $x[5]];
        Coordinates::coortrf($vel, $vel, self::SSY_PLANE_INCL);
        $x[3] = $vel[0];
        $x[4] = $vel[1];
        $x[5] = $vel[2];
        Coordinates::cartPolSp($x, $x);

        // Calculate zero point of t0 in J2000 system
        $x0 = [1.0, 0.0, 0.0];

        if ($t0Tt !== Constants::J2000) {
            Precession::precess($x0, $t0Tt, 0, Constants::J_TO_J2000);
        }

        // Zero point to ecliptic 2000
        Coordinates::coortrf2($x0, $x0, $oe->seps, $oe->ceps);

        // To polar coordinates
        $x0Polar = [0.0, 0.0, 0.0];
        Coordinates::cartPol($x0, $x0Polar);

        // To solar system equator
        $x0Polar[0] -= self::SSY_PLANE_NODE_E2000;
        Coordinates::polCart($x0Polar, $x0);
        Coordinates::coortrf($x0, $x0, self::SSY_PLANE_INCL);
        Coordinates::cartPol($x0, $x0Polar);

        // Measure planet from zero point
        $x[0] -= $x0Polar[0];
        $x[0] *= Constants::RADTODEG;

        // Get ayanamsa correction
        $corr = 0.0;
        self::getAyaCorrection($iflag, $corr, $serr);

        // Subtract ayan_t0 and add correction
        $x[0] -= $ayanT0;
        $x[0] = Math::normalizeDegrees($x[0] + $corr) * Constants::DEGTORAD;

        // Back to cartesian
        Coordinates::polCartSp($x, $xout);

        return Constants::SE_OK;
    }
}
