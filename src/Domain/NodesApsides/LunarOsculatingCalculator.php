<?php

declare(strict_types=1);

namespace Swisseph\Domain\NodesApsides;

use Swisseph\Constants;
use Swisseph\Coordinates;
use Swisseph\Math;
use Swisseph\Nutation;
use Swisseph\NutationMatrix;
use Swisseph\Obliquity;
use Swisseph\Precession;
use Swisseph\Bias;
use Swisseph\JplHorizonsApprox;
use Swisseph\VectorMath;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephCalculator;
use Swisseph\SwephFile\SwephConstants;
use Swisseph\SwephFile\SwephPlanCalculator;

/**
 * Calculator for lunar osculating nodes and apsides (True Node, True Lilith)
 *
 * Full port of lunar_osc_elem() from sweph.c:5167-5782
 *
 * This calculates:
 * - SE_TRUE_NODE (osculating lunar node)
 * - SE_OSCU_APOG (osculating lunar apogee / True Black Moon Lilith)
 *
 * The algorithm:
 * 1. Get 3 Moon positions at t-dt, t, t+dt using raw ephemeris (swemoon equivalent)
 * 2. Apply precession/nutation to get ecliptic coordinates of date
 * 3. Calculate node as intersection of orbital plane with ecliptic
 * 4. Calculate apogee from Keplerian orbital elements
 */
class LunarOsculatingCalculator
{
    private const J2000 = 2451545.0;

    // Time intervals for speed calculation
    private const NODE_CALC_INTV = 0.0001;      // for SWIEPH/JPLEPH
    private const NODE_CALC_INTV_MOSH = 0.1;    // for MOSEPH

    // Gravitational constant for Moon orbit
    // Gmsm = G * M(earth+moon) in AU^3/day^2
    // From C: GEOGCONST * (1 + 1/EARTH_MOON_MRAT) / AUNIT^3 * 86400^2

