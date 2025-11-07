<?php

namespace Swisseph\Swe\Observer;

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\Sidereal;

/**
 * Observer (topocentric) position calculation
 * EXACT port of swi_get_observer() from sweph.c:7336-7433
 *
 * NO SIMPLIFICATIONS - line-by-line translation from C
 */
class Observer
{
    // Constants from sweodef.h
    private const EARTH_OBLATENESS = 0.00335281066474748;  // (1.0 / 298.257223563)
    private const EARTH_RADIUS = 6378136.6;  // meters
    private const EARTH_ROT_SPEED = 7.2921151467064e-5;  // rad/sec
    private const AUNIT = 1.4959787066e+11;  // AU in meters

    /**
     * Get observer (topocentric) position in cartesian coordinates
     *
     * EXACT port of swi_get_observer() from sweph.c:7336-7433
     *
     * @param float $tjd Julian day in TT (NOT UT!)
     * @param int $iflag Calculation flags
     * @param bool $do_save Whether to save in SwedState::topd
     * @param array &$xobs Output: observer position [x, y, z, vx, vy, vz] in AU
     * @param string|null &$serr Error message
     * @return int OK or ERR
     */
    public static function getObserver(
        float $tjd,
        int $iflag,
        bool $do_save,
        array &$xobs,
        ?string &$serr = null
    ): int {
        $xobs = array_fill(0, 6, 0.0);

        $swed = SwedState::getInstance();

        // Line 7340-7344: Check if geographic position is set
        if (!isset($swed->topd->geolon)) {
            if ($serr !== null) {
                $serr = "geographic position has not been set";
            }
            return Constants::SE_ERR;
        }

        // Line 7345-7350: Compute UT from ET (TT)
        // Geocentric position of observer depends on sidereal time,
        // which depends on UT.
        $delt = \Swisseph\DeltaT::deltaTSecondsFromJd($tjd) / 86400.0;
        $tjd_ut = $tjd - $delt;

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Observer] tjd=%.10f, delt=%.10f, tjd_ut=%.10f", $tjd, $delt, $tjd_ut));
        }

        // Line 7351-7361: Get obliquity and nutation (centralized via SwedState)
        if ($swed->oec->needsUpdate($tjd)) {
            $swed->oec->calculate($tjd, $iflag);
        }
        // Ensure nutation cache for this date
        $swed->ensureNutation($tjd, $iflag, $swed->oec->seps, $swed->oec->ceps);
        $eps = $swed->oec->eps;
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $nutlo_0 = $swed->dpsi;  // dpsi
            $nutlo_1 = $swed->deps;  // deps
        } else {
            $nutlo_0 = 0.0;
            $nutlo_1 = 0.0;
        }

        // Line 7362-7367: Apply nutation to obliquity
        if ($iflag & Constants::SEFLG_NONUT) {
            $nut = 0.0;
        } else {
            $eps += $nutlo_1;
            $nut = $nutlo_0;
        }

        // Line 7368-7370: Mean or apparent sidereal time
        $sidt = Sidereal::sidtime0($tjd_ut, $eps * Constants::RADTODEG, $nut * Constants::RADTODEG);
        $sidt *= 15.0;  // Convert hours to degrees

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Observer] sidt=%.15f degrees, geolon=%.6f, geolon+sidt=%.15f",
                $sidt, $swed->topd->geolon, $swed->topd->geolon + $sidt));
        }

        // Line 7371-7390: Calculate observer position on ellipsoid
        // Length of position and speed vectors.
        // The height above sea level must be taken into account.
        // With the moon, an altitude of 3000 m makes a difference
        // of about 2 arc seconds.
        $f = self::EARTH_OBLATENESS;
        $re = self::EARTH_RADIUS;

        $cosfi = cos($swed->topd->geolat * Constants::DEGTORAD);
        $sinfi = sin($swed->topd->geolat * Constants::DEGTORAD);
        $cc = 1.0 / sqrt($cosfi * $cosfi + (1.0 - $f) * (1.0 - $f) * $sinfi * $sinfi);
        $ss = (1.0 - $f) * (1.0 - $f) * $cc;

        // Line 7391-7396: Add sidereal time
        $cosl = cos(($swed->topd->geolon + $sidt) * Constants::DEGTORAD);
        $sinl = sin(($swed->topd->geolon + $sidt) * Constants::DEGTORAD);
        $h = $swed->topd->geoalt;

        // Debug: print geolat value
        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Observer] geolat=%.6f, cosfi=%.15f, sinfi=%.15f", $swed->topd->geolat, $cosfi, $sinfi));
        }

        // Line 7397-7399: Cartesian coordinates
        $xobs[0] = ($re * $cc + $h) * $cosfi * $cosl;
        $xobs[1] = ($re * $cc + $h) * $cosfi * $sinl;
        $xobs[2] = ($re * $ss + $h) * $sinfi;

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Observer] AFTER cartesian: xobs=[%.15f, %.15f, %.15f] meters",
                $xobs[0], $xobs[1], $xobs[2]));
        }

        // Line 7400-7401: Convert to polar coordinates
        self::cartpol($xobs, $xobs);

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Observer] AFTER cartpol: xobs=[%.15f, %.15f, %.15f] (lon/lat in rad, r in meters)",
                $xobs[0], $xobs[1], $xobs[2]));
        }

        // Line 7402-7404: Add rotation speed
        $xobs[3] = self::EARTH_ROT_SPEED;
        $xobs[4] = 0.0;
        $xobs[5] = 0.0;

        // Line 7405: Convert back to cartesian with speed
        self::polcartSp($xobs, $xobs);

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Observer] AFTER polcartSp: xobs=[%.15f, %.15f, %.15f] meters",
                $xobs[0], $xobs[1], $xobs[2]));
        }

        // Line 7406-7408: Convert to AU
        for ($i = 0; $i <= 5; $i++) {
            $xobs[$i] /= self::AUNIT;
        }

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Observer] AFTER /AUNIT: xobs=[%.15f, %.15f, %.15f] AU",
                $xobs[0], $xobs[1], $xobs[2]));
        }

        // Line 7409-7414: Subtract nutation (apply backward nutation matrix)
        if (!($iflag & Constants::SEFLG_NONUT)) {
            \Swisseph\Coordinates::nutate(
                $xobs,
                $swed->nutMatrix,
                $swed->nutMatrixVelocity,
                $iflag | Constants::SEFLG_SPEED,
                true
            );
        }

        // Line 7415-7417: Precess to J2000
        \Swisseph\Precession::precess($xobs, $tjd, $iflag, Constants::J_TO_J2000);
        \Swisseph\Precession::precessSpeed($xobs, $tjd, $iflag, Constants::J_TO_J2000);

        if (getenv('DEBUG_OBSERVER')) {
            error_log(sprintf("[Observer] AFTER precess: xobs=[%.15f, %.15f, %.15f] AU",
                $xobs[0], $xobs[1], $xobs[2]));
        }

        // Line 7418-7420: Neglect frame bias (45cm displacement)
        // ... (not implemented)

        // Line 7421-7427: Save if requested
        if ($do_save) {
            for ($i = 0; $i <= 5; $i++) {
                $swed->topd->xobs[$i] = $xobs[$i];
            }
            $swed->topd->teval = $tjd;
            $swed->topd->tjd_ut = $tjd_ut;
        }

        return Constants::SE_OK;
    }

    /**
     * Convert cartesian coordinates to polar (swi_cartpol from sweph.c:6745)
     * EXACT port - NO SIMPLIFICATIONS - line-by-line translation from C
     *
     * @param array $x Input cartesian [x,y,z]
     * @param array $xpol Output polar coordinates [r,theta,phi]
     */
    private static function cartpol(array $x, array &$xpol): void
    {
        // EXACT port of swi_cartpol from swephlib.c:314-340
        // Returns [lon, lat, r] in RADIANS (not degrees!)
        // NO CONVERSIONS - must match C exactly

        $rxy = $x[0] * $x[0] + $x[1] * $x[1];
        $ll = [0.0, 0.0, 0.0];

        if ($x[0] === 0.0 && $x[1] === 0.0 && $x[2] === 0.0) {
            $xpol[0] = 0.0;
            $xpol[1] = 0.0;
            $xpol[2] = 0.0;
            return;
        }

        $ll[2] = sqrt($rxy + $x[2] * $x[2]);  // r
        $rxy = sqrt($rxy);
        $ll[0] = atan2($x[1], $x[0]);  // lon in radians
        if ($ll[0] < 0.0) {
            $ll[0] += 2.0 * M_PI;  // TWOPI
        }

        if ($rxy === 0.0) {
            if ($x[2] >= 0.0) {
                $ll[1] = M_PI / 2.0;
            } else {
                $ll[1] = -(M_PI / 2.0);
            }
        } else {
            $ll[1] = atan($x[2] / $rxy);  // lat in radians
        }

        $xpol[0] = $ll[0];  // lon (radians)
        $xpol[1] = $ll[1];  // lat (radians)
        $xpol[2] = $ll[2];  // r
    }

    /**
     * Convert polar coordinates to cartesian with speed (swi_polcart_sp from sweph.c:6781)
     * EXACT port - NO SIMPLIFICATIONS - line-by-line translation from C
     *
     * @param array $xpol Input polar [r,theta,phi,dr,dtheta,dphi]
     * @param array $xcart Output cartesian [x,y,z,dx,dy,dz]
     */
    private static function polcartSp(array $l, array &$x): void
    {
        // EXACT port of swi_polcart_sp from swephlib.c:420-453
        // Input l[] is [lon, lat, r, dlon, dlat, dr] in RADIANS (not degrees!)

        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        // Zero speed case
        if ($l[3] === 0.0 && $l[4] === 0.0 && $l[5] === 0.0) {
            $x[3] = $x[4] = $x[5] = 0.0;
            // swi_polcart for position only
            $coslon = cos($l[0]);
            $sinlon = sin($l[0]);
            $coslat = cos($l[1]);
            $sinlat = sin($l[1]);
            $x[0] = $l[2] * $coslat * $coslon;
            $x[1] = $l[2] * $coslat * $sinlon;
            $x[2] = $l[2] * $sinlat;
            return;
        }

        // Position
        $coslon = cos($l[0]);
        $sinlon = sin($l[0]);
        $coslat = cos($l[1]);
        $sinlat = sin($l[1]);
        $xx[0] = $l[2] * $coslat * $coslon;
        $xx[1] = $l[2] * $coslat * $sinlon;
        $xx[2] = $l[2] * $sinlat;

        // Speed
        $rxyz = $l[2];
        $rxy = sqrt($xx[0] * $xx[0] + $xx[1] * $xx[1]);
        $xx[5] = $l[5];
        $xx[4] = $l[4] * $rxyz;
        $x[5] = $sinlat * $xx[5] + $coslat * $xx[4];  // speed z
        $xx[3] = $coslat * $xx[5] - $sinlat * $xx[4];
        $xx[4] = $l[3] * $rxy;
        $x[3] = $coslon * $xx[3] - $sinlon * $xx[4];  // speed x
        $x[4] = $sinlon * $xx[3] + $coslon * $xx[4];  // speed y

        // Return position
        $x[0] = $xx[0];
        $x[1] = $xx[1];
        $x[2] = $xx[2];
    }
}
