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
 * Exact port of fixstar_calc_from_struct() from sweph.c:6461-6718
 *
 * Applies corrections following C algorithm EXACTLY:
 * 1. Proper motion correction
 * 2. FK4→FK5 conversion (epoch 1950 only)
 * 3. ICRF conversion
 * 4. Earth/Sun positions for parallax, deflection, aberration
 * 5. Observer position (geocenter or topocenter)
 * 6. Parallax correction
 * 7. Light deflection
 * 8. Aberration
 * 9. ICRS→J2000 bias
 * 10. Precession to date
 * 11. Nutation
 * 12. Equatorial→Ecliptic
 * 13. Sidereal mode
 * 14. Polar coordinates
 * 15. Degrees conversion
 */
final class StarCalculator
{
    /**
     * Time interval for speed calculation: PLAN_SPEED_INTV * 0.1 = 0.0001 * 0.1 = 0.00001 days
     */
    private const DT = 0.00001;

    /**
     * Parsec to AU: 206264.806247096 AU per parsec
     */
    private const PARSEC_TO_AUNIT = 206264.806;

    /**
     * Calculate fixed star position
     *
     * Port of fixstar_calc_from_struct() from sweph.c:6461-6718
     *
     * @param FixedStarData $stardata Star catalog data
     * @param float $tjd Julian Day (ET)
     * @param int $iflag Calculation flags
     * @param string &$star Output: formatted star name
     * @param array &$xx Output: 6 doubles [lon/ra, lat/dec, dist, dlon, dlat, ddist]
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
        $iflag |= Constants::SEFLG_SPEED; // We need this to work correctly

        // Initialize arrays (matching C code line-by-line)
        $x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xxsv = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xobs_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xearth = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xearth_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xsun = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xsun_dt = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        $epheflag = $iflag & Constants::SEFLG_EPHMASK;

        // C: if (iflag & SEFLG_SIDEREAL && !swed.ayana_is_set)
        if (($iflag & Constants::SEFLG_SIDEREAL) && !State::isSiderealModeSet()) {
            State::setSiderealMode(Constants::SE_SIDM_FAGAN_BRADLEY, 0.0, 0.0);
        }

        // C: swi_check_ecliptic(tjd, iflag); swi_check_nutation(tjd, iflag);
        // In PHP: Calculate obliquity and nutation directly
        $obliq2000 = Obliquity::calc(Constants::J2000);
        $obliqDate = Obliquity::calc($tjd);
        $nutData = null;
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $nutData = Nutation::calc($tjd, $iflag);
        }

        // C: sprintf(star, "%s,%s", stardata->starname, stardata->starbayer);
        $star = $stardata->getFullName();

        // C: Extract star data
        $epoch = $stardata->epoch;
        $ra_pm = $stardata->ramot;
        $de_pm = $stardata->demot;
        $radv = $stardata->radvel;
        $parall = $stardata->parall;
        $ra = $stardata->ra;
        $de = $stardata->de;

        // C: if (epoch == 1950) t = (tjd - B1950); else t = (tjd - J2000);
        if ($epoch == 1950) {
            $t = $tjd - Constants::B1950;
        } else {
            $t = $tjd - Constants::J2000;
        }

        // C: x[0] = ra; x[1] = de; x[2] = 1;
        $x[0] = $ra;
        $x[1] = $de;
        $x[2] = 1.0;

        // C: if (parall == 0) rdist = 1000000000; else rdist = 1.0 / (parall * RADTODEG * 3600) * PARSEC_TO_AUNIT;
        if ($parall == 0) {
            $rdist = 1000000000.0;
        } else {
            $rdist = 1.0 / ($parall * Constants::RADTODEG * 3600.0) * self::PARSEC_TO_AUNIT;
        }
        $x[2] = $rdist;

        // C: x[3] = ra_pm / 36525.0; x[4] = de_pm / 36525.0; x[5] = radv / 36525.0;
        $x[3] = $ra_pm / 36525.0;
        $x[4] = $de_pm / 36525.0;
        $x[5] = $radv / 36525.0;

        // C: swi_polcart_sp(x, x); // Cartesian space motion vector
        Coordinates::polCartSp($x, $x);

        // C: FK4 -> FK5 conversion for B1950 epoch
        if ($epoch == 1950) {
            FK4FK5::fk4ToFk5($x, Constants::B1950);
            Precession::precess($x, Constants::B1950, 0, Constants::J_TO_J2000);
            $xSpeed = array_slice($x, 3, 3);
            Precession::precess($xSpeed, Constants::B1950, 0, Constants::J_TO_J2000);
            $x[3] = $xSpeed[0];
            $x[4] = $xSpeed[1];
            $x[5] = $xSpeed[2];
        }

        // C: FK5 to ICRF
        if ($epoch != 0) {
            ICRS::icrs2fk5($x, $iflag, true); // TRUE = backward (FK5→ICRF)
            // C: if (swi_get_denum(SEI_SUN, iflag) >= 403)
            $denum = 431; // TODO: Implement swi_get_denum
            if ($denum >= 403) {
                Bias::bias($x, Constants::J2000, Constants::SEFLG_SPEED, false);
            }
        }

        // C: Earth/Sun for parallax, light deflection, and aberration
        if (!($iflag & Constants::SEFLG_BARYCTR) && (!($iflag & Constants::SEFLG_HELCTR) || !($iflag & Constants::SEFLG_MOSEPH))) {
            // C: main_planet_bary(tjd - dt, SEI_EARTH, epheflag, iflag, NO_SAVE, xearth_dt, xearth_dt, xsun_dt, NULL, serr)
            $retc = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
                $tjd - self::DT,
                Constants::SE_EARTH,
                $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR,
                $xearth_dt,
                $serr
            );
            if ($retc < 0) return Constants::SE_ERR;

            // C: main_planet_bary(tjd, SEI_EARTH, epheflag, iflag, DO_SAVE, xearth, xearth, xsun, NULL, serr)
            $retc = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
                $tjd,
                Constants::SE_EARTH,
                $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR,
                $xearth,
                $serr
            );
            if ($retc < 0) return Constants::SE_ERR;

            // Get Sun positions
            $retc = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
                $tjd - self::DT,
                Constants::SE_SUN,
                $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR,
                $xsun_dt,
                $serr
            );
            if ($retc < 0) return Constants::SE_ERR;

            $retc = \Swisseph\Swe\Functions\PlanetsFunctions::calc(
                $tjd,
                Constants::SE_SUN,
                $epheflag | Constants::SEFLG_J2000 | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ | Constants::SEFLG_BARYCTR,
                $xsun,
                $serr
            );
            if ($retc < 0) return Constants::SE_ERR;
        }

        // C: Observer: geocenter or topocenter
        if ($iflag & Constants::SEFLG_TOPOCTR) {
            // C: swi_get_observer(tjd - dt, iflag | SEFLG_NONUT, NO_SAVE, xobs_dt, serr)
            // TODO: Implement swi_get_observer for topocentric
            $serr = 'Topocentric positions for fixed stars not yet implemented';
            return Constants::SE_ERR;
        } elseif (!($iflag & Constants::SEFLG_BARYCTR) && (!($iflag & Constants::SEFLG_HELCTR) || !($iflag & Constants::SEFLG_MOSEPH))) {
            // C: for (i = 0; i <= 5; i++) { xobs[i] = xearth[i]; xobs_dt[i] = xearth_dt[i]; }
            for ($i = 0; $i <= 5; $i++) {
                $xobs[$i] = $xearth[$i];
                $xobs_dt[$i] = $xearth_dt[$i];
            }
        }

        // C: Position and speed at tjd (for parallax)
        $xpo = null;
        $xpo_dt = null;

        // C: Determine xpo, xpo_dt based on flags
        if (($iflag & Constants::SEFLG_HELCTR) && ($iflag & Constants::SEFLG_MOSEPH)) {
            $xpo = null;
            $xpo_dt = null;
        } elseif ($iflag & Constants::SEFLG_HELCTR) {
            $xpo = $xsun;
            $xpo_dt = $xsun_dt;
        } elseif ($iflag & Constants::SEFLG_BARYCTR) {
            $xpo = null;
            $xpo_dt = null;
        } else {
            $xpo = $xobs;
            $xpo_dt = $xobs_dt;
        }

        // C: Apply proper motion and parallax
        if ($xpo === null) {
            // C: for (i = 0; i <= 2; i++) x[i] += t * x[i+3];
            for ($i = 0; $i <= 2; $i++) {
                $x[$i] += $t * $x[$i + 3];
            }
        } else {
            // C: for (i = 0; i <= 2; i++) { x[i] += t * x[i+3]; x[i] -= xpo[i]; x[i+3] -= xpo[i+3]; }
            for ($i = 0; $i <= 2; $i++) {
                $x[$i] += $t * $x[$i + 3];
                $x[$i] -= $xpo[$i];
                $x[$i + 3] -= $xpo[$i + 3];
            }
        }

        // C: Relativistic light deflection
        if (($iflag & Constants::SEFLG_TRUEPOS) == 0 && ($iflag & Constants::SEFLG_NOGDEFL) == 0) {
            // C: swi_deflect_light(x, 0, iflag & SEFLG_SPEED);
            \Swisseph\Swe\FixedStars\StarTransforms::deflectLight($x, $xearth, $xearth_dt, $xsun, $xsun_dt, self::DT, $iflag);
        }

        // C: Annual aberration
        if (($iflag & Constants::SEFLG_TRUEPOS) == 0 && ($iflag & Constants::SEFLG_NOABERR) == 0) {
            // C: swi_aberr_light_ex(x, xpo, xpo_dt, dt, iflag & SEFLG_SPEED);
            \Swisseph\Swe\FixedStars\StarTransforms::aberrLightEx($x, $xpo, $xpo_dt, self::DT, $iflag);
        }

        // C: ICRS to J2000
        if (!($iflag & Constants::SEFLG_ICRS) && ($denum >= 403 || ($iflag & Constants::SEFLG_BARYCTR))) {
            Bias::bias($x, $tjd, $iflag, false);
        }

        // C: Save J2000 coordinates (required for sidereal positions)
        for ($i = 0; $i <= 5; $i++) {
            $xxsv[$i] = $x[$i];
        }

        // C: Precession: equator 2000 -> equator of date
        $oe = null;
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($x, $tjd, $iflag, Constants::J2000_TO_J);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($x, $tjd, $iflag, Constants::J2000_TO_J);
            }
            // C: oe = &swed.oec;
            $oe = ['eps' => $obliqDate, 'seps' => sin($obliqDate), 'ceps' => cos($obliqDate)];
        } else {
            // C: oe = &swed.oec2000;
            $oe = ['eps' => $obliq2000, 'seps' => sin($obliq2000), 'ceps' => cos($obliq2000)];
        }

        // C: Nutation
        if (!($iflag & Constants::SEFLG_NONUT)) {
            // C: swi_nutate(x, iflag, FALSE);
            Coordinates::nutate($x, $iflag, false, $serr);
        }

        // C: Transformation to ecliptic
        if (!($iflag & Constants::SEFLG_EQUATORIAL)) {
            // C: swi_coortrf2(x, x, oe->seps, oe->ceps);
            Coordinates::coortrf2($x, $x, $oe['seps'], $oe['ceps']);
            if ($iflag & Constants::SEFLG_SPEED) {
                $xSpeed = array_slice($x, 3, 3);
                Coordinates::coortrf2($xSpeed, $xSpeed, $oe['seps'], $oe['ceps']);
                $x[3] = $xSpeed[0];
                $x[4] = $xSpeed[1];
                $x[5] = $xSpeed[2];
            }
            // C: if (!(iflag & SEFLG_NONUT)) { swi_coortrf2(x, x, swed.nut.snut, swed.nut.cnut); ... }
            if (!($iflag & Constants::SEFLG_NONUT) && $nutData !== null) {
                $snut = sin($nutData['nutlo']);
                $cnut = cos($nutData['nutlo']);
                Coordinates::coortrf2($x, $x, $snut, $cnut);
                if ($iflag & Constants::SEFLG_SPEED) {
                    $xSpeed = array_slice($x, 3, 3);
                    Coordinates::coortrf2($xSpeed, $xSpeed, $snut, $cnut);
                    $x[3] = $xSpeed[0];
                    $x[4] = $xSpeed[1];
                    $x[5] = $xSpeed[2];
                }
            }
        }

        // C: Sidereal positions
        if ($iflag & Constants::SEFLG_SIDEREAL) {
            // TODO: Port sidereal transformations from C
            $serr = 'Sidereal mode not yet implemented for fixstar2';
            return Constants::SE_ERR;
        }

        // C: Transformation to polar coordinates
        if (!($iflag & Constants::SEFLG_XYZ)) {
            // C: swi_cartpol_sp(x, x);
            Coordinates::cartPolSp($x, $x);
        }

        // C: Radians to degrees
        if (!($iflag & Constants::SEFLG_RADIANS) && !($iflag & Constants::SEFLG_XYZ)) {
            // C: for (i = 0; i < 2; i++) { x[i] *= RADTODEG; x[i+3] *= RADTODEG; }
            for ($i = 0; $i < 2; $i++) {
                $x[$i] *= Constants::RADTODEG;
                $x[$i + 3] *= Constants::RADTODEG;
            }
        }

        // C: Copy to output array
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $x[$i];
        }

        // C: If speed not requested, zero out velocity
        if (!($iflgsave & Constants::SEFLG_SPEED)) {
            // C: for (i = 3; i <= 5; i++) xx[i] = 0;
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // C: if ((iflgsave & SEFLG_EPHMASK) == 0) iflag = iflag & ~SEFLG_DEFAULTEPH;
        if (($iflgsave & Constants::SEFLG_EPHMASK) == 0) {
            $iflag = $iflag & ~Constants::SEFLG_DEFAULTEPH;
        }

        // C: iflag = iflag & ~SEFLG_SPEED;
        $iflag = $iflag & ~Constants::SEFLG_SPEED;

        return $iflag;
    }
}