    /**
     * Calculate osculating lunar node and apogee
     *
     * @param float $tjd Julian day TT
     * @param int $ipl SE_TRUE_NODE or SE_OSCU_APOG
     * @param int $iflag Calculation flags
     * @param array &$xreturn Output array [lon, lat, dist, speed_lon, speed_lat, speed_dist,
     *                        x, y, z, vx, vy, vz, RA, Dec, dist, speed_RA, speed_Dec, speed_dist,
     *                        x_eq, y_eq, z_eq, vx_eq, vy_eq, vz_eq] (24 elements)
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

        // Heliocentric/barycentric lunar node not allowed
        if (($iflag & Constants::SEFLG_HELCTR) || ($iflag & Constants::SEFLG_BARYCTR)) {
            return $iflag;
        }

        // Determine ephemeris and speed interval
        $speedIntv = self::NODE_CALC_INTV;
        if ($iflag & Constants::SEFLG_MOSEPH) {
            $speedIntv = self::NODE_CALC_INTV_MOSH;
        }

        // Calculate gravitational constant for Earth-Moon system
        $Gmsm = Constants::GEOGCONST * (1 + 1 / Constants::EARTH_MOON_MRAT) /
                Constants::AUNIT / Constants::AUNIT / Constants::AUNIT * 86400.0 * 86400.0;

        // Determine if speeds are needed
        $istart = ($iflag & Constants::SEFLG_SPEED) ? 0 : 2;

        // Get 3 Moon positions (t-dt, t+dt, t)
        $xpos = [];
        for ($i = $istart; $i <= 2; $i++) {
            // Initialize position array
            $xpos[$i] = array_fill(0, 6, 0.0);

            if ($i === 0) {
                $t = $tjd - $speedIntv;
            } elseif ($i === 1) {
                $t = $tjd + $speedIntv;
            } else {
                $t = $tjd;
            }

            // Get raw Moon position from ephemeris (like swemoon)
            $retc = self::getMoonPosition($t, $iflag, $xpos[$i], $serr);
            if ($retc < 0) {
                return Constants::SE_ERR;
            }

            // Light-time correction for apparent node (~ 0.006")
            if (!($iflag & Constants::SEFLG_TRUEPOS)) {
                $dt = sqrt(VectorMath::squareSum($xpos[$i])) * Constants::AUNIT / Constants::CLIGHT / 86400.0;
                $retc = self::getMoonPosition($t - $dt, $iflag, $xpos[$i], $serr);
                if ($retc < 0) {
                    return Constants::SE_ERR;
                }
            }

            // Apply precession and nutation transformations
            self::planForOscElem($iflag | Constants::SEFLG_SPEED, $t, $xpos[$i]);
        }

        // Calculate node vectors for each time point
        $xx = [];  // Node vectors
        $xxa = []; // Apogee vectors
        $dzmin = 1e-15;

        for ($i = $istart; $i <= 2; $i++) {
            // Ensure minimum z-velocity
            if (abs($xpos[$i][5]) < $dzmin) {
                $xpos[$i][5] = $dzmin;
            }

            $fac = $xpos[$i][2] / $xpos[$i][5];
            $sgn = $xpos[$i][5] / abs($xpos[$i][5]);

            // Node vector: xn = (x - (z/vz) * v) * sgn(vz)
            for ($j = 0; $j <= 2; $j++) {
                $xx[$i][$j] = ($xpos[$i][$j] - $fac * $xpos[$i][$j + 3]) * $sgn;
            }
        }

        // Calculate orbital elements and apogee
        for ($i = $istart; $i <= 2; $i++) {
            // Node longitude from node vector
            $rxy = sqrt($xx[$i][0] * $xx[$i][0] + $xx[$i][1] * $xx[$i][1]);
            $cosnode = $xx[$i][0] / $rxy;
            $sinnode = $xx[$i][1] / $rxy;

            // Inclination from angular momentum vector
            $xnorm = [];
            VectorMath::crossProduct($xpos[$i], array_slice($xpos[$i], 3, 3), $xnorm);

            $rxy2 = $xnorm[0] * $xnorm[0] + $xnorm[1] * $xnorm[1];
            $c2 = $rxy2 + $xnorm[2] * $xnorm[2];
            $rxyz = sqrt($c2);
            $rxy = sqrt($rxy2);
            $sinincl = $rxy / $rxyz;
            $cosincl = sqrt(1 - $sinincl * $sinincl);
            if ($xnorm[2] < 0) {
                $cosincl = -$cosincl;
            }

            // Argument of latitude
            $cosu = $xpos[$i][0] * $cosnode + $xpos[$i][1] * $sinnode;
            $sinu = $xpos[$i][2] / $sinincl;
            $uu = atan2($sinu, $cosu);

            // Semi-major axis
            $rxyz = sqrt(VectorMath::squareSum($xpos[$i]));
            $v2 = VectorMath::squareSum(array_slice($xpos[$i], 3, 3));
            $sema = 1 / (2 / $rxyz - $v2 / $Gmsm);

            // Eccentricity
            $pp = $c2 / $Gmsm;
            $ecce = sqrt(1 - $pp / $sema);

            // Eccentric anomaly
            $cosE = 1 / $ecce * (1 - $rxyz / $sema);
            $sinE = 1 / $ecce / sqrt($sema * $Gmsm) *
                    VectorMath::dotProduct($xpos[$i], array_slice($xpos[$i], 3, 3));

            // True anomaly
            $ny = 2 * atan(sqrt((1 + $ecce) / (1 - $ecce)) * $sinE / (1 + $cosE));

            // Distance of apogee from ascending node
            $xxa[$i][0] = Math::mod2PI($uu - $ny + M_PI);
            $xxa[$i][1] = 0;
            $xxa[$i][2] = $sema * (1 + $ecce);

            // Transform apogee to ecliptic coordinates
            Coordinates::polCart($xxa[$i], $xxa[$i]);
            Coordinates::coortrf2($xxa[$i], $xxa[$i], -$sinincl, $cosincl);
            Coordinates::cartPol($xxa[$i], $xxa[$i]);
            $xxa[$i][0] += atan2($sinnode, $cosnode);
            Coordinates::polCart($xxa[$i], $xxa[$i]);

            // Correct node distance from orbital ellipse
            $ny_node = Math::mod2PI($ny - $uu);
            $cosE = cos(2 * atan(tan($ny_node / 2) / sqrt((1 + $ecce) / (1 - $ecce))));
            $rn = $sema * (1 - $ecce * $cosE);

            // Scale node vector to correct distance
            $r_old = sqrt(VectorMath::squareSum($xx[$i]));
            for ($j = 0; $j <= 2; $j++) {
                $xx[$i][$j] *= $rn / $r_old;
            }
        }

        // Save final node and apogee positions with speeds
        $xnode = [];
        $xapog = [];

        for ($i = 0; $i <= 2; $i++) {
            $xnode[$i] = $xx[2][$i];
            $xapog[$i] = $xxa[2][$i];

            if ($iflag & Constants::SEFLG_SPEED) {
                $xnode[$i + 3] = ($xx[1][$i] - $xx[0][$i]) / $speedIntv / 2;
                $xapog[$i + 3] = ($xxa[1][$i] - $xxa[0][$i]) / $speedIntv / 2;
            } else {
                $xnode[$i + 3] = 0;
                $xapog[$i + 3] = 0;
            }
        }

        // Get obliquity for coordinate transformations
        $swed = SwedState::getInstance();
        if ($swed->oec->needsUpdate($tjd)) {
            $swed->oec->calculate($tjd, $iflag);
        }
        $seps = $swed->oec->seps;
        $ceps = $swed->oec->ceps;

        // Get nutation for coordinate transformations
        $nutModel = Nutation::selectModelFromFlags($iflag);
        [$dpsi, $deps] = Nutation::calc($tjd, $nutModel, false);
        $snut = sin($deps);
        $cnut = cos($deps);

        // Select which result to return based on ipl
        if ($ipl === Constants::SE_TRUE_NODE) {
            $xfinal = $xnode;
        } else {
            $xfinal = $xapog;
        }

        // Build xreturn array (24 elements, same layout as C)
        // Indexes 0-5: ecliptic polar (lon, lat, dist, speed_lon, speed_lat, speed_dist)
        // Indexes 6-11: ecliptic cartesian
        // Indexes 12-17: equatorial polar
        // Indexes 18-23: equatorial cartesian

        // Ecliptic cartesian (indexes 6-11)
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[6 + $i] = $xfinal[$i];
        }

        // Ecliptic polar (indexes 0-5)
        $xpol = array_fill(0, 6, 0.0);
        Coordinates::cartPolSp(array_slice($xreturn, 6, 6), $xpol);
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[$i] = $xpol[$i];
        }

        // Equatorial cartesian (indexes 18-23)
        $xeq = array_fill(0, 3, 0.0);
        Coordinates::coortrf2(array_slice($xreturn, 6, 3), $xeq, -$seps, $ceps);
        $xreturn[18] = $xeq[0];
        $xreturn[19] = $xeq[1];
        $xreturn[20] = $xeq[2];

        if ($iflag & Constants::SEFLG_SPEED) {
            $xeq_vel = array_fill(0, 3, 0.0);
            Coordinates::coortrf2(array_slice($xreturn, 9, 3), $xeq_vel, -$seps, $ceps);
            $xreturn[21] = $xeq_vel[0];
            $xreturn[22] = $xeq_vel[1];
            $xreturn[23] = $xeq_vel[2];
        }

        // Apply nutation to equatorial
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $xeq_nut = array_fill(0, 3, 0.0);
            Coordinates::coortrf2(array_slice($xreturn, 18, 3), $xeq_nut, -$snut, $cnut);
            $xreturn[18] = $xeq_nut[0];
            $xreturn[19] = $xeq_nut[1];
            $xreturn[20] = $xeq_nut[2];

            if ($iflag & Constants::SEFLG_SPEED) {
                $xeq_vel_nut = array_fill(0, 3, 0.0);
                Coordinates::coortrf2(array_slice($xreturn, 21, 3), $xeq_vel_nut, -$snut, $cnut);
                $xreturn[21] = $xeq_vel_nut[0];
                $xreturn[22] = $xeq_vel_nut[1];
                $xreturn[23] = $xeq_vel_nut[2];
            }
        }

        // Equatorial polar (indexes 12-17)
        $xeq_pol = array_fill(0, 6, 0.0);
        Coordinates::cartPolSp(array_slice($xreturn, 18, 6), $xeq_pol);
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[12 + $i] = $xeq_pol[$i];
        }

        // Convert to degrees
        for ($i = 0; $i < 2; $i++) {
            $xreturn[$i] = Math::radToDeg($xreturn[$i]);     // ecliptic lon, lat
            $xreturn[$i + 3] = Math::radToDeg($xreturn[$i + 3]);
            $xreturn[$i + 12] = Math::radToDeg($xreturn[$i + 12]); // equatorial RA, Dec
            $xreturn[$i + 15] = Math::radToDeg($xreturn[$i + 15]);
        }
        $xreturn[0] = Math::normAngleDeg($xreturn[0]);
        $xreturn[12] = Math::normAngleDeg($xreturn[12]);

        // For True Node, force latitude to 0
        if ($ipl === Constants::SE_TRUE_NODE) {
            if (!($iflag & Constants::SEFLG_SIDEREAL) && !($iflag & Constants::SEFLG_J2000)) {
                $xreturn[1] = 0.0;   // ecliptic latitude
                $xreturn[4] = 0.0;   // speed
                $xreturn[5] = 0.0;   // radial speed
                $xreturn[8] = 0.0;   // z coordinate
                $xreturn[11] = 0.0;  // z speed
            }
        }

        return $iflag;
    }

    /**
     * Get raw Moon position from ephemeris (equivalent to swemoon in C)
     *
     * Returns geocentric Moon position in J2000 equatorial XYZ coordinates
     *
     * @param float $tjd Julian day TT
     * @param int $iflag Calculation flags
     * @param array &$xp Output position/velocity [6]
     * @param string|null &$serr Error string
     * @return int OK or error code
     */
    private static function getMoonPosition(float $tjd, int $iflag, array &$xp, ?string &$serr): int
    {
        $swed = SwedState::getInstance();

        // Use SwephPlanCalculator to get raw Moon position
        // Result for Moon is returned in xpret (first output param)
        $xpret = array_fill(0, 6, 0.0);
        $xpe = null;
        $xps = null;
        $xpm = null;

        $retc = SwephPlanCalculator::calculate(
            $tjd,
            SwephConstants::SEI_MOON,
            Constants::SE_MOON,
            SwephConstants::SEI_FILE_MOON,
            $iflag | Constants::SEFLG_SPEED,
            false,  // don't save
            $xpret,
            $xpe,
            $xps,
            $xpm,
            $serr
        );

        if ($retc < 0) {
            return Constants::SE_ERR;
        }

        // Moon position is in xpret (geocentric J2000 equatorial XYZ)
        $xp = $xpret;

        return 0;
    }

