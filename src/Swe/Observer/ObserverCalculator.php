<?php

declare(strict_types=1);

namespace Swisseph\Swe\Observer;

use Swisseph\Constants;
use Swisseph\State;
use Swisseph\SwephFile\SwedState;
use Swisseph\Coordinates;
use Swisseph\DeltaT;

/**
 * Observer position calculator for topocentric coordinates.
 *
 * Port of swi_get_observer() from sweph.c:7336-7441.
 * Computes barycentric position and velocity of observer on Earth's surface.
 *
 * NO SIMPLIFICATIONS - Full C code fidelity maintained.
 */
final class ObserverCalculator
{
    /**
     * Calculate observer's barycentric position and velocity.
     *
     * Port of swi_get_observer() from sweph.c:7336-7441
     *
     * The observer position depends on:
     * - Geographic coordinates (lon, lat, altitude)
     * - Sidereal time (depends on UT)
     * - Earth's oblateness (ellipsoid shape)
     * - Nutation (if SEFLG_NONUT not set)
     * - Precession to J2000
     *
     * @param float $tjd Julian day (Ephemeris Time)
     * @param int $iflag Calculation flags
     * @param bool $doSave Whether to save result in SwedState
     * @param array &$xobs Output: observer position/velocity [6] (modified in place)
     * @param string|null &$serr Error message
     * @return int OK (0) or ERR (-1)
     */
    public static function getObserver(
        float $tjd,
        int $iflag,
        bool $doSave,
        array &$xobs,
        ?string &$serr
    ): int {
        $swed = SwedState::getInstance();

        // Check if geographic position was set via swe_set_topo()
        [$geolon, $geolat, $geoalt] = State::getTopo();
        if (!$swed->geoposIsSet && ($geolon == 0.0 && $geolat == 0.0 && $geoalt == 0.0)) {
            $serr = "geographic position has not been set";
            return Constants::SE_ERR;
        }

        // Update SwedState with current topo data
        if (!$swed->geoposIsSet || $swed->topd->geolon != $geolon || $swed->topd->geolat != $geolat || $swed->topd->geoalt != $geoalt) {
            $swed->topd->geolon = $geolon;
            $swed->topd->geolat = $geolat;
            $swed->topd->geoalt = $geoalt;
            $swed->geoposIsSet = true;
            $swed->topd->teval = 0.0; // Force recalculation
        }

        /* Geocentric position of observer depends on sidereal time,
         * which depends on UT.
         * Compute UT from ET. This UT will be slightly different
         * from the user's UT, but this difference is extremely small.
         */
        $delt = DeltaT::deltaTSecondsFromJd($tjd) / 86400.0; // Convert seconds to days
        $tjd_ut = $tjd - $delt;

        // Get obliquity and nutation
        if ($swed->oec->teps == $tjd && $swed->tnut == $tjd) {
            $eps = $swed->oec->eps;
            $nutlo = [$swed->dpsi, $swed->deps];
        } else {
            // Calculate epsilon (obliquity) - returns value in radians
            $eps = \Swisseph\Obliquity::calc($tjd, $iflag);

            // Calculate nutation if needed
            if (!($iflag & Constants::SEFLG_NONUT)) {
                [$nutlo[0], $nutlo[1]] = \Swisseph\Nutation::calc($tjd);
            } else {
                $nutlo = [0.0, 0.0];
            }
        }

        $nut = 0.0;
        if ($iflag & Constants::SEFLG_NONUT) {
            $nut = 0.0;
        } else {
            $eps += $nutlo[1];
            $nut = $nutlo[0];
        }

        /* Mean or apparent sidereal time, depending on whether or
         * not SEFLG_NONUT is set */
        $sidt = \Swisseph\Sidereal::sidtime0($tjd_ut, $eps * Constants::RADTODEG, $nut * Constants::RADTODEG);
        $sidt *= 15.0; // Convert hours to degrees

        /* Length of position and speed vectors;
         * the height above sea level must be taken into account.
         * With the moon, an altitude of 3000 m makes a difference
         * of about 2 arc seconds.
         * Height is referred to the average sea level. However,
         * the spheroid (geoid), which is defined by the average
         * sea level (or rather by all points of same gravitational
         * potential), is of irregular shape and cannot easily
         * be taken into account. Therefore, we refer height to
         * the surface of the ellipsoid. The resulting error
         * is below 500 m, i.e. 0.2 - 0.3 arc seconds with the moon.
         */
        $f = Constants::EARTH_OBLATENESS;
        $re = Constants::EARTH_RADIUS;

        $cosfi = cos($geolat * Constants::DEGTORAD);
        $sinfi = sin($geolat * Constants::DEGTORAD);
        $cc = 1.0 / sqrt($cosfi * $cosfi + (1.0 - $f) * (1.0 - $f) * $sinfi * $sinfi);
        $ss = (1.0 - $f) * (1.0 - $f) * $cc;

        /* Neglect polar motion (displacement of a few meters), as long as
         * we use the earth ellipsoid */

        /* Add sidereal time */
        $cosl = cos(($geolon + $sidt) * Constants::DEGTORAD);
        $sinl = sin(($geolon + $sidt) * Constants::DEGTORAD);
        $h = $geoalt;

        // Cartesian position in meters
        $xobs[0] = ($re * $cc + $h) * $cosfi * $cosl;
        $xobs[1] = ($re * $cc + $h) * $cosfi * $sinl;
        $xobs[2] = ($re * $ss + $h) * $sinfi;

        /* Polar coordinates */
        $xobs = Coordinates::cartesianToPolar($xobs);

        /* Speed: Earth rotation speed */
        $xobs[3] = Constants::EARTH_ROT_SPEED;
        $xobs[4] = $xobs[5] = 0.0;

        /* Convert polar to cartesian (with speed) */
        $xobs = Coordinates::polarToCartesian($xobs, true);

        /* Convert to AU */
        for ($i = 0; $i <= 5; $i++) {
            $xobs[$i] /= Constants::AUNIT;
        }

        /* Subtract nutation, set backward flag */
        if (!($iflag & Constants::SEFLG_NONUT)) {
            // Rotate by -nutation in longitude
            Coordinates::rotate($xobs, -$swed->snut, $swed->cnut);

            // Also rotate velocity
            $xobs_vel = array_slice($xobs, 3, 3);
            Coordinates::rotate($xobs_vel, -$swed->snut, $swed->cnut);
            $xobs[3] = $xobs_vel[0];
            $xobs[4] = $xobs_vel[1];
            $xobs[5] = $xobs_vel[2];

            // Apply nutation (backward = true)
            Coordinates::nutate(
                $xobs,
                $swed->nutMatrix,
                $swed->nutMatrixVelocity,
                true,
                $iflag | Constants::SEFLG_SPEED
            );
        }

        /* Precess to J2000 */
        \Swisseph\Precession::precess($xobs, $tjd, $iflag, 1); // J_TO_J2000 = 1

        // Precess speed
        \Swisseph\Precession::precessSpeed($xobs, $tjd, $iflag, 1); // J_TO_J2000 = 1        /* Neglect frame bias (displacement of 45cm) */

        /* Save */
        if ($doSave) {
            for ($i = 0; $i <= 5; $i++) {
                $swed->topd->xobs[$i] = $xobs[$i];
            }
            $swed->topd->teval = $tjd;
            $swed->topd->tjd_ut = $tjd_ut;
        }

        return Constants::SE_OK;
    }
}

