<?php

namespace Swisseph\Swe\Observer;

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;

/**
 * Observer (topocentric) position calculation
 * Port of swi_get_observer() from sweph.c
 */
class Observer
{
    /**
     * Get observer (topocentric) position in cartesian coordinates
     *
     * Port of swi_get_observer() from sweph.c:6528-6643
     *
     * @param float $tjd_ut Julian day in UT
     * @param int $iflag Calculation flags
     * @param bool $do_save Whether to save in SwedState::topd
     * @param array &$xobs Output: observer position [x, y, z, vx, vy, vz] in AU
     * @param string|null &$serr Error message
     * @return int OK or ERR
     */
    public static function getObserver(
        float $tjd_ut,
        int $iflag,
        bool $do_save,
        array &$xobs,
        ?string &$serr = null
    ): int {
        $xobs = array_fill(0, 6, 0.0);

        $swed = SwedState::getInstance();

        // Get topocentric data
        $lon = $swed->topd->geolon;
        $lat = $swed->topd->geolat;
        $height = $swed->topd->geoalt;

        // Calculate sidereal time
        // From sweph.c:6556-6568
        $tjd_tt = $tjd_ut + \Swisseph\DeltaT::deltaTSecondsFromJd($tjd_ut) / 86400.0;
        $armc = self::sidtime($tjd_ut, $lon);
        $mdd = self::diurnalArc($lat, $height, $armc);

        // Get Earth's center position
        $xe = array_fill(0, 6, 0.0);
        $retc = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
            $tjd_tt,
            Constants::SE_EARTH,
            $iflag | Constants::SEFLG_J2000 | Constants::SEFLG_TRUEPOS | Constants::SEFLG_NONUT,
            $xe,
            $serr
        );

        if ($retc === Constants::SE_ERR) {
            return Constants::SE_ERR;
        }

        // Calculate observer offset from Earth center
        // From sweph.c:6585-6632
        $xobs[0] = $mdd->x;
        $xobs[1] = $mdd->y;
        $xobs[2] = $mdd->z;
        $xobs[3] = $mdd->dx;
        $xobs[4] = $mdd->dy;
        $xobs[5] = $mdd->dz;

        // Precess from J2000 to date if needed
        if (!($iflag & Constants::SEFLG_J2000)) {
            \Swisseph\Precession::precess($xobs, $tjd_tt, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                \Swisseph\Precession::precessSpeed($xobs, $tjd_tt, $iflag, Constants::J2000_TO_J);
            }
        }

        // Save if requested
        if ($do_save) {
            $swed->topd->teval = $tjd_ut;
            for ($i = 0; $i < 6; $i++) {
                $swed->topd->xobs[$i] = $xobs[$i];
            }
        }

        return Constants::SE_OK;
    }

    /**
     * Calculate sidereal time
     * Simplified version - full implementation in SiderealTime class
     */
    private static function sidtime(float $tjd_ut, float $lon): float
    {
        // Use existing sidereal time implementation
        return \Swisseph\Sidereal::sidtime0($tjd_ut, 0.0, 0.0) + $lon / 15.0;
    }

    /**
     * Calculate diurnal arc - observer position relative to Earth center
     * Port of calc_epsilon_nut() and related from swephlib.c
     */
    private static function diurnalArc(float $lat, float $height, float $armc): object
    {
        $DEG2RAD = M_PI / 180.0;
        $lat_rad = $lat * $DEG2RAD;
        $armc_rad = $armc * 15.0 * $DEG2RAD; // hours to radians

        // Earth radius at given latitude (WGS84 ellipsoid)
        $a = 6378.14;  // km, equatorial radius
        $f = 1.0 / 298.257; // flattening
        $e2 = 2 * $f - $f * $f; // eccentricity squared

        $sinlat = sin($lat_rad);
        $coslat = cos($lat_rad);
        $n = $a / sqrt(1.0 - $e2 * $sinlat * $sinlat);

        // Observer position in km
        $x_km = ($n + $height / 1000.0) * $coslat * cos($armc_rad);
        $y_km = ($n + $height / 1000.0) * $coslat * sin($armc_rad);
        $z_km = ($n * (1.0 - $e2) + $height / 1000.0) * $sinlat;

        // Convert to AU (1 AU = 149597870.7 km)
        $AU_KM = 149597870.7;
        $x = $x_km / $AU_KM;
        $y = $y_km / $AU_KM;
        $z = $z_km / $AU_KM;

        // Velocities due to Earth rotation
        // ω = 2π / (sidereal day) = 2π / 86164.0905 seconds
        $omega = 2.0 * M_PI / 86164.0905; // rad/sec
        $omega_day = $omega * 86400.0; // rad/day

        // v = ω × r (cross product)
        $dx = -$y * $omega_day;
        $dy = $x * $omega_day;
        $dz = 0.0;

        return (object)[
            'x' => $x,
            'y' => $y,
            'z' => $z,
            'dx' => $dx,
            'dy' => $dy,
            'dz' => $dz,
        ];
    }
}