    /**
     * Transform coordinates for osculating element calculation
     *
     * Port of swi_plan_for_osc_elem() from sweph.c:5787-5909
     *
     * Chain: ICRS → J2000 → date equatorial → date ecliptic
     *
     * @param int $iflag Calculation flags
     * @param float $tjd Julian day
     * @param array &$xx Position and velocity [6]
     */
    private static function planForOscElem(int $iflag, float $tjd, array &$xx): void
    {
        $swed = SwedState::getInstance();

        // Step 1: ICRS to J2000 (frame bias)
        if (!($iflag & Constants::SEFLG_ICRS)) {
            $xx = Bias::apply(
                $xx,
                $tjd,
                $iflag,
                Bias::MODEL_IAU_2006,
                false,
                JplHorizonsApprox::SEMOD_JPLHORA_DEFAULT
            );
        }

        // Step 2: Precession from J2000 to date
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($xx, $tjd, $iflag, -1, null);
            $vel = [$xx[3], $xx[4], $xx[5]];
            Precession::precess($vel, $tjd, $iflag, -1, null);
            $xx[3] = $vel[0];
            $xx[4] = $vel[1];
            $xx[5] = $vel[2];
        }

        // Get obliquity
        if ($swed->oec->needsUpdate($tjd)) {
            $swed->oec->calculate($tjd, $iflag);
        }
        $seps = $swed->oec->seps;
        $ceps = $swed->oec->ceps;

        // Step 3: Nutation (mean to true equator)
        if (!($iflag & Constants::SEFLG_NONUT) && !($iflag & Constants::SEFLG_J2000)) {
            $nutModel = Nutation::selectModelFromFlags($iflag);
            [$dpsi, $deps] = Nutation::calc($tjd, $nutModel, false);
            $eps = Obliquity::calc($tjd, $iflag, 0, null);

            $nutMatrix = NutationMatrix::build($dpsi, $deps, $eps, sin($eps), cos($eps));

            $xTemp = NutationMatrix::apply($nutMatrix, [$xx[0], $xx[1], $xx[2]]);
            $xx[0] = $xTemp[0];
            $xx[1] = $xTemp[1];
            $xx[2] = $xTemp[2];

            $velTemp = NutationMatrix::apply($nutMatrix, [$xx[3], $xx[4], $xx[5]]);
            $xx[3] = $velTemp[0];
            $xx[4] = $velTemp[1];
            $xx[5] = $velTemp[2];
        }

        // Step 4: Equatorial to ecliptic
        $xOut = [];
        Coordinates::coortrf2([$xx[0], $xx[1], $xx[2]], $xOut, $seps, $ceps);
        $xx[0] = $xOut[0];
        $xx[1] = $xOut[1];
        $xx[2] = $xOut[2];

        $velOut = [];
        Coordinates::coortrf2([$xx[3], $xx[4], $xx[5]], $velOut, $seps, $ceps);
        $xx[3] = $velOut[0];
        $xx[4] = $velOut[1];
        $xx[5] = $velOut[2];

        // Step 5: Apply nutation to ecliptic
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $nutModel = Nutation::selectModelFromFlags($iflag);
            [, $deps] = Nutation::calc($tjd, $nutModel, false);
            $snut = sin($deps);
            $cnut = cos($deps);

            Coordinates::coortrf2([$xx[0], $xx[1], $xx[2]], $xOut, $snut, $cnut);
            $xx[0] = $xOut[0];
            $xx[1] = $xOut[1];
            $xx[2] = $xOut[2];

            Coordinates::coortrf2([$xx[3], $xx[4], $xx[5]], $velOut, $snut, $cnut);
            $xx[3] = $velOut[0];
            $xx[4] = $velOut[1];
            $xx[5] = $velOut[2];
        }
    }
}
